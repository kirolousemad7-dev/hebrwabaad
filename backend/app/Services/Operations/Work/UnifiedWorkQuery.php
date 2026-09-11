<?php

namespace App\Services\Operations\Work;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * SQL UNION ALL read path for Unified Work.
 *
 * Returns persistent calendar_items rows only (masters / non-instances).
 * Phase 6K: UnifiedWorkService merges bounded RecurrenceExpander occurrences
 * for buckets today|this_week|upcoming after this query. Do not expand here.
 *
 * Benchmark: php artisan operations:benchmark-unified-work
 */
class UnifiedWorkQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     rows: list<stdClass>,
     *     meta: array{current_page: int, per_page: int, total: int, last_page: int}
     * }
     */
    public function paginate(User $actor, array $filters, int $page, int $perPage): array
    {
        $total = $this->count($actor, $filters);
        $offset = max(0, ($page - 1) * $perPage);

        $sorted = $this->applySort(
            $this->dedupedUnion($actor, $filters),
            (string) ($filters['sort'] ?? 'overdue_first'),
        );

        /** @var list<stdClass> $rows */
        $rows = $sorted
            ->select(['source_type', 'source_id'])
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function count(User $actor, array $filters): int
    {
        return (int) DB::query()
            ->fromSub($this->dedupedUnion($actor, $filters), 'unified_count')
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dedupedUnion(User $actor, array $filters): Builder
    {
        $source = $filters['source'] ?? null;
        $scope = (string) ($filters['scope'] ?? 'mine');

        if ($source === 'task') {
            $union = $this->taskProjection($actor, $filters);
        } elseif ($source === 'calendar') {
            $union = $this->calendarProjection($actor, $filters);
        } else {
            $union = $this->taskProjection($actor, $filters)
                ->unionAll($this->calendarProjection($actor, $filters));
        }

        $outer = DB::query()->fromSub($union, 'uw');

        if ($source !== 'task') {
            $this->applyLinkedDedupe($outer, $actor, $scope);
        }

        return $outer;
    }

    /**
     * Prefer workspace Task when soft-linked to a calendar clone.
     */
    private function applyLinkedDedupe(Builder $query, User $actor, string $scope): void
    {
        $query->where(function (Builder $builder) use ($actor, $scope): void {
            $builder->where('uw.source_type', '!=', 'calendar')
                ->orWhere(function (Builder $calendar) use ($actor, $scope): void {
                    $calendar
                        ->whereNotIn('uw.source_id', function (Builder $sub) use ($actor, $scope): void {
                            $sub->select('tasks.calendar_item_id')
                                ->from('tasks')
                                ->whereNotNull('tasks.calendar_item_id');
                            $this->applyTaskVisibility($sub, $actor, $scope);
                        })
                        ->where(function (Builder $related) use ($actor, $scope): void {
                            $related->whereNull('uw.related_type')
                                ->orWhere('uw.related_type', '!=', 'workspace_task')
                                ->orWhereNotIn('uw.related_id', function (Builder $sub) use ($actor, $scope): void {
                                    $sub->select('tasks.id')->from('tasks');
                                    $this->applyTaskVisibility($sub, $actor, $scope);
                                });
                        });
                });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function taskProjection(User $actor, array $filters): Builder
    {
        $today = now()->toDateString();
        $completed = TaskStatus::Completed->value;

        $statusCase = "
            CASE
                WHEN tasks.status != ? AND tasks.deadline IS NOT NULL AND tasks.deadline < ? THEN 'overdue'
                WHEN tasks.status = 'TODO' THEN 'open'
                WHEN tasks.status = 'IN_PROGRESS' THEN 'in_progress'
                WHEN tasks.status IN ('REVIEW', 'REVISION') THEN 'review'
                WHEN tasks.status = 'COMPLETED' THEN 'completed'
                ELSE 'open'
            END
        ";

        $overdueCase = '
            CASE
                WHEN tasks.status != ? AND tasks.deadline IS NOT NULL AND tasks.deadline < ? THEN 1
                ELSE 0
            END
        ';

        $priorityRank = "
            CASE tasks.priority
                WHEN 'URGENT' THEN 0
                WHEN 'HIGH' THEN 1
                WHEN 'MEDIUM' THEN 2
                WHEN 'LOW' THEN 3
                ELSE 9
            END
        ";

        $query = DB::table('tasks')
            ->leftJoin('users as task_assignees', 'task_assignees.id', '=', 'tasks.assigned_to')
            ->selectRaw(
                "'task' as source_type,
                tasks.id as source_id,
                tasks.title as title,
                {$statusCase} as normalized_status,
                tasks.priority as priority,
                tasks.deadline as due_at,
                NULL as starts_at,
                tasks.assigned_to as assigned_to,
                task_assignees.department_id as department_id,
                tasks.project_id as project_id,
                tasks.created_by as created_by,
                tasks.created_at as created_at,
                tasks.calendar_item_id as calendar_item_id,
                CASE WHEN tasks.project_id IS NOT NULL THEN 'project' ELSE NULL END as related_type,
                tasks.project_id as related_id,
                {$overdueCase} as is_overdue,
                {$priorityRank} as priority_rank",
                [$completed, $today, $completed, $today],
            );

        $this->applyTaskVisibility($query, $actor, (string) ($filters['scope'] ?? 'mine'));
        $this->applyTaskFilters($query, $filters);
        $this->applyTaskBucket($query, (string) ($filters['bucket'] ?? 'all'));
        $this->applyTaskNormalizedStatus($query, $filters['status'] ?? null, $today);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function calendarProjection(User $actor, array $filters): Builder
    {
        $now = now()->toDateTimeString();
        $todayStart = now()->startOfDay()->toDateTimeString();
        $cancelled = CalendarItemStatus::Cancelled->value;
        $completed = CalendarItemStatus::Completed->value;
        $overdue = CalendarItemStatus::Overdue->value;

        $statusCase = "
            CASE
                WHEN calendar_items.status = ? THEN 'cancelled'
                WHEN calendar_items.status = ? THEN 'completed'
                WHEN calendar_items.status = ? OR (
                    calendar_items.status NOT IN (?, ?)
                    AND calendar_items.starts_at < ?
                ) THEN 'overdue'
                WHEN calendar_items.status = 'SCHEDULED' THEN 'open'
                WHEN calendar_items.status = 'IN_PROGRESS' THEN 'in_progress'
                ELSE 'open'
            END
        ";

        $overdueCase = '
            CASE
                WHEN calendar_items.status = ? THEN 0
                WHEN calendar_items.status = ? THEN 0
                WHEN calendar_items.status = ? OR calendar_items.starts_at < ? THEN 1
                ELSE 0
            END
        ';

        $priorityRank = "
            CASE calendar_items.priority
                WHEN 'URGENT' THEN 0
                WHEN 'HIGH' THEN 1
                WHEN 'MEDIUM' THEN 2
                WHEN 'LOW' THEN 3
                ELSE 9
            END
        ";

        $query = DB::table('calendar_items')
            ->where('calendar_items.type', CalendarItemType::Task->value)
            ->whereNull('calendar_items.deleted_at')
            ->whereNull('calendar_items.recurrence_parent_id')
            ->selectRaw(
                "'calendar' as source_type,
                calendar_items.id as source_id,
                calendar_items.title as title,
                {$statusCase} as normalized_status,
                calendar_items.priority as priority,
                DATE(calendar_items.starts_at) as due_at,
                calendar_items.starts_at as starts_at,
                NULL as assigned_to,
                calendar_items.department_id as department_id,
                CASE WHEN calendar_items.related_type = 'project' THEN calendar_items.related_id ELSE NULL END as project_id,
                calendar_items.created_by as created_by,
                calendar_items.created_at as created_at,
                NULL as calendar_item_id,
                calendar_items.related_type as related_type,
                calendar_items.related_id as related_id,
                {$overdueCase} as is_overdue,
                {$priorityRank} as priority_rank",
                [
                    $cancelled,
                    $completed,
                    $overdue,
                    $completed,
                    $cancelled,
                    $now,
                    $completed,
                    $cancelled,
                    $overdue,
                    $now,
                ],
            );

        $this->applyCalendarVisibility($query, $actor, (string) ($filters['scope'] ?? 'mine'));
        $this->applyCalendarFilters($query, $filters);
        $this->applyCalendarBucket($query, (string) ($filters['bucket'] ?? 'all'), $todayStart);
        $this->applyCalendarNormalizedStatus($query, $filters['status'] ?? null);

        return $query;
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'priority' => $query
                ->orderBy('priority_rank')
                ->orderBy('source_type')
                ->orderByDesc('source_id'),
            'due' => $query
                ->orderByRaw('due_at IS NULL')
                ->orderBy('due_at')
                ->orderBy('source_type')
                ->orderByDesc('source_id'),
            'newest' => $query
                ->orderByDesc('source_id')
                ->orderBy('source_type'),
            default => $query
                ->orderByDesc('is_overdue')
                ->orderBy('priority_rank')
                ->orderByRaw('due_at IS NULL')
                ->orderBy('due_at')
                ->orderBy('source_type')
                ->orderByDesc('source_id'),
        };
    }

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
            $builder->where('tasks.assigned_to', $actor->id)
                ->orWhere('tasks.created_by', $actor->id)
                ->orWhereExists(function (Builder $projects) use ($actor): void {
                    $projects->selectRaw('1')
                        ->from('projects')
                        ->whereColumn('projects.id', 'tasks.project_id')
                        ->where('projects.account_manager_id', $actor->id);
                });
        });
    }

    private function applyCalendarVisibility(Builder $query, User $actor, string $scope): void
    {
        $canTeam = $actor->role instanceof UserRole && $actor->role->canViewTeamCalendar();

        if ($scope === 'team' && $canTeam) {
            return;
        }

        $query->where(function (Builder $builder) use ($actor): void {
            $builder->where('calendar_items.created_by', $actor->id)
                ->orWhereExists(function (Builder $assignees) use ($actor): void {
                    $assignees->selectRaw('1')
                        ->from('calendar_item_assignees')
                        ->whereColumn('calendar_item_assignees.calendar_item_id', 'calendar_items.id')
                        ->where('calendar_item_assignees.user_id', $actor->id);
                });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyTaskFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['project_id'])) {
            $query->where('tasks.project_id', (int) $filters['project_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('tasks.assigned_to', (int) $filters['assigned_to']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('tasks.created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['priority'])) {
            $query->where('tasks.priority', strtoupper((string) $filters['priority']));
        }

        if (! empty($filters['department_id'])) {
            $query->where('task_assignees.department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('tasks.title', 'like', $term)
                    ->orWhere('tasks.description', 'like', $term);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyCalendarFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['department_id'])) {
            $query->where('calendar_items.department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('calendar_items.related_type', 'project')
                ->where('calendar_items.related_id', (int) $filters['project_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $assigneeId = (int) $filters['assigned_to'];
            $query->whereExists(function (Builder $assignees) use ($assigneeId): void {
                $assignees->selectRaw('1')
                    ->from('calendar_item_assignees')
                    ->whereColumn('calendar_item_assignees.calendar_item_id', 'calendar_items.id')
                    ->where('calendar_item_assignees.user_id', $assigneeId);
            });
        }

        if (! empty($filters['created_by'])) {
            $query->where('calendar_items.created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['priority'])) {
            $query->where('calendar_items.priority', strtoupper((string) $filters['priority']));
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('calendar_items.title', 'like', $term)
                    ->orWhere('calendar_items.description', 'like', $term);
            });
        }

        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $query->whereBetween('calendar_items.starts_at', [
                Carbon::parse((string) $filters['from']),
                Carbon::parse((string) $filters['to']),
            ]);
        }
    }

    private function applyTaskBucket(Builder $query, string $bucket): void
    {
        $today = now()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $completed = TaskStatus::Completed->value;

        match ($bucket) {
            'today' => $query->whereDate('tasks.deadline', $today)
                ->where('tasks.status', '!=', $completed),
            'overdue' => $query->where('tasks.status', '!=', $completed)
                ->whereNotNull('tasks.deadline')
                ->whereDate('tasks.deadline', '<', $today),
            'this_week' => $query->where('tasks.status', '!=', $completed)
                ->whereNotNull('tasks.deadline')
                ->whereDate('tasks.deadline', '>=', $today)
                ->whereDate('tasks.deadline', '<=', $weekEnd),
            'upcoming' => $query->where('tasks.status', '!=', $completed)
                ->whereNotNull('tasks.deadline')
                ->whereDate('tasks.deadline', '>', $today),
            'completed' => $query->where('tasks.status', $completed),
            'today_overdue', 'today+overdue' => $query->where(function (Builder $builder) use ($today, $completed): void {
                $builder->where(function (Builder $todayQ) use ($today, $completed): void {
                    $todayQ->whereDate('tasks.deadline', $today)
                        ->where('tasks.status', '!=', $completed);
                })->orWhere(function (Builder $overdueQ) use ($today, $completed): void {
                    $overdueQ->where('tasks.status', '!=', $completed)
                        ->whereNotNull('tasks.deadline')
                        ->whereDate('tasks.deadline', '<', $today);
                });
            }),
            default => null,
        };
    }

    private function applyCalendarBucket(Builder $query, string $bucket, string $todayStart): void
    {
        $todayEnd = now()->endOfDay()->toDateTimeString();
        $weekEnd = now()->endOfWeek()->toDateTimeString();
        $cancelled = CalendarItemStatus::Cancelled->value;
        $completed = CalendarItemStatus::Completed->value;
        $overdue = CalendarItemStatus::Overdue->value;

        match ($bucket) {
            'today' => $query->whereBetween('calendar_items.starts_at', [$todayStart, $todayEnd])
                ->whereNotIn('calendar_items.status', [$cancelled, $completed]),
            'overdue' => $query->where(function (Builder $builder) use ($todayStart, $overdue, $completed, $cancelled): void {
                $builder->where('calendar_items.status', $overdue)
                    ->orWhere(function (Builder $inner) use ($todayStart, $completed, $cancelled): void {
                        $inner->where('calendar_items.starts_at', '<', $todayStart)
                            ->whereNotIn('calendar_items.status', [$completed, $cancelled]);
                    });
            }),
            'this_week' => $query->whereBetween('calendar_items.starts_at', [$todayStart, $weekEnd])
                ->whereNotIn('calendar_items.status', [$cancelled, $completed]),
            'upcoming' => $query->where('calendar_items.starts_at', '>', $todayEnd)
                ->whereNotIn('calendar_items.status', [$cancelled, $completed]),
            'completed' => $query->where('calendar_items.status', $completed),
            'today_overdue', 'today+overdue' => $query->where(function (Builder $builder) use ($todayStart, $todayEnd, $overdue, $completed, $cancelled): void {
                $builder->where(function (Builder $todayQ) use ($todayStart, $todayEnd, $cancelled, $completed): void {
                    $todayQ->whereBetween('calendar_items.starts_at', [$todayStart, $todayEnd])
                        ->whereNotIn('calendar_items.status', [$cancelled, $completed]);
                })->orWhere(function (Builder $overdueQ) use ($todayStart, $overdue, $completed, $cancelled): void {
                    $overdueQ->where('calendar_items.status', $overdue)
                        ->orWhere(function (Builder $inner) use ($todayStart, $completed, $cancelled): void {
                            $inner->where('calendar_items.starts_at', '<', $todayStart)
                                ->whereNotIn('calendar_items.status', [$completed, $cancelled]);
                        });
                });
            }),
            default => null,
        };
    }

    private function applyTaskNormalizedStatus(Builder $query, mixed $status, string $today): void
    {
        if (! is_string($status) || $status === '') {
            return;
        }

        match (strtolower($status)) {
            'open' => $query->where('tasks.status', TaskStatus::Todo->value),
            'in_progress' => $query->where('tasks.status', TaskStatus::InProgress->value),
            'review' => $query->whereIn('tasks.status', [TaskStatus::Review->value, TaskStatus::Revision->value]),
            'completed' => $query->where('tasks.status', TaskStatus::Completed->value),
            'overdue' => $query->where('tasks.status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('tasks.deadline')
                ->whereDate('tasks.deadline', '<', $today),
            'cancelled' => $query->whereRaw('1 = 0'),
            default => null,
        };
    }

    private function applyCalendarNormalizedStatus(Builder $query, mixed $status): void
    {
        if (! is_string($status) || $status === '') {
            return;
        }

        match (strtolower($status)) {
            'open' => $query->where('calendar_items.status', CalendarItemStatus::Scheduled->value),
            'in_progress' => $query->where('calendar_items.status', CalendarItemStatus::InProgress->value),
            'review' => $query->where('calendar_items.status', CalendarItemStatus::InProgress->value),
            'completed' => $query->where('calendar_items.status', CalendarItemStatus::Completed->value),
            'cancelled' => $query->where('calendar_items.status', CalendarItemStatus::Cancelled->value),
            'overdue' => $query->where('calendar_items.status', CalendarItemStatus::Overdue->value),
            default => null,
        };
    }
}
