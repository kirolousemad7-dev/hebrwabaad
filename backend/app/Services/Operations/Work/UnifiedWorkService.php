<?php

namespace App\Services\Operations\Work;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\Task;
use App\Models\User;
use App\Services\Calendar\RecurrenceExpander;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Unified read/query layer over workspace Task and CalendarItem (type=TASK).
 *
 * Drivers (config operations.unified_work_driver):
 * - sql: UNION ALL via UnifiedWorkQuery, then hydrate page rows through adapters.
 *        For buckets today|this_week|upcoming, also merges bounded RecurrenceExpander
 *        virtual occurrences (Phase 6K V2, max 100). Unconstrained all/completed lists
 *        stay persistent-only.
 * - php: bounded in-memory merge (Phase 4); also used as fallback; same windowed
 *        recurrence merge.
 *
 * Soft links are explicit only (TaskCalendarLinkService); no auto-create calendar for every task.
 * Legacy expand_occurrences / expand_recurrence forces the PHP path.
 *
 * Benchmark: php artisan operations:benchmark-unified-work
 */
class UnifiedWorkService
{
    public const MAX_RECURRENCE_OCCURRENCES = 100;

    /** @var list<string> */
    private const WINDOWED_BUCKETS = ['today', 'this_week', 'upcoming'];

    public function __construct(
        private readonly TaskWorkAdapter $taskAdapter,
        private readonly CalendarTaskWorkAdapter $calendarAdapter,
        private readonly UnifiedWorkQuery $unifiedWorkQuery,
        private readonly RecurrenceExpander $recurrenceExpander,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(User $actor, array $filters): array
    {
        if ($this->shouldUseSqlDriver($filters)) {
            try {
                return $this->listViaSql($actor, $filters);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->listViaPhp($actor, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function shouldUseSqlDriver(array $filters): bool
    {
        if (config('operations.unified_work_driver', 'sql') !== 'sql') {
            return false;
        }

        // Recurrence / virtual occurrence expansion is PHP-only.
        if (! empty($filters['expand_occurrences']) || ! empty($filters['expand_recurrence'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    private function listViaSql(User $actor, array $filters): array
    {
        $bucket = (string) ($filters['bucket'] ?? 'all');

        if ($this->isWindowedBucket($bucket)) {
            return $this->listWithWindowedRecurrence($actor, $filters, useSqlCandidates: true);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 50));

        $result = $this->unifiedWorkQuery->paginate($actor, $filters, $page, $perPage);
        $items = $this->hydrateRows($actor, $result['rows']);

        return [
            'items' => array_values($items),
            'meta' => $result['meta'],
        ];
    }

    /**
     * @param  list<object{source_type: string, source_id: int|string}>  $rows
     * @return list<array<string, mixed>>
     */
    private function hydrateRows(User $actor, array $rows): array
    {
        $taskIds = [];
        $calendarIds = [];

        foreach ($rows as $row) {
            if (($row->source_type ?? '') === 'task') {
                $taskIds[] = (int) $row->source_id;
            } elseif (($row->source_type ?? '') === 'calendar') {
                $calendarIds[] = (int) $row->source_id;
            }
        }

        $tasks = $taskIds === []
            ? collect()
            : Task::query()
                ->with(['assignee', 'creator', 'project'])
                ->whereIn('id', array_values(array_unique($taskIds)))
                ->get()
                ->keyBy('id');

        $calendars = $calendarIds === []
            ? collect()
            : CalendarItem::query()
                ->with(['assignees:id,name,department_id', 'creator:id,name', 'blockedBy:id,title,status'])
                ->whereIn('id', array_values(array_unique($calendarIds)))
                ->get()
                ->keyBy('id');

        $items = [];
        foreach ($rows as $row) {
            if (($row->source_type ?? '') === 'task') {
                $task = $tasks->get((int) $row->source_id);
                if ($task instanceof Task) {
                    $items[] = $this->taskAdapter->toItem($task, $actor);
                }

                continue;
            }

            if (($row->source_type ?? '') === 'calendar') {
                $calendarItem = $calendars->get((int) $row->source_id);
                if ($calendarItem instanceof CalendarItem) {
                    $items[] = $this->calendarAdapter->toItem($calendarItem, $actor);
                }
            }
        }

        return $items;
    }

    /**
     * Bounded PHP merge (Phase 4) — also the fallback when SQL is disabled or fails.
     *
     * @param  array<string, mixed>  $filters
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    private function listViaPhp(User $actor, array $filters): array
    {
        $bucket = (string) ($filters['bucket'] ?? 'all');

        if ($this->isWindowedBucket($bucket)) {
            return $this->listWithWindowedRecurrence($actor, $filters, useSqlCandidates: false);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 50));
        $fetchLimit = min(200, max(50, $page * $perPage * 3));

        $source = $filters['source'] ?? null;
        $items = [];

        if ($source !== 'calendar') {
            foreach ($this->fetchTasks($actor, $filters, $fetchLimit) as $task) {
                $items[] = $this->taskAdapter->toItem($task, $actor);
            }
        }

        if ($source !== 'task') {
            foreach ($this->fetchCalendarTasks($actor, $filters, $fetchLimit) as $calendarItem) {
                if ($calendarItem->isRecurringMaster()) {
                    continue;
                }
                $items[] = $this->calendarAdapter->toItem($calendarItem, $actor);
            }
        }

        $items = $this->dedupeWorkspaceTaskLinks($items);
        $items = $this->applyNormalizedFilters($items, $filters);
        $items = $this->sortItems($items, (string) ($filters['sort'] ?? 'overdue_first'));

        $total = count($items);
        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);

        return [
            'items' => array_values($slice),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * Persistent candidates + bounded virtual recurrence for today|this_week|upcoming.
     *
     * @param  array<string, mixed>  $filters
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    private function listWithWindowedRecurrence(User $actor, array $filters, bool $useSqlCandidates): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 50));
        $fetchLimit = min(200, max(50, $page * $perPage * 3));

        $source = $filters['source'] ?? null;
        $items = [];

        if ($useSqlCandidates) {
            $result = $this->unifiedWorkQuery->paginate($actor, $filters, 1, $fetchLimit);
            $items = $this->hydrateRows($actor, $result['rows']);
            $items = $this->stripRecurringMastersFromItems($items);
        } else {
            if ($source !== 'calendar') {
                foreach ($this->fetchTasks($actor, $filters, $fetchLimit) as $task) {
                    $items[] = $this->taskAdapter->toItem($task, $actor);
                }
            }

            if ($source !== 'task') {
                foreach ($this->fetchCalendarTasks($actor, $filters, $fetchLimit) as $calendarItem) {
                    if ($calendarItem->isRecurringMaster()) {
                        continue;
                    }
                    $items[] = $this->calendarAdapter->toItem($calendarItem, $actor);
                }
            }
        }

        if ($source !== 'task') {
            $items = array_merge($items, $this->expandWindowedRecurrence($actor, $filters));
        }

        $items = $this->dedupeWorkspaceTaskLinks($items);
        $items = $this->dedupeOccurrencesAgainstLinkedTasks($items);
        $items = $this->applyNormalizedFilters($items, $filters);
        $items = $this->sortItems($items, (string) ($filters['sort'] ?? 'overdue_first'));

        $total = count($items);
        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);

        return [
            'items' => array_values($slice),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            ],
        ];
    }

    private function isWindowedBucket(string $bucket): bool
    {
        return in_array($bucket, self::WINDOWED_BUCKETS, true);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function bucketWindow(string $bucket): ?array
    {
        return match ($bucket) {
            'today' => [now()->copy()->startOfDay(), now()->copy()->endOfDay()],
            'this_week' => [now()->copy()->startOfDay(), now()->copy()->endOfWeek()],
            'upcoming' => [now()->copy()->endOfDay()->addSecond(), now()->copy()->addDays(30)->endOfDay()],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function expandWindowedRecurrence(User $actor, array $filters): array
    {
        $window = $this->bucketWindow((string) ($filters['bucket'] ?? 'all'));
        if ($window === null) {
            return [];
        }

        [$from, $to] = $window;
        $masters = $this->fetchRecurringMasters($actor, $filters, $from, $to);
        if ($masters === []) {
            return [];
        }

        $linkedMasterIds = Task::query()
            ->whereNotNull('calendar_item_id')
            ->whereIn('calendar_item_id', array_map(fn (CalendarItem $m) => (int) $m->id, $masters))
            ->pluck('calendar_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $linkedLookup = array_fill_keys($linkedMasterIds, true);

        $items = [];
        foreach ($masters as $master) {
            if (isset($linkedLookup[(int) $master->id])) {
                continue;
            }

            foreach ($this->recurrenceExpander->expand($master, $from, $to) as $occurrence) {
                $items[] = $this->calendarAdapter->toItem($master, $actor, $occurrence);
                if (count($items) >= self::MAX_RECURRENCE_OCCURRENCES) {
                    return $items;
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<CalendarItem>
     */
    private function fetchRecurringMasters(User $actor, array $filters, Carbon $from, Carbon $to): array
    {
        $query = CalendarItem::query()
            ->with(['assignees:id,name,department_id', 'creator:id,name', 'blockedBy:id,title,status'])
            ->where('type', CalendarItemType::Task->value)
            ->whereNotNull('recurrence_rule')
            ->where('recurrence_rule', '!=', '')
            ->whereNull('recurrence_parent_id')
            ->where('starts_at', '<=', $to)
            ->where(function (Builder $until) use ($from): void {
                $until->whereNull('recurrence_until')
                    ->orWhere('recurrence_until', '>=', $from);
            })
            ->whereNotIn('status', [
                CalendarItemStatus::Cancelled->value,
                CalendarItemStatus::Completed->value,
            ]);

        $this->applyCalendarVisibility($query, $actor, (string) ($filters['scope'] ?? 'mine'));

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['project_id'])) {
            $projectId = (int) $filters['project_id'];
            $query->where('related_type', 'project')->where('related_id', $projectId);
        }

        if (! empty($filters['assigned_to'])) {
            $assigneeId = (int) $filters['assigned_to'];
            $query->whereHas('assignees', fn (Builder $q) => $q->where('users.id', $assigneeId));
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', strtoupper((string) $filters['priority']));
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        $this->applyCalendarNormalizedStatus($query, $filters['status'] ?? null);

        return $query->orderBy('id')->limit(50)->get()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function stripRecurringMastersFromItems(array $items): array
    {
        $calendarIds = [];
        foreach ($items as $item) {
            if (($item['source_type'] ?? '') === 'calendar') {
                $calendarIds[] = (int) $item['source_id'];
            }
        }

        if ($calendarIds === []) {
            return $items;
        }

        $recurringIds = CalendarItem::query()
            ->whereIn('id', array_values(array_unique($calendarIds)))
            ->whereNotNull('recurrence_rule')
            ->where('recurrence_rule', '!=', '')
            ->whereNull('recurrence_parent_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $lookup = array_fill_keys($recurringIds, true);

        return array_values(array_filter(
            $items,
            function (array $item) use ($lookup): bool {
                if (($item['source_type'] ?? '') !== 'calendar') {
                    return true;
                }

                // Keep dated virtual ids if somehow present; strip bare master ids.
                $id = (string) ($item['id'] ?? '');
                if (preg_match('/^calendar:\d+:\d{4}-\d{2}-\d{2}$/', $id) === 1) {
                    return true;
                }

                return ! isset($lookup[(int) ($item['source_id'] ?? 0)]);
            },
        ));
    }

    /**
     * Hide virtual occurrences when a workspace Task links to the recurrence master.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function dedupeOccurrencesAgainstLinkedTasks(array $items): array
    {
        $linkedMasterIds = [];
        foreach ($items as $item) {
            if (($item['source_type'] ?? '') !== 'task') {
                continue;
            }
            $calendarItemId = (int) ($item['calendar_item_id'] ?? 0);
            if ($calendarItemId > 0) {
                $linkedMasterIds[$calendarItemId] = true;
            }
        }

        if ($linkedMasterIds === []) {
            $occurrenceMasterIds = [];
            foreach ($items as $item) {
                $id = (string) ($item['id'] ?? '');
                if (preg_match('/^calendar:(\d+):\d{4}-\d{2}-\d{2}$/', $id, $m) === 1) {
                    $occurrenceMasterIds[] = (int) $m[1];
                }
            }

            if ($occurrenceMasterIds !== []) {
                foreach (
                    Task::query()
                        ->whereNotNull('calendar_item_id')
                        ->whereIn('calendar_item_id', array_values(array_unique($occurrenceMasterIds)))
                        ->pluck('calendar_item_id') as $calendarItemId
                ) {
                    $linkedMasterIds[(int) $calendarItemId] = true;
                }
            }
        }

        if ($linkedMasterIds === []) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            function (array $item) use ($linkedMasterIds): bool {
                $id = (string) ($item['id'] ?? '');
                if (preg_match('/^calendar:(\d+):\d{4}-\d{2}-\d{2}$/', $id, $m) !== 1) {
                    return true;
                }

                return ! isset($linkedMasterIds[(int) $m[1]]);
            },
        ));
    }

    /**
     * @return array{
     *     today_tasks: int,
     *     overdue: int,
     *     open: int,
     *     in_progress: int,
     *     completed_today: int
     * }
     */
    public function summary(User $actor): array
    {
        $scope = ($actor->role instanceof UserRole && $actor->role->canManageTeamWork())
            ? 'team'
            : 'mine';

        $items = $this->collectForSummary($actor, $scope);

        $today = now()->toDateString();
        $todayTasks = 0;
        $overdue = 0;
        $open = 0;
        $inProgress = 0;
        $completedToday = 0;

        foreach ($items as $item) {
            $status = $item['status'] ?? '';
            $due = $item['due_at'] ?? ($item['starts_at'] ? substr((string) $item['starts_at'], 0, 10) : null);

            if ($item['is_overdue'] ?? false) {
                $overdue++;
            }

            if ($status === 'open') {
                $open++;
            }
            if ($status === 'in_progress') {
                $inProgress++;
            }

            if ($due === $today && ! ($item['is_completed'] ?? false) && ($item['status'] ?? '') !== 'cancelled') {
                $todayTasks++;
            }

            if (($item['is_completed'] ?? false) && $due === $today) {
                $completedToday++;
            }
        }

        return [
            'today_tasks' => $todayTasks,
            'overdue' => $overdue,
            'open' => $open,
            'in_progress' => $inProgress,
            'completed_today' => $completedToday,
        ];
    }

    /**
     * @return array{from: string, to: string, by_assignee: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function workload(User $actor, Carbon $from, Carbon $to): array
    {
        $filters = [
            'scope' => ($actor->role instanceof UserRole && $actor->role->canViewTeamCalendar()) ? 'team' : 'mine',
            'bucket' => 'all',
        ];

        $items = [];
        foreach ($this->fetchTasks($actor, $filters, 200) as $task) {
            $deadline = $task->deadline;
            if ($deadline === null) {
                continue;
            }
            $day = $deadline->copy()->startOfDay();
            if ($day->lt($from->copy()->startOfDay()) || $day->gt($to->copy()->endOfDay())) {
                continue;
            }
            $items[] = $this->taskAdapter->toItem($task, $actor);
        }

        foreach ($this->fetchCalendarTasks($actor, array_merge($filters, [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
        ]), 200) as $calendarItem) {
            $items[] = $this->calendarAdapter->toItem($calendarItem, $actor);
        }

        $items = $this->dedupeWorkspaceTaskLinks($items);
        $items = array_values(array_filter(
            $items,
            fn (array $item): bool => ! ($item['is_completed'] ?? false) && ($item['status'] ?? '') !== 'cancelled',
        ));

        $byAssignee = [];
        foreach ($items as $item) {
            $assignees = $item['assignee_ids'] ?: [0];
            foreach ($assignees as $assigneeId) {
                $key = (string) $assigneeId;
                if (! isset($byAssignee[$key])) {
                    $byAssignee[$key] = [
                        'user_id' => (int) $assigneeId,
                        'count' => 0,
                        'overdue' => 0,
                        'urgent' => 0,
                    ];
                }
                $byAssignee[$key]['count']++;
                if ($item['is_overdue'] ?? false) {
                    $byAssignee[$key]['overdue']++;
                }
                if (($item['priority'] ?? '') === 'URGENT') {
                    $byAssignee[$key]['urgent']++;
                }
            }
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'by_assignee' => array_values($byAssignee),
            'totals' => [
                'items' => count($items),
                'overdue' => count(array_filter($items, fn (array $i): bool => (bool) ($i['is_overdue'] ?? false))),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function focus(User $actor, int $limit = 5): array
    {
        $items = $this->collectForSummary(
            $actor,
            'mine',
        );

        $items = array_values(array_filter(
            $items,
            fn (array $item): bool => ! ($item['is_completed'] ?? false)
                && ($item['status'] ?? '') !== 'cancelled'
                && ! ($item['is_blocked'] ?? false),
        ));

        usort($items, function (array $a, array $b): int {
            $score = fn (array $item): int => ($item['is_overdue'] ?? false ? 0 : 100)
                + match ($item['priority'] ?? 'MEDIUM') {
                    'URGENT' => 0,
                    'HIGH' => 10,
                    'MEDIUM' => 20,
                    default => 30,
                };

            $dueCmp = strcmp(
                (string) ($a['due_at'] ?? $a['starts_at'] ?? '9999'),
                (string) ($b['due_at'] ?? $b['starts_at'] ?? '9999'),
            );

            return $score($a) <=> $score($b) ?: $dueCmp ?: strcmp($a['id'], $b['id']);
        });

        return array_slice($items, 0, max(1, min($limit, 20)));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function kanban(User $actor, array $filters = []): array
    {
        $scope = (string) ($filters['scope'] ?? 'mine');
        $items = $this->collectForSummary($actor, $scope);
        $items = $this->applyNormalizedFilters($items, $filters);

        $columns = [
            'open' => [],
            'in_progress' => [],
            'review' => [],
            'overdue' => [],
            'completed' => [],
            'cancelled' => [],
        ];

        foreach ($items as $item) {
            $status = $item['status'] ?? 'open';
            if (! array_key_exists($status, $columns)) {
                $status = 'open';
            }
            $columns[$status][] = $item;
        }

        return $columns;
    }

    public function actorCanViewTask(User $actor, Task $task): bool
    {
        if ((int) $task->assigned_to === (int) $actor->id || (int) $task->created_by === (int) $actor->id) {
            return true;
        }

        $role = $actor->role;
        if (! $role instanceof UserRole) {
            return false;
        }

        if ($role === UserRole::Owner || $role === UserRole::AdminManager) {
            return true;
        }

        $task->loadMissing('project');

        return $task->project !== null && (int) $task->project->account_manager_id === (int) $actor->id;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<Task>
     */
    private function fetchTasks(User $actor, array $filters, int $limit): array
    {
        $query = Task::query()->with(['assignee', 'creator', 'project']);
        $this->applyTaskVisibility($query, $actor, (string) ($filters['scope'] ?? 'mine'));

        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', strtoupper((string) $filters['priority']));
        }

        if (! empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            $query->where(function (Builder $builder) use ($departmentId): void {
                $builder->whereHas('assignee', fn (Builder $q) => $q->where('department_id', $departmentId));
            });
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        $this->applyTaskBucket($query, (string) ($filters['bucket'] ?? 'all'));
        $this->applyTaskNormalizedStatus($query, $filters['status'] ?? null);

        return $query->orderByDesc('id')->limit($limit)->get()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<CalendarItem>
     */
    private function fetchCalendarTasks(User $actor, array $filters, int $limit): array
    {
        $query = CalendarItem::query()
            ->with(['assignees:id,name,department_id', 'creator:id,name', 'blockedBy:id,title,status'])
            ->where('type', CalendarItemType::Task->value);

        $this->applyCalendarVisibility($query, $actor, (string) ($filters['scope'] ?? 'mine'));

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['project_id'])) {
            $projectId = (int) $filters['project_id'];
            $query->where('related_type', 'project')->where('related_id', $projectId);
        }

        if (! empty($filters['assigned_to'])) {
            $assigneeId = (int) $filters['assigned_to'];
            $query->whereHas('assignees', fn (Builder $q) => $q->where('users.id', $assigneeId));
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', strtoupper((string) $filters['priority']));
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $query->whereBetween('starts_at', [
                Carbon::parse((string) $filters['from']),
                Carbon::parse((string) $filters['to']),
            ]);
        }

        $this->applyCalendarBucket($query, (string) ($filters['bucket'] ?? 'all'));
        $this->applyCalendarNormalizedStatus($query, $filters['status'] ?? null);

        return $query->orderByDesc('id')->limit($limit)->get()->all();
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyTaskVisibility(Builder $query, User $actor, string $scope): void
    {
        $role = $actor->role;
        $canTeam = $role instanceof UserRole && (
            $role->canManageTeamWork()
            || $role->canViewTeamCalendar()
            || $role === UserRole::Owner
            || $role === UserRole::AdminManager
        );

        if ($scope === 'team' && $canTeam) {
            return;
        }

        $query->where(function (Builder $builder) use ($actor): void {
            $builder->where('assigned_to', $actor->id)
                ->orWhere('created_by', $actor->id)
                ->orWhereHas('project', fn (Builder $projects) => $projects->where('account_manager_id', $actor->id));
        });
    }

    /**
     * @param  Builder<CalendarItem>  $query
     */
    private function applyCalendarVisibility(Builder $query, User $actor, string $scope): void
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
     * @param  Builder<Task>  $query
     */
    private function applyTaskBucket(Builder $query, string $bucket): void
    {
        $today = now()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        match ($bucket) {
            'today' => $query->whereDate('deadline', $today)
                ->where('status', '!=', TaskStatus::Completed->value),
            'overdue' => $query->where('status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', $today),
            'this_week' => $query->where('status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '>=', $today)
                ->whereDate('deadline', '<=', $weekEnd),
            'upcoming' => $query->where('status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '>', $today),
            'completed' => $query->where('status', TaskStatus::Completed->value),
            default => null,
        };
    }

    /**
     * @param  Builder<CalendarItem>  $query
     */
    private function applyCalendarBucket(Builder $query, string $bucket): void
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $weekEnd = now()->endOfWeek();

        match ($bucket) {
            'today' => $query->whereBetween('starts_at', [$todayStart, $todayEnd])
                ->whereNotIn('status', [CalendarItemStatus::Cancelled->value, CalendarItemStatus::Completed->value]),
            'overdue' => $query->where(function (Builder $builder) use ($todayStart): void {
                $builder->where('status', CalendarItemStatus::Overdue->value)
                    ->orWhere(function (Builder $inner) use ($todayStart): void {
                        $inner->where('starts_at', '<', $todayStart)
                            ->whereNotIn('status', [
                                CalendarItemStatus::Completed->value,
                                CalendarItemStatus::Cancelled->value,
                            ]);
                    });
            }),
            'this_week' => $query->whereBetween('starts_at', [$todayStart, $weekEnd])
                ->whereNotIn('status', [CalendarItemStatus::Cancelled->value, CalendarItemStatus::Completed->value]),
            'upcoming' => $query->where('starts_at', '>', $todayEnd)
                ->whereNotIn('status', [CalendarItemStatus::Cancelled->value, CalendarItemStatus::Completed->value]),
            'completed' => $query->where('status', CalendarItemStatus::Completed->value),
            default => null,
        };
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyTaskNormalizedStatus(Builder $query, mixed $status): void
    {
        if (! is_string($status) || $status === '') {
            return;
        }

        match (strtolower($status)) {
            'open' => $query->where('status', TaskStatus::Todo->value),
            'in_progress' => $query->where('status', TaskStatus::InProgress->value),
            'review' => $query->whereIn('status', [TaskStatus::Review->value, TaskStatus::Revision->value]),
            'completed' => $query->where('status', TaskStatus::Completed->value),
            'overdue' => $query->where('status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', now()->toDateString()),
            'cancelled' => $query->whereRaw('1 = 0'),
            default => null,
        };
    }

    /**
     * @param  Builder<CalendarItem>  $query
     */
    private function applyCalendarNormalizedStatus(Builder $query, mixed $status): void
    {
        if (! is_string($status) || $status === '') {
            return;
        }

        match (strtolower($status)) {
            'open' => $query->where('status', CalendarItemStatus::Scheduled->value),
            'in_progress' => $query->where('status', CalendarItemStatus::InProgress->value),
            'review' => $query->where('status', CalendarItemStatus::InProgress->value),
            'completed' => $query->where('status', CalendarItemStatus::Completed->value),
            'cancelled' => $query->where('status', CalendarItemStatus::Cancelled->value),
            'overdue' => $query->where('status', CalendarItemStatus::Overdue->value),
            default => null,
        };
    }

    /**
     * Prefer Task when soft-linked to a calendar clone (V2).
     *
     * Priority:
     * 1. Task.calendar_item_id → hide that calendar id when the task is in the result set
     * 2. CalendarItem.related_type=workspace_task → hide when matching task is present
     * 3. Otherwise keep both as separate items
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function dedupeWorkspaceTaskLinks(array $items): array
    {
        $taskIds = [];
        $linkedCalendarIds = [];

        foreach ($items as $item) {
            if (($item['source_type'] ?? '') !== 'task') {
                continue;
            }

            $taskIds[(int) $item['source_id']] = true;
            $calendarItemId = (int) ($item['calendar_item_id'] ?? 0);
            if ($calendarItemId > 0) {
                $linkedCalendarIds[$calendarItemId] = true;
            }
        }

        $out = [];
        foreach ($items as $item) {
            if (($item['source_type'] ?? '') === 'calendar') {
                $calendarId = (int) ($item['source_id'] ?? 0);

                if (isset($linkedCalendarIds[$calendarId])) {
                    continue;
                }

                if (
                    ($item['related_type'] ?? null) === 'workspace_task'
                    && isset($taskIds[(int) ($item['related_id'] ?? 0)])
                ) {
                    continue;
                }
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function applyNormalizedFilters(array $items, array $filters): array
    {
        $status = isset($filters['status']) ? strtolower((string) $filters['status']) : null;
        $bucket = (string) ($filters['bucket'] ?? 'all');

        return array_values(array_filter($items, function (array $item) use ($status, $bucket): bool {
            if ($status !== null && $status !== '' && ($item['status'] ?? '') !== $status) {
                // Allow overdue flag to satisfy overdue status filter.
                if (! ($status === 'overdue' && ($item['is_overdue'] ?? false))) {
                    return false;
                }
            }

            if ($bucket === 'today+overdue' || $bucket === 'today_overdue') {
                $due = $item['due_at'] ?? ($item['starts_at'] ? substr((string) $item['starts_at'], 0, 10) : null);
                $isToday = $due === now()->toDateString();

                return ($item['is_overdue'] ?? false) || $isToday;
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sortItems(array $items, string $sort): array
    {
        $priorityRank = ['URGENT' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];

        usort($items, function (array $a, array $b) use ($sort, $priorityRank): int {
            return match ($sort) {
                'priority' => ($priorityRank[$a['priority'] ?? 'MEDIUM'] ?? 9) <=> ($priorityRank[$b['priority'] ?? 'MEDIUM'] ?? 9)
                    ?: strcmp($a['id'], $b['id']),
                'due' => strcmp(
                    (string) ($a['due_at'] ?? $a['starts_at'] ?? '9999'),
                    (string) ($b['due_at'] ?? $b['starts_at'] ?? '9999'),
                ) ?: strcmp($a['id'], $b['id']),
                'newest' => strcmp($b['id'], $a['id']),
                default => ((int) ! ($a['is_overdue'] ?? false)) <=> ((int) ! ($b['is_overdue'] ?? false))
                    ?: ($priorityRank[$a['priority'] ?? 'MEDIUM'] ?? 9) <=> ($priorityRank[$b['priority'] ?? 'MEDIUM'] ?? 9)
                    ?: strcmp(
                        (string) ($a['due_at'] ?? $a['starts_at'] ?? '9999'),
                        (string) ($b['due_at'] ?? $b['starts_at'] ?? '9999'),
                    )
                    ?: strcmp($a['id'], $b['id']),
            };
        });

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectForSummary(User $actor, string $scope): array
    {
        $filters = ['scope' => $scope, 'bucket' => 'all'];
        $items = [];

        foreach ($this->fetchTasks($actor, $filters, 200) as $task) {
            $items[] = $this->taskAdapter->toItem($task, $actor);
        }
        foreach ($this->fetchCalendarTasks($actor, $filters, 200) as $calendarItem) {
            $items[] = $this->calendarAdapter->toItem($calendarItem, $actor);
        }

        return $this->dedupeWorkspaceTaskLinks($items);
    }
}
