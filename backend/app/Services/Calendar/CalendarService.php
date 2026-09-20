<?php

namespace App\Services\Calendar;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\CalendarReminderOffset;
use App\Enums\CalendarSource;
use App\Enums\CalendarVisibility;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\CalendarReminder;
use App\Models\CrmCompany;
use App\Models\CrmContact;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Models\CrmQuotation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CalendarNotification;
use App\Services\Crm\CrmCalendarService;
use App\Services\Operations\OperationalNotifier;
use App\Services\Operations\Work\TaskCalendarLinkService;
use App\Services\ProjectActivityService;
use App\Services\Workflow\WorkflowAutomationEngine;
use App\Support\Calendar\CalendarDateTime;
use App\Support\Calendar\CalendarOccurrenceReference;
use App\Support\Calendar\CalendarRelatedEntityUrlResolver;
use App\Support\Calendar\CalendarWorkloadRules;
use App\Support\Operations\TaskCalendarSyncContext;
use App\Support\Workflow\AutomationContext;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalendarService
{
    public function __construct(
        private readonly CrmCalendarService $crmCalendar,
        private readonly RecurrenceExpander $recurrenceExpander,
        private readonly CalendarActivityLogger $activityLogger,
        private readonly CalendarDerivedEventsService $derivedEvents,
        private readonly CalendarRelatedEntityUrlResolver $relatedUrls,
        private readonly ProjectActivityService $projectActivities,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function listFor(User $actor, array $filters): array
    {
        $from = Carbon::parse((string) ($filters['from'] ?? now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse((string) ($filters['to'] ?? now()->endOfMonth()->toDateString()))->endOfDay();

        if ($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) > 93) {
            throw ValidationException::withMessages([
                'to' => 'نطاق التقويم يجب ألا يتجاوز 93 يوماً.',
            ]);
        }

        $this->markOverdue();

        $query = CalendarItem::query()
            ->with(['creator:id,name', 'assignees:id,name,role', 'reminders'])
            ->withCount(['comments', 'files'])
            ->where(function (Builder $builder) use ($from, $to): void {
                $builder->where(function (Builder $nonRecurring) use ($from, $to): void {
                    $nonRecurring->where(function (Builder $rule) {
                        $rule->whereNull('recurrence_rule')->orWhere('recurrence_rule', '');
                    })->where(function (Builder $range) use ($from, $to): void {
                        $range->whereBetween('starts_at', [$from, $to])
                            ->orWhere(function (Builder $inner) use ($from, $to): void {
                                $inner->whereNotNull('ends_at')
                                    ->where('starts_at', '<=', $to)
                                    ->where('ends_at', '>=', $from);
                            });
                    });
                })->orWhere(function (Builder $masters) use ($from, $to): void {
                    $masters->whereNotNull('recurrence_rule')
                        ->where('recurrence_rule', '!=', '')
                        ->whereNull('recurrence_parent_id')
                        ->where('starts_at', '<=', $to)
                        ->where(function (Builder $until) use ($from): void {
                            $until->whereNull('recurrence_until')
                                ->orWhere('recurrence_until', '>=', $from);
                        });
                });
            });

        $this->applyVisibilityScope($query, $actor, $filters['scope'] ?? 'mine');
        $this->applyListFilters($query, $filters);

        $items = [];
        foreach ($query->orderBy('starts_at')->get() as $item) {
            if ($item->isRecurringMaster()) {
                $duration = $item->ends_at && $item->starts_at
                    ? $item->starts_at->diffInSeconds($item->ends_at)
                    : 3600;
                foreach ($this->recurrenceExpander->expand($item, $from, $to) as $occurrence) {
                    $items[] = $this->serializeOccurrence($item, $occurrence, (int) $duration);
                }
            } else {
                $items[] = $this->serialize($item);
            }
        }

        $includeLinked = ($filters['include_linked'] ?? '1') !== '0';
        if ($includeLinked && empty($filters['type']) && empty($filters['status']) && empty($filters['priority']) && empty($filters['source']) && empty($filters['q'])) {
            $items = $this->mergeCrmLinkedEvents($actor, $from, $to, $items, $filters['scope'] ?? 'mine');
            $items = array_merge($items, $this->derivedEvents->eventsFor($actor, $from, $to));
        }

        usort($items, fn (array $a, array $b): int => strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? '')));

        return [
            'items' => $items,
            'summary' => $this->summaryFor($actor, $filters['scope'] ?? 'mine'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): CalendarItem
    {
        return DB::transaction(function () use ($actor, $data): CalendarItem {
            $related = $this->resolveRelated($actor, $data['related_type'] ?? null, isset($data['related_id']) ? (int) $data['related_id'] : null);
            $allDay = (bool) ($data['all_day'] ?? false);
            $startsAt = CalendarDateTime::normalizeStart((string) $data['starts_at'], $allDay);
            $endsAt = CalendarDateTime::normalizeEnd(
                isset($data['ends_at']) ? (string) $data['ends_at'] : null,
                $allDay,
            );

            $item = CalendarItem::query()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'status' => $data['status'] ?? CalendarItemStatus::Scheduled->value,
                'priority' => $data['priority'] ?? CalendarItemPriority::Medium->value,
                'visibility' => $data['visibility'] ?? CalendarVisibility::Participants->value,
                'source' => $data['source'] ?? CalendarSource::Manual->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'all_day' => $allDay,
                'created_by' => $actor->id,
                'related_type' => $related['type'],
                'related_id' => $related['id'],
                'related_label' => $related['label'],
                'related_href' => $related['href'],
                'recurrence_rule' => $data['recurrence_rule'] ?? null,
                'recurrence_until' => $data['recurrence_until'] ?? null,
                'recurrence_count' => $data['recurrence_count'] ?? null,
                'recurrence_exceptions' => $data['recurrence_exceptions'] ?? null,
                'location' => $data['location'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'blocked_by_id' => $data['blocked_by_id'] ?? null,
                'checklist' => $data['checklist'] ?? null,
                'department_id' => $data['department_id'] ?? null,
            ]);

            $assigneeIds = $this->normalizeAssigneeIds($actor, $data['assignee_ids'] ?? [$actor->id]);
            $item->assignees()->sync($assigneeIds);
            $this->syncReminders($item, $data['reminders'] ?? []);
            $this->notifyAssignees($item, $assigneeIds, $actor, 'assigned');
            $this->activityLogger->log($item, $actor, 'created', 'تم إنشاء عنصر التقويم');
            $this->projectActivities->recordFromCalendarItem($item, $actor, 'created');

            $fresh = $item->fresh(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files']);
            $this->dispatchTaskCreatedHook($actor, $fresh);

            return $fresh;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, CalendarItem $item, array $data): CalendarItem
    {
        $scope = $data['scope'] ?? null;
        if ($item->isRecurringMaster() && in_array($scope, ['this', 'future', 'all'], true)) {
            return $this->updateRecurring($actor, $item, $data, $scope);
        }

        return DB::transaction(fn () => $this->applyDirectUpdate($actor, $item, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyDirectUpdate(User $actor, CalendarItem $item, array $data): CalendarItem
    {
        $previousAssignees = $item->assignees()->pluck('users.id')->all();
        $related = array_key_exists('related_type', $data) || array_key_exists('related_id', $data)
            ? $this->resolveRelated(
                $actor,
                $data['related_type'] ?? $item->related_type,
                array_key_exists('related_id', $data)
                    ? ($data['related_id'] !== null ? (int) $data['related_id'] : null)
                    : $item->related_id,
            )
            : null;

        $allDay = array_key_exists('all_day', $data) ? (bool) $data['all_day'] : (bool) $item->all_day;
        $startsAt = array_key_exists('starts_at', $data)
            ? CalendarDateTime::normalizeStart((string) $data['starts_at'], $allDay)
            : $item->starts_at;
        $endsAt = array_key_exists('ends_at', $data)
            ? CalendarDateTime::normalizeEnd($data['ends_at'] !== null ? (string) $data['ends_at'] : null, $allDay)
            : $item->ends_at;

        $item->fill([
            'title' => $data['title'] ?? $item->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $item->description,
            'type' => $data['type'] ?? $item->type,
            'status' => $data['status'] ?? $item->status,
            'priority' => $data['priority'] ?? $item->priority,
            'visibility' => $data['visibility'] ?? $item->visibility,
            'source' => $data['source'] ?? $item->source,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => $allDay,
            'location' => array_key_exists('location', $data) ? $data['location'] : $item->location,
            'meeting_url' => array_key_exists('meeting_url', $data) ? $data['meeting_url'] : $item->meeting_url,
            'blocked_by_id' => array_key_exists('blocked_by_id', $data) ? $data['blocked_by_id'] : $item->blocked_by_id,
            'checklist' => array_key_exists('checklist', $data) ? $data['checklist'] : $item->checklist,
            'recurrence_rule' => array_key_exists('recurrence_rule', $data) ? $data['recurrence_rule'] : $item->recurrence_rule,
            'recurrence_until' => array_key_exists('recurrence_until', $data) ? $data['recurrence_until'] : $item->recurrence_until,
            'recurrence_count' => array_key_exists('recurrence_count', $data) ? $data['recurrence_count'] : $item->recurrence_count,
            'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $item->department_id,
        ]);

        if ($related !== null) {
            $item->related_type = $related['type'];
            $item->related_id = $related['id'];
            $item->related_label = $related['label'];
            $item->related_href = $related['href'];
        }

        if (($data['status'] ?? null) === CalendarItemStatus::Completed->value) {
            $item->completed_at = now();
        }

        $item->save();

        if (array_key_exists('assignee_ids', $data)) {
            $assigneeIds = $this->normalizeAssigneeIds($actor, $data['assignee_ids'] ?? []);
            $item->assignees()->sync($assigneeIds);
            $newAssignees = array_values(array_diff($assigneeIds, $previousAssignees));
            if ($newAssignees !== []) {
                $this->notifyAssignees($item, $newAssignees, $actor, 'assigned');
            }
        }

        if (array_key_exists('reminders', $data)) {
            $this->syncReminders($item, $data['reminders'] ?? []);
        }

        $this->notifyAssignees($item, $item->assignees()->pluck('users.id')->all(), $actor, 'updated');
        $this->activityLogger->log($item, $actor, 'updated', 'تم تحديث عنصر التقويم');
        $this->projectActivities->recordFromCalendarItem($item, $actor, 'updated');

        $fresh = $item->fresh(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files']);

        if (
            ! TaskCalendarSyncContext::isSyncing()
            && ($data['status'] ?? null) === CalendarItemStatus::Completed->value
        ) {
            app(TaskCalendarLinkService::class)->syncCompletionFromCalendar($fresh);
        }

        return $fresh;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRecurring(User $actor, CalendarItem $master, array $data, string $scope): CalendarItem
    {
        return DB::transaction(function () use ($actor, $master, $data, $scope): CalendarItem {
            $occurrenceAt = isset($data['occurrence_at'])
                ? Carbon::parse((string) $data['occurrence_at'])
                : ($master->starts_at?->copy() ?? now());

            if ($scope === 'this') {
                $this->addException($master, $occurrenceAt->toDateString());
                $clone = $this->cloneAsStandalone($actor, $master, $data, $occurrenceAt);
                $this->activityLogger->log($master, $actor, 'exception_created', 'edited single occurrence');
                $this->activityLogger->log($clone, $actor, 'created', 'created exception from recurring series');

                return $clone;
            }

            if ($scope === 'future') {
                $originalUntil = $master->recurrence_until?->copy();
                $originalCount = $master->recurrence_count;
                $oldUntil = $occurrenceAt->copy()->startOfDay()->subSecond();
                $master->recurrence_until = $oldUntil;
                $master->save();

                $data['starts_at'] = $data['starts_at'] ?? $occurrenceAt->toIso8601String();
                $data['recurrence_until'] = array_key_exists('recurrence_until', $data)
                    ? $data['recurrence_until']
                    : $originalUntil?->toIso8601String();
                $data['recurrence_count'] = array_key_exists('recurrence_count', $data)
                    ? $data['recurrence_count']
                    : $originalCount;

                $newMaster = $this->cloneAsStandalone($actor, $master, $data, $occurrenceAt, keepRecurrence: true);
                $this->activityLogger->log($master, $actor, 'series_split', 'تم تقسيم السلسلة المتكررة', [
                    'old_until' => $oldUntil->toIso8601String(),
                    'new_starts' => $newMaster->starts_at?->toIso8601String(),
                ]);

                return $newMaster;
            }

            unset($data['scope'], $data['occurrence_at']);

            return $this->applyDirectUpdate($actor, $master, $data);
        });
    }

    public function deleteRecurring(User $actor, CalendarItem $item, string $scope = 'all', ?Carbon $occurrenceAt = null): void
    {
        DB::transaction(function () use ($actor, $item, $scope, $occurrenceAt): void {
            if (! $item->isRecurringMaster() || $scope === 'all') {
                $this->delete($actor, $item);

                return;
            }

            $at = $occurrenceAt ?? $item->starts_at?->copy() ?? now();

            if ($scope === 'this') {
                $this->addException($item, $at->toDateString());
                $this->activityLogger->log($item, $actor, 'exception_deleted', 'تم حذف تكرار واحد من السلسلة');

                return;
            }

            if ($scope === 'future') {
                $item->recurrence_until = $at->copy()->startOfDay()->subSecond();
                $item->save();
                $this->activityLogger->log($item, $actor, 'series_truncated', 'تم إنهاء التكرار من هذا التاريخ');
            }
        });
    }

    public function reschedule(User $actor, CalendarItem $item, string $startsAt, ?string $endsAt = null, ?string $scope = null, ?string $occurrenceAt = null): CalendarItem
    {
        $data = [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'scope' => $scope,
            'occurrence_at' => $occurrenceAt,
        ];

        if ($item->isRecurringMaster() && in_array($scope, ['this', 'future', 'all'], true)) {
            return $this->updateRecurring($actor, $item, $data, $scope);
        }

        $item = $this->update($actor, $item, [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $this->activityLogger->log($item, $actor, 'rescheduled', 'تم إعادة جدولة العنصر');

        return $item;
    }

    public function duplicate(User $actor, CalendarItem $item, string $startsAt): CalendarItem
    {
        $starts = Carbon::parse($startsAt);
        $duration = $item->ends_at && $item->starts_at
            ? $item->starts_at->diffInSeconds($item->ends_at)
            : 3600;

        return $this->create($actor, [
            'title' => $item->title,
            'description' => $item->description,
            'type' => $item->type instanceof CalendarItemType ? $item->type->value : $item->type,
            'status' => CalendarItemStatus::Scheduled->value,
            'priority' => $item->priority instanceof CalendarItemPriority ? $item->priority->value : $item->priority,
            'visibility' => $item->visibility instanceof CalendarVisibility ? $item->visibility->value : $item->visibility,
            'source' => CalendarSource::Manual->value,
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $starts->copy()->addSeconds($duration)->toIso8601String(),
            'all_day' => $item->all_day,
            'assignee_ids' => $item->assignees()->pluck('users.id')->all() ?: [$actor->id],
            'related_type' => $item->related_type,
            'related_id' => $item->related_id,
            'reminders' => $item->reminders->map(fn (CalendarReminder $r) => $r->offset instanceof CalendarReminderOffset ? $r->offset->value : $r->offset)->all(),
            'location' => $item->location,
            'meeting_url' => $item->meeting_url,
            'checklist' => $item->checklist,
            'blocked_by_id' => $item->blocked_by_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CalendarItem>
     */
    public function tasksList(User $actor, array $filters): LengthAwarePaginator
    {
        $query = CalendarItem::query()
            ->with(['creator:id,name', 'assignees:id,name,role', 'reminders'])
            ->withCount(['comments', 'files'])
            ->where('type', CalendarItemType::Task->value);

        $this->applyVisibilityScope($query, $actor, $filters['scope'] ?? 'mine');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['assignee_id'])) {
            $assigneeId = (int) $filters['assignee_id'];
            $query->whereHas('assignees', fn (Builder $q) => $q->where('users.id', $assigneeId));
        }
        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }
        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        return $query->orderBy('starts_at')->paginate(max(1, min((int) ($filters['per_page'] ?? 20), 100)));
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $changes
     * @return array{items: list<array<string, mixed>>, skipped_ids: list<int>}
     */
    public function bulkUpdate(User $actor, array $ids, array $changes): array
    {
        $serialized = [];
        $skipped = [];

        foreach ($ids as $rawId) {
            $itemId = CalendarOccurrenceReference::parse($rawId)->itemId();
            $item = CalendarItem::query()->find($itemId);

            if ($item === null) {
                $skipped[] = $itemId;

                continue;
            }

            if (! $actor->can('update', $item)) {
                $skipped[] = $itemId;

                continue;
            }

            if (($changes['status'] ?? null) === CalendarItemStatus::Completed->value) {
                $serialized[] = $this->serialize($this->complete($actor, $item));

                continue;
            }

            $serialized[] = $this->serialize($this->update($actor, $item, $changes));
        }

        return [
            'items' => $serialized,
            'skipped_ids' => array_values(array_unique($skipped)),
        ];
    }

    /**
     * Workload counts DB rows only (virtual recurrence occurrences are not expanded).
     *
     * @return array{from: string, to: string, by_assignee: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function workload(User $actor, Carbon $from, Carbon $to, ?int $departmentId = null): array
    {
        $query = CalendarItem::query()
            ->with('assignees:id,name')
            ->whereBetween('starts_at', [$from, $to])
            ->whereNotIn('status', [
                CalendarItemStatus::Cancelled->value,
                CalendarItemStatus::Completed->value,
            ]);

        $this->applyVisibilityScope($query, $actor, 'team');

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        $counts = [];
        $totals = ['items' => 0, 'tasks' => 0, 'meetings' => 0, 'overdue' => 0];

        foreach ($query->get() as $item) {
            $totals['items']++;
            $isOpenTask = $item->type === CalendarItemType::Task;
            if ($isOpenTask) {
                $totals['tasks']++;
            }
            if (in_array($item->type, [CalendarItemType::Meeting, CalendarItemType::Appointment, CalendarItemType::Call], true)) {
                $totals['meetings']++;
            }
            $isOverdue = $item->status === CalendarItemStatus::Overdue;
            if ($isOverdue) {
                $totals['overdue']++;
            }
            $isUrgent = in_array(
                $item->priority instanceof CalendarItemPriority ? $item->priority->value : $item->priority,
                [CalendarItemPriority::High->value, CalendarItemPriority::Urgent->value],
                true,
            );

            $assignees = $item->assignees;
            if ($assignees->isEmpty()) {
                $key = 'unassigned';
                $counts[$key] ??= [
                    'id' => null,
                    'name' => 'غير معيّن',
                    'count' => 0,
                    'tasks' => 0,
                    'open_tasks' => 0,
                    'urgent' => 0,
                    'overdue' => 0,
                ];
                $counts[$key]['count']++;
                if ($isOpenTask) {
                    $counts[$key]['tasks']++;
                    $counts[$key]['open_tasks']++;
                }
                if ($isUrgent) {
                    $counts[$key]['urgent']++;
                }
                if ($isOverdue) {
                    $counts[$key]['overdue']++;
                }

                continue;
            }

            foreach ($assignees as $user) {
                $key = (string) $user->id;
                $counts[$key] ??= [
                    'id' => $user->id,
                    'name' => $user->name,
                    'count' => 0,
                    'tasks' => 0,
                    'open_tasks' => 0,
                    'urgent' => 0,
                    'overdue' => 0,
                ];
                $counts[$key]['count']++;
                if ($isOpenTask) {
                    $counts[$key]['tasks']++;
                    $counts[$key]['open_tasks']++;
                }
                if ($isUrgent) {
                    $counts[$key]['urgent']++;
                }
                if ($isOverdue) {
                    $counts[$key]['overdue']++;
                }
            }
        }

        $byAssignee = [];
        foreach ($counts as $row) {
            $level = CalendarWorkloadRules::level(
                (int) $row['open_tasks'],
                (int) $row['urgent'],
                (int) $row['overdue'],
            );
            $row['level'] = $level;
            $row['level_label'] = CalendarWorkloadRules::levelLabelAr($level);
            unset($row['open_tasks'], $row['urgent']);
            $byAssignee[] = $row;
        }

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'by_assignee' => $byAssignee,
            'totals' => $totals,
        ];
    }

    /**
     * @param  list<int>  $assigneeIds
     * @return list<array<string, mixed>>
     */
    public function conflicts(User $actor, string $startsAt, string $endsAt, array $assigneeIds, ?int $excludeId = null): array
    {
        $starts = Carbon::parse($startsAt);
        $ends = Carbon::parse($endsAt);
        $assigneeIds = array_values(array_unique(array_map('intval', $assigneeIds)));

        $query = CalendarItem::query()
            ->with(['assignees:id,name', 'creator:id,name'])
            ->whereIn('type', CalendarWorkloadRules::timeBlockingTypes())
            ->whereNotIn('status', [CalendarItemStatus::Cancelled->value, CalendarItemStatus::Completed->value])
            ->where('starts_at', '<', $ends)
            ->where(function (Builder $builder) use ($starts): void {
                $builder->whereNull('ends_at')
                    ->where('starts_at', '>', $starts->copy()->subHours(12))
                    ->orWhere(function (Builder $inner) use ($starts): void {
                        $inner->whereNotNull('ends_at')->where('ends_at', '>', $starts);
                    });
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($assigneeIds !== []) {
            $query->whereHas('assignees', fn (Builder $q) => $q->whereIn('users.id', $assigneeIds));
        }

        $this->applyVisibilityScope($query, $actor, 'team');

        return $query->orderBy('starts_at')->get()->map(fn (CalendarItem $item) => $this->serialize($item))->all();
    }

    /**
     * @param  list<array<string, mixed>>  $checklist
     */
    public function syncChecklist(User $actor, CalendarItem $item, array $checklist): CalendarItem
    {
        $normalized = [];
        foreach ($checklist as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized[] = [
                'id' => (string) ($row['id'] ?? uniqid('c_', true)),
                'text' => (string) ($row['text'] ?? ''),
                'done' => (bool) ($row['done'] ?? false),
            ];
        }

        $item->checklist = $normalized;
        $item->save();
        $this->activityLogger->log($item, $actor, 'checklist_updated', 'تم تحديث قائمة المهام الفرعية');

        return $item->fresh(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files']);
    }

    public function complete(User $actor, CalendarItem $item): CalendarItem
    {
        $item->update([
            'status' => CalendarItemStatus::Completed->value,
            'completed_at' => now(),
        ]);

        $this->notifyAssignees($item, $item->assignees()->pluck('users.id')->all(), $actor, 'completed');
        $this->activityLogger->log($item, $actor, 'completed', 'تم إكمال عنصر التقويم');
        $this->projectActivities->recordFromCalendarItem($item, $actor, 'completed');

        $fresh = $item->fresh(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files']);
        $this->dispatchTaskCompletedHook($actor, $fresh);

        if (! TaskCalendarSyncContext::isSyncing()) {
            app(TaskCalendarLinkService::class)->syncCompletionFromCalendar($fresh);
        }

        return $fresh;
    }

    public function delete(User $actor, CalendarItem $item): void
    {
        $assigneeIds = $item->assignees()->pluck('users.id')->all();
        $this->activityLogger->log($item, $actor, 'deleted', 'تم حذف عنصر التقويم');
        $item->delete();
        $this->notifyAssignees($item, $assigneeIds, $actor, 'cancelled');
    }

    public function markOverdue(): int
    {
        $query = CalendarItem::query()
            ->whereIn('status', [CalendarItemStatus::Scheduled->value, CalendarItemStatus::InProgress->value])
            ->where('starts_at', '<', now())
            ->whereIn('type', [CalendarItemType::Task->value, CalendarItemType::Deadline->value, CalendarItemType::Reminder->value]);

        $ids = (clone $query)->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }

        $count = $query->update(['status' => CalendarItemStatus::Overdue->value]);

        try {
            $engine = app(WorkflowAutomationEngine::class);
            $day = now()->toDateString();
            foreach ($ids as $id) {
                $item = CalendarItem::query()->find($id);
                if ($item === null) {
                    continue;
                }

                $engine->dispatch('calendar.task.overdue', [
                    'source_type' => 'calendar_item',
                    'source_id' => (int) $item->id,
                    'actor_id' => $item->created_by,
                    'title' => $item->title,
                    'related_type' => $item->related_type,
                    'related_id' => $item->related_id,
                    'department_id' => $item->department_id,
                    'payload' => [
                        'calendar_item_id' => $item->id,
                        'status' => CalendarItemStatus::Overdue->value,
                    ],
                ], 'day:'.$day);
            }
        } catch (\Throwable) {
            // Workflow hooks must never break calendar listing.
        }

        return $count;
    }

    /**
     * @return list<array{id: int, name: string, role: string|null}>
     */
    public function assignableUsers(User $actor): array
    {
        $query = User::query()
            ->where('is_active', true)
            ->whereIn('role', UserRole::employeeValues())
            ->orderBy('name');

        if ($actor->role === UserRole::Owner || ($actor->role instanceof UserRole && $actor->role->canManageWorkCalendar())) {
            // full staff list
        } else {
            $query->where('id', $actor->id);
        }

        $users = $query->get(['id', 'name', 'role']);

        if ($actor->role === UserRole::Owner) {
            $users = User::query()
                ->where('is_active', true)
                ->where(function (Builder $builder) use ($actor): void {
                    $builder->whereIn('role', UserRole::employeeValues())
                        ->orWhere('id', $actor->id);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'role']);
        }

        return $users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role instanceof UserRole ? $user->role->value : (string) $user->role,
        ])->all();
    }

    /**
     * @return array<string, int>
     */
    public function summaryFor(User $actor, string $scope): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $base = CalendarItem::query();
        $this->applyVisibilityScope($base, $actor, $scope);

        $open = (clone $base)->whereNotIn('status', [
            CalendarItemStatus::Completed->value,
            CalendarItemStatus::Cancelled->value,
        ]);

        return [
            'today_tasks' => (clone $open)
                ->where('type', CalendarItemType::Task->value)
                ->whereBetween('starts_at', [$todayStart, $todayEnd])
                ->count(),
            'today_meetings' => (clone $open)
                ->whereIn('type', [CalendarItemType::Meeting->value, CalendarItemType::Appointment->value, CalendarItemType::Call->value])
                ->whereBetween('starts_at', [$todayStart, $todayEnd])
                ->count(),
            'overdue' => (clone $base)
                ->where('status', CalendarItemStatus::Overdue->value)
                ->count(),
            'upcoming' => (clone $open)
                ->where('starts_at', '>', $todayEnd)
                ->where('starts_at', '<=', now()->addDays(7))
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(CalendarItem $item, bool $includeActivities = false): array
    {
        $payload = [
            'id' => $item->id,
            'is_linked' => false,
            'is_occurrence' => false,
            'occurrence_at' => $item->recurrence_instance_at?->toIso8601String(),
            'title' => $item->title,
            'description' => $item->description,
            'type' => $item->type instanceof CalendarItemType ? $item->type->value : $item->type,
            'status' => $item->status instanceof CalendarItemStatus ? $item->status->value : $item->status,
            'priority' => $item->priority instanceof CalendarItemPriority ? $item->priority->value : $item->priority,
            'visibility' => $item->visibility instanceof CalendarVisibility ? $item->visibility->value : $item->visibility,
            'source' => $item->source instanceof CalendarSource ? $item->source->value : $item->source,
            'starts_at' => $item->starts_at?->toIso8601String(),
            'ends_at' => $item->ends_at?->toIso8601String(),
            'all_day' => (bool) $item->all_day,
            'created_by' => $item->created_by,
            'creator' => $item->creator ? [
                'id' => $item->creator->id,
                'name' => $item->creator->name,
            ] : null,
            'assignees' => $item->relationLoaded('assignees')
                ? $item->assignees->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
                ])->values()->all()
                : [],
            'reminders' => $item->relationLoaded('reminders')
                ? $item->reminders->map(fn (CalendarReminder $reminder) => [
                    'id' => $reminder->id,
                    'offset' => $reminder->offset instanceof CalendarReminderOffset ? $reminder->offset->value : $reminder->offset,
                    'remind_at' => $reminder->remind_at?->toIso8601String(),
                    'sent_at' => $reminder->sent_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'related_type' => $item->related_type,
            'related_id' => $item->related_id,
            'related_label' => $item->related_label,
            'related_href' => $item->related_href,
            'completed_at' => $item->completed_at?->toIso8601String(),
            'can_edit' => true,
            'location' => $item->location,
            'meeting_url' => $item->meeting_url,
            'blocked_by_id' => $item->blocked_by_id,
            'checklist' => $item->checklist ?? [],
            'recurrence_rule' => $item->recurrence_rule,
            'recurrence_until' => $item->recurrence_until?->toIso8601String(),
            'recurrence_count' => $item->recurrence_count,
            'recurrence_parent_id' => $item->recurrence_parent_id,
            'recurrence_exceptions' => $item->recurrence_exceptions ?? [],
            'comments_count' => (int) ($item->comments_count ?? 0),
            'attachments_count' => (int) ($item->files_count ?? 0),
            'department_id' => $item->department_id,
        ];

        if ($includeActivities && $item->relationLoaded('activities')) {
            $payload['activities'] = $item->activities->take(20)->map(fn ($activity) => [
                'id' => $activity->id,
                'action' => $activity->action,
                'summary' => $activity->summary,
                'meta' => $activity->meta,
                'user_id' => $activity->user_id,
                'created_at' => $activity->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOccurrence(CalendarItem $master, Carbon $occurrence, int $durationSeconds): array
    {
        $base = $this->serialize($master);
        $ends = $occurrence->copy()->addSeconds(max(0, $durationSeconds));

        $base['id'] = CalendarOccurrenceReference::forOccurrence((int) $master->id, $occurrence)->toListId();
        $base['is_occurrence'] = true;
        $base['occurrence_at'] = $occurrence->toIso8601String();
        $base['starts_at'] = $occurrence->toIso8601String();
        $base['ends_at'] = $ends->toIso8601String();
        $base['master_id'] = $master->id;
        $base['can_edit'] = true;

        return $base;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cloneAsStandalone(User $actor, CalendarItem $master, array $data, Carbon $occurrenceAt, bool $keepRecurrence = false): CalendarItem
    {
        $duration = $master->ends_at && $master->starts_at
            ? $master->starts_at->diffInSeconds($master->ends_at)
            : 3600;
        $starts = isset($data['starts_at']) ? Carbon::parse((string) $data['starts_at']) : $occurrenceAt->copy();
        $ends = array_key_exists('ends_at', $data)
            ? ($data['ends_at'] !== null ? Carbon::parse((string) $data['ends_at']) : null)
            : $starts->copy()->addSeconds($duration);

        $payload = [
            'title' => $data['title'] ?? $master->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $master->description,
            'type' => $data['type'] ?? ($master->type instanceof CalendarItemType ? $master->type->value : $master->type),
            'status' => $data['status'] ?? ($master->status instanceof CalendarItemStatus ? $master->status->value : $master->status),
            'priority' => $data['priority'] ?? ($master->priority instanceof CalendarItemPriority ? $master->priority->value : $master->priority),
            'visibility' => $data['visibility'] ?? ($master->visibility instanceof CalendarVisibility ? $master->visibility->value : $master->visibility),
            'source' => $master->source instanceof CalendarSource ? $master->source->value : $master->source,
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends?->toIso8601String(),
            'all_day' => array_key_exists('all_day', $data) ? (bool) $data['all_day'] : $master->all_day,
            'assignee_ids' => $data['assignee_ids'] ?? $master->assignees()->pluck('users.id')->all(),
            'related_type' => $master->related_type,
            'related_id' => $master->related_id,
            'reminders' => $data['reminders'] ?? $master->reminders->map(fn (CalendarReminder $r) => $r->offset instanceof CalendarReminderOffset ? $r->offset->value : $r->offset)->all(),
            'location' => array_key_exists('location', $data) ? $data['location'] : $master->location,
            'meeting_url' => array_key_exists('meeting_url', $data) ? $data['meeting_url'] : $master->meeting_url,
            'checklist' => array_key_exists('checklist', $data) ? $data['checklist'] : $master->checklist,
            'blocked_by_id' => array_key_exists('blocked_by_id', $data) ? $data['blocked_by_id'] : $master->blocked_by_id,
        ];

        if ($keepRecurrence) {
            $payload['recurrence_rule'] = $data['recurrence_rule'] ?? $master->recurrence_rule;
            $payload['recurrence_until'] = array_key_exists('recurrence_until', $data)
                ? $data['recurrence_until']
                : $master->recurrence_until?->toIso8601String();
            $payload['recurrence_count'] = array_key_exists('recurrence_count', $data)
                ? $data['recurrence_count']
                : $master->recurrence_count;
        }

        $clone = $this->create($actor, $payload);
        if (! $keepRecurrence) {
            $clone->recurrence_parent_id = $master->id;
            $clone->recurrence_instance_at = $occurrenceAt;
            $clone->recurrence_rule = null;
            $clone->recurrence_until = null;
            $clone->recurrence_count = null;
            $clone->save();
        }

        return $clone->fresh(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files']);
    }

    private function addException(CalendarItem $master, string $date): void
    {
        $exceptions = $master->recurrence_exceptions ?? [];
        if (! in_array($date, $exceptions, true)) {
            $exceptions[] = $date;
        }
        $master->recurrence_exceptions = array_values($exceptions);
        $master->save();
    }

    /**
     * @param  Builder<CalendarItem>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyListFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (! empty($filters['assignee_id'])) {
            $assigneeId = (int) $filters['assignee_id'];
            $query->where(function (Builder $builder) use ($assigneeId): void {
                $builder->where('created_by', $assigneeId)
                    ->orWhereHas('assignees', fn (Builder $q) => $q->where('users.id', $assigneeId));
            });
        }
        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('related_label', 'like', $term);
            });
        }
        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }
    }

    /**
     * @return array{type: ?string, id: ?int, label: ?string, href: ?string}
     */
    private function resolveRelated(User $actor, ?string $type, ?int $id): array
    {
        if ($type === null || $type === '' || $id === null) {
            return ['type' => null, 'id' => null, 'label' => null, 'href' => null];
        }

        return match ($type) {
            'crm_lead' => $this->relatedFrom(
                CrmLead::query()->find($id),
                'crm_lead',
                fn (CrmLead $lead) => $lead->full_name.' ('.$lead->reference.')',
                $actor,
            ),
            'crm_contact' => $this->relatedFrom(
                CrmContact::query()->find($id),
                'crm_contact',
                fn (CrmContact $contact) => $contact->name,
                $actor,
            ),
            'crm_company' => $this->relatedFrom(
                CrmCompany::query()->find($id),
                'crm_company',
                fn (CrmCompany $company) => $company->name,
                $actor,
            ),
            'crm_opportunity' => $this->relatedFrom(
                CrmOpportunity::query()->find($id),
                'crm_opportunity',
                fn (CrmOpportunity $opportunity) => $opportunity->name ?? ('فرصة #'.$opportunity->id),
                $actor,
            ),
            'crm_quotation' => $this->relatedFrom(
                CrmQuotation::query()->find($id),
                'crm_quotation',
                fn (CrmQuotation $quotation) => $quotation->number ? (string) $quotation->number : ('عرض #'.$quotation->id),
                $actor,
            ),
            'crm_follow_up' => $this->relatedFrom(
                CrmFollowUp::query()->with('lead')->find($id),
                'crm_follow_up',
                fn (CrmFollowUp $followUp) => 'متابعة — '.($followUp->lead?->full_name ?? '#'.$followUp->id),
                $actor,
            ),
            'order' => $this->relatedFrom(
                Order::query()->find($id),
                'order',
                fn (Order $order) => 'طلب #'.$order->id,
                $actor,
            ),
            'printing_request' => $this->relatedFrom(
                PrintingRequest::query()->find($id),
                'printing_request',
                fn (PrintingRequest $request) => 'طباعة #'.$request->id,
                $actor,
            ),
            'project' => $this->relatedFrom(
                Project::query()->find($id),
                'project',
                fn (Project $project) => $project->title ?? ('مشروع #'.$project->id),
                $actor,
            ),
            'workspace_task' => $this->relatedWorkspaceTask($actor, $id),
            'supplier' => $this->relatedFrom(
                Supplier::query()->find($id),
                'supplier',
                fn (Supplier $supplier) => $supplier->name,
                $actor,
            ),
            'payment' => $this->relatedFrom(
                Payment::query()->find($id),
                'payment',
                fn (Payment $payment) => 'دفعة #'.$payment->id,
                $actor,
            ),
            'employee' => $this->relatedFrom(
                User::query()->whereIn('role', UserRole::employeeValues())->find($id),
                'employee',
                fn (User $user) => $user->name,
                $actor,
            ),
            default => throw ValidationException::withMessages([
                'related_type' => 'نوع السجل المرتبط غير مدعوم.',
            ]),
        };
    }

    /**
     * Safe read of a workspace Task when the actor can view the project/task.
     *
     * @return array{type: string, id: int, label: string, href: ?string}
     */
    private function relatedWorkspaceTask(User $actor, int $id): array
    {
        $task = Task::query()->with('project')->find($id);
        if ($task === null) {
            throw ValidationException::withMessages([
                'related_id' => 'السجل المرتبط غير موجود.',
            ]);
        }

        $canView = (int) $task->assigned_to === (int) $actor->id
            || (int) $task->created_by === (int) $actor->id
            || ($actor->role === UserRole::Owner)
            || ($actor->role === UserRole::AdminManager)
            || ($task->project !== null && (int) $task->project->account_manager_id === (int) $actor->id)
            || ($actor->role instanceof UserRole && $actor->role->canOverseeProjects());

        if (! $canView) {
            throw ValidationException::withMessages([
                'related_id' => 'السجل المرتبط غير موجود.',
            ]);
        }

        return [
            'type' => 'workspace_task',
            'id' => (int) $task->id,
            'label' => $task->title,
            'href' => $this->relatedUrls->resolve($actor, 'workspace_task', (int) $task->id),
        ];
    }

    /**
     * @template T of object
     *
     * @param  T|null  $model
     * @param  callable(T): string  $label
     * @return array{type: string, id: int, label: string, href: ?string}
     */
    private function relatedFrom(?object $model, string $type, callable $label, User $actor): array
    {
        if ($model === null) {
            throw ValidationException::withMessages([
                'related_id' => 'السجل المرتبط غير موجود.',
            ]);
        }

        return [
            'type' => $type,
            'id' => (int) $model->id,
            'label' => $label($model),
            'href' => $this->relatedUrls->resolve($actor, $type, (int) $model->id),
        ];
    }

    /**
     * @param  list<int|string>|mixed  $ids
     * @return list<int>
     */
    private function normalizeAssigneeIds(User $actor, mixed $ids): array
    {
        $ids = is_array($ids) ? $ids : [];
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            $ids = [$actor->id];
        }

        $allowed = User::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->where(function (Builder $builder) use ($actor): void {
                $builder->whereIn('role', UserRole::employeeValues())
                    ->orWhere('id', $actor->id)
                    ->orWhere('role', UserRole::Owner->value);
            })
            ->pluck('id')
            ->all();

        if ($allowed === []) {
            throw ValidationException::withMessages([
                'assignee_ids' => 'يجب اختيار مسؤول صالح.',
            ]);
        }

        return array_values(array_map('intval', $allowed));
    }

    /**
     * @param  list<string>|mixed  $offsets
     */
    private function syncReminders(CalendarItem $item, mixed $offsets): void
    {
        $item->reminders()->delete();
        $offsets = is_array($offsets) ? $offsets : [];

        foreach ($offsets as $offsetValue) {
            $offset = CalendarReminderOffset::tryFrom((string) $offsetValue);
            if ($offset === null) {
                continue;
            }

            CalendarReminder::query()->create([
                'calendar_item_id' => $item->id,
                'offset' => $offset->value,
                'remind_at' => $item->starts_at->copy()->subMinutes($offset->minutesBefore()),
            ]);
        }
    }

    /**
     * @param  list<int>  $assigneeIds
     */
    private function notifyAssignees(CalendarItem $item, array $assigneeIds, User $actor, string $action): void
    {
        $recipients = User::query()
            ->whereIn('id', $assigneeIds)
            ->where('is_active', true)
            ->where('id', '!=', $actor->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $titles = [
            'assigned' => 'تم تعيين عنصر تقويم لك',
            'updated' => 'تم تحديث عنصر في التقويم',
            'completed' => 'تم إكمال عنصر في التقويم',
            'cancelled' => 'تم إلغاء عنصر في التقويم',
        ];

        $notifier = app(OperationalNotifier::class);

        foreach ($recipients as $recipient) {
            $notifier->notify($recipient, new CalendarNotification([
                'type' => 'calendar_'.$action,
                'title' => $titles[$action] ?? 'تحديث التقويم',
                'message' => $item->title,
                'href' => $this->calendarHrefFor($recipient, (int) $item->id),
                'calendar_item_id' => $item->id,
            ]), 'operational');
        }
    }

    private function dispatchTaskCreatedHook(User $actor, CalendarItem $item): void
    {
        $type = $item->type instanceof CalendarItemType ? $item->type : CalendarItemType::tryFrom((string) $item->type);
        if ($type !== CalendarItemType::Task) {
            return;
        }

        try {
            $depth = (int) AutomationContext::get('automation_depth', 0);
            $chain = AutomationContext::get('trigger_chain', []);
            app(WorkflowAutomationEngine::class)->dispatch('calendar.task.created', [
                'source_type' => 'calendar_item',
                'source_id' => (int) $item->id,
                'actor_id' => $actor->id,
                'title' => $item->title,
                'related_type' => $item->related_type,
                'related_id' => $item->related_id,
                'department_id' => $item->department_id,
                'assignee_ids' => $item->assignees->pluck('id')->all(),
                'automation_depth' => $depth,
                'origin_run_id' => AutomationContext::get('origin_run_id'),
                'trigger_chain' => is_array($chain) ? $chain : [],
                'payload' => [
                    'calendar_item_id' => $item->id,
                    'type' => CalendarItemType::Task->value,
                ],
            ]);
        } catch (\Throwable) {
            // Workflow hooks must never break calendar creation.
        }
    }

    private function dispatchTaskCompletedHook(User $actor, CalendarItem $item): void
    {
        $type = $item->type instanceof CalendarItemType ? $item->type : CalendarItemType::tryFrom((string) $item->type);
        if ($type !== CalendarItemType::Task) {
            return;
        }

        try {
            app(WorkflowAutomationEngine::class)->dispatch('calendar.task.completed', [
                'source_type' => 'calendar_item',
                'source_id' => (int) $item->id,
                'actor_id' => $actor->id,
                'title' => $item->title,
                'related_type' => $item->related_type,
                'related_id' => $item->related_id,
                'department_id' => $item->department_id,
                'payload' => [
                    'calendar_item_id' => $item->id,
                    'status' => CalendarItemStatus::Completed->value,
                ],
            ]);
        } catch (\Throwable) {
            // Workflow hooks must never break calendar completion.
        }
    }

    private function calendarHrefFor(User $user, int $itemId): string
    {
        if ($user->role === UserRole::Owner) {
            return '/owner/calendar?item='.$itemId;
        }

        if ($user->role instanceof UserRole && $user->role->canAccessCrm()) {
            return '/crm/work-calendar?item='.$itemId;
        }

        return '/workspace/calendar?item='.$itemId;
    }

    /**
     * @param  Builder<CalendarItem>  $query
     */
    private function applyVisibilityScope(Builder $query, User $actor, string $scope): void
    {
        $canTeam = $actor->role instanceof UserRole && $actor->role->canViewTeamCalendar();

        if ($scope === 'team' && $canTeam) {
            return;
        }

        $query->where(function (Builder $builder) use ($actor): void {
            $builder->where('created_by', $actor->id)
                ->orWhereHas('assignees', fn (Builder $q) => $q->where('users.id', $actor->id));
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function mergeCrmLinkedEvents(User $actor, Carbon $from, Carbon $to, array $items, string $scope): array
    {
        if (! ($actor->role instanceof UserRole && $actor->role->canAccessCrm())) {
            return $items;
        }

        $crmEvents = $this->crmCalendar->events($actor, $from->toDateString(), $to->toDateString());
        $existingFollowUps = collect($items)
            ->filter(fn (array $item) => ($item['related_type'] ?? null) === 'crm_follow_up')
            ->pluck('related_id')
            ->filter()
            ->all();

        foreach ($crmEvents as $event) {
            if (($event['related_type'] ?? null) === 'crm_follow_up' && in_array($event['related_id'] ?? null, $existingFollowUps, true)) {
                continue;
            }

            $items[] = [
                'id' => $event['id'],
                'is_linked' => true,
                'is_occurrence' => false,
                'occurrence_at' => null,
                'title' => $event['title'],
                'description' => null,
                'type' => match ($event['type'] ?? '') {
                    'follow_up' => CalendarItemType::FollowUp->value,
                    'activity' => CalendarItemType::Meeting->value,
                    'expected_close' => CalendarItemType::Deadline->value,
                    default => CalendarItemType::Other->value,
                },
                'status' => CalendarItemStatus::Scheduled->value,
                'priority' => CalendarItemPriority::Medium->value,
                'visibility' => CalendarVisibility::Team->value,
                'source' => CalendarSource::Crm->value,
                'starts_at' => $event['starts_at'],
                'ends_at' => null,
                'all_day' => ($event['type'] ?? '') === 'expected_close',
                'created_by' => null,
                'creator' => null,
                'assignees' => [],
                'reminders' => [],
                'related_type' => $event['related_type'] ?? null,
                'related_id' => $event['related_id'] ?? null,
                'related_label' => $event['title'] ?? null,
                'related_href' => $event['href'] ?? null,
                'completed_at' => null,
                'can_edit' => false,
                'location' => null,
                'meeting_url' => null,
                'checklist' => [],
                'recurrence_rule' => null,
                'recurrence_until' => null,
                'recurrence_count' => null,
                'comments_count' => 0,
                'attachments_count' => 0,
            ];
        }

        return $items;
    }
}
