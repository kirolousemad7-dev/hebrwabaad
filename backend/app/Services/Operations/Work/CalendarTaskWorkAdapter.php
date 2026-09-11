<?php

namespace App\Services\Operations\Work;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemStatus;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\User;
use App\Services\Calendar\CalendarService;
use App\Support\Operations\UnifiedWorkItem;
use App\Support\Operations\UnifiedWorkReference;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CalendarTaskWorkAdapter
{
    public function __construct(
        private readonly CalendarService $calendar,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toItem(CalendarItem $item, User $actor, ?Carbon $occurrenceAt = null): array
    {
        $item->loadMissing(['assignees:id,name,department_id', 'creator:id,name', 'blockedBy:id,title,status']);

        $statusValue = $item->status instanceof CalendarItemStatus ? $item->status->value : (string) $item->status;
        $priorityValue = $item->priority instanceof CalendarItemPriority ? $item->priority->value : (string) $item->priority;
        $isCompleted = $statusValue === CalendarItemStatus::Completed->value;
        $isOverdue = $statusValue === CalendarItemStatus::Overdue->value
            || (
                ! $isCompleted
                && $statusValue !== CalendarItemStatus::Cancelled->value
                && $item->starts_at !== null
                && $item->starts_at->isPast()
            );

        $blocker = $item->blockedBy;
        $blockerStatus = $blocker?->status instanceof CalendarItemStatus
            ? $blocker->status->value
            : ($blocker?->status !== null ? (string) $blocker->status : null);
        $isBlocked = $blocker !== null && $blockerStatus !== CalendarItemStatus::Completed->value;

        $occ = $occurrenceAt
            ?? ($item->resolved_occurrence_at ? Carbon::parse($item->resolved_occurrence_at) : null);
        $ref = UnifiedWorkReference::forCalendar((int) $item->id, $occ);

        $canUpdate = Gate::forUser($actor)->allows('update', $item);
        $canComplete = Gate::forUser($actor)->allows('complete', $item);
        $canAssign = Gate::forUser($actor)->allows('assign', CalendarItem::class)
            || $canUpdate;

        $projectId = ($item->related_type === 'project' && $item->related_id)
            ? (int) $item->related_id
            : (($item->related_type === 'workspace_task') ? null : null);

        if ($item->related_type === 'workspace_task' && $item->related_id) {
            // Prefer project from linked task when loaded later; leave null here.
            $projectId = null;
        }

        $startsAt = $occ?->toIso8601String() ?? $item->starts_at?->toIso8601String();
        $dueAt = $occ?->toDateString() ?? $item->starts_at?->toDateString();

        return UnifiedWorkItem::fromArray([
            'id' => $ref->toString(),
            'source_type' => 'calendar',
            'source_id' => (int) $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'status' => $isOverdue && ! $isCompleted && $statusValue !== CalendarItemStatus::Cancelled->value
                ? 'overdue'
                : $this->normalizeStatus($statusValue),
            'priority' => $priorityValue,
            'due_at' => $dueAt,
            'starts_at' => $startsAt,
            'assignee_ids' => $item->assignees->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'department_id' => $item->department_id !== null ? (int) $item->department_id : null,
            'project_id' => $projectId,
            'related_type' => $item->related_type,
            'related_id' => $item->related_id !== null ? (int) $item->related_id : null,
            'created_by' => $item->created_by !== null ? (int) $item->created_by : null,
            'is_overdue' => $isOverdue && ! $isCompleted,
            'is_completed' => $isCompleted,
            'is_blocked' => $isBlocked,
            'blocked_label' => $isBlocked ? ($blocker?->title ?? 'محظورة') : null,
            'href' => $this->href($actor, (int) $item->id),
            'capabilities' => [
                'can_edit' => $canUpdate,
                'can_complete' => $canComplete && ! $isCompleted && ! $isBlocked,
                'can_assign' => $canAssign,
                'can_reschedule' => $canUpdate,
                'can_start' => $canUpdate
                    && ! $isCompleted
                    && $statusValue !== CalendarItemStatus::InProgress->value
                    && $statusValue !== CalendarItemStatus::Cancelled->value,
            ],
            'source_badge' => 'calendar',
        ])->toArray();
    }

    public function complete(User $actor, CalendarItem $item): array
    {
        $this->assertCanComplete($actor, $item);
        $item = $this->calendar->complete($actor, $item);

        return $this->toItem($item, $actor);
    }

    /**
     * @param  list<int>  $assigneeIds
     */
    public function assign(User $actor, CalendarItem $item, array $assigneeIds, ?string $scope = null, ?string $occurrenceAt = null): array
    {
        $this->assertCanUpdate($actor, $item);

        $payload = [
            'assignee_ids' => array_values(array_map('intval', $assigneeIds)),
        ];
        if ($scope !== null) {
            $payload['scope'] = $scope;
        }
        if ($occurrenceAt !== null) {
            $payload['occurrence_at'] = $occurrenceAt;
        }

        $item = $this->calendar->update($actor, $item, $payload);

        return $this->toItem($item, $actor, $occurrenceAt ? Carbon::parse($occurrenceAt) : null);
    }

    public function setPriority(User $actor, CalendarItem $item, string $priority, ?string $scope = null, ?string $occurrenceAt = null): array
    {
        $this->assertCanUpdate($actor, $item);

        $mapped = CalendarItemPriority::tryFrom(strtoupper($priority));
        if ($mapped === null) {
            throw ValidationException::withMessages([
                'priority' => ['Invalid priority.'],
            ]);
        }

        $payload = ['priority' => $mapped->value];
        if ($scope !== null) {
            $payload['scope'] = $scope;
        }
        if ($occurrenceAt !== null) {
            $payload['occurrence_at'] = $occurrenceAt;
        }

        $item = $this->calendar->update($actor, $item, $payload);

        return $this->toItem($item, $actor, $occurrenceAt ? Carbon::parse($occurrenceAt) : null);
    }

    public function setStatus(User $actor, CalendarItem $item, string $status, ?string $scope = null, ?string $occurrenceAt = null): array
    {
        $this->assertCanUpdate($actor, $item);

        if (strtolower($status) === 'completed') {
            return $this->complete($actor, $item);
        }

        $mapped = $this->denormalizeStatus($status);
        $payload = ['status' => $mapped->value];
        if ($scope !== null) {
            $payload['scope'] = $scope;
        }
        if ($occurrenceAt !== null) {
            $payload['occurrence_at'] = $occurrenceAt;
        }

        $item = $this->calendar->update($actor, $item, $payload);

        return $this->toItem($item, $actor, $occurrenceAt ? Carbon::parse($occurrenceAt) : null);
    }

    public function reschedule(User $actor, CalendarItem $item, string $startsAt, ?string $scope = null, ?string $occurrenceAt = null): array
    {
        $this->assertCanUpdate($actor, $item);

        $item = $this->calendar->reschedule(
            $actor,
            $item,
            $startsAt,
            null,
            $scope,
            $occurrenceAt ?? $item->resolved_occurrence_at,
        );

        return $this->toItem($item, $actor, $occurrenceAt ? Carbon::parse($occurrenceAt) : null);
    }

    public function start(User $actor, CalendarItem $item, ?string $scope = null, ?string $occurrenceAt = null): array
    {
        return $this->setStatus($actor, $item, 'in_progress', $scope, $occurrenceAt);
    }

    private function assertCanUpdate(User $actor, CalendarItem $item): void
    {
        if (! Gate::forUser($actor)->allows('update', $item)) {
            throw new AuthorizationException;
        }
    }

    private function assertCanComplete(User $actor, CalendarItem $item): void
    {
        if (! Gate::forUser($actor)->allows('complete', $item)) {
            throw new AuthorizationException;
        }
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            CalendarItemStatus::Scheduled->value => 'open',
            CalendarItemStatus::InProgress->value => 'in_progress',
            CalendarItemStatus::Completed->value => 'completed',
            CalendarItemStatus::Cancelled->value => 'cancelled',
            CalendarItemStatus::Overdue->value => 'overdue',
            default => 'open',
        };
    }

    private function denormalizeStatus(string $status): CalendarItemStatus
    {
        return match (strtolower($status)) {
            'open' => CalendarItemStatus::Scheduled,
            'in_progress' => CalendarItemStatus::InProgress,
            'review' => CalendarItemStatus::InProgress,
            'completed' => CalendarItemStatus::Completed,
            'cancelled' => CalendarItemStatus::Cancelled,
            'overdue' => CalendarItemStatus::Overdue,
            default => throw ValidationException::withMessages([
                'status' => ['Unsupported status for calendar tasks.'],
            ]),
        };
    }

    private function href(User $actor, int $itemId): string
    {
        return match (true) {
            $actor->role === UserRole::Owner => '/owner/calendar?item='.$itemId,
            $actor->role instanceof UserRole && $actor->role->canAccessCrm() => '/crm/work-calendar?item='.$itemId,
            default => '/workspace/calendar?item='.$itemId,
        };
    }
}
