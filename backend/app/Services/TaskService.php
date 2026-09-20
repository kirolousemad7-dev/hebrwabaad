<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use App\Services\GoogleCalendar\GoogleCalendarTaskSyncService;
use App\Services\GoogleCalendar\TaskReminderService;
use App\Services\Operations\Work\TaskCalendarLinkService;
use App\Support\Operations\TaskCalendarSyncContext;
use App\Support\ProjectActivityAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ProjectActivityService $activities,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginateAssignedTo(User $user, array $filters): LengthAwarePaginator
    {
        $query = Task::query()
            ->with(['assignee', 'creator', 'project'])
            ->where('assigned_to', $user->id);

        $this->applyFilters($query, $filters, includeAssignee: false);

        return $query->paginate($this->perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginateCreatedBy(User $user, array $filters): LengthAwarePaginator
    {
        $query = Task::query()
            ->with(['assignee', 'creator', 'project'])
            ->where(function (Builder $inner) use ($user): void {
                $inner->where('created_by', $user->id)
                    ->orWhereHas('project', function (Builder $projects) use ($user): void {
                        $projects->where('account_manager_id', $user->id);
                    });
            });

        $this->applyFilters($query, $filters, includeAssignee: true);

        return $query->paginate($this->perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginateForProject(User $user, int $projectId, array $filters): LengthAwarePaginator
    {
        $query = Task::query()
            ->with(['assignee', 'creator', 'project'])
            ->where('project_id', $projectId);

        $role = $user->role;
        if ($role instanceof UserRole && $role->canReceiveAssignedTasks() && $role !== UserRole::AccountManager) {
            $query->where('assigned_to', $user->id);
        }

        $this->applyFilters($query, $filters, includeAssignee: $role === UserRole::AccountManager || $role === UserRole::Owner);

        return $query->paginate($this->perPage($filters));
    }

    /**
     * @return array{total: int, in_progress: int, completed: int, overdue: int}
     */
    public function summaryForCreator(User $user): array
    {
        $base = Task::query()->where(function (Builder $inner) use ($user): void {
            $inner->where('created_by', $user->id)
                ->orWhereHas('project', function (Builder $projects) use ($user): void {
                    $projects->where('account_manager_id', $user->id);
                });
        });

        return [
            'total' => (clone $base)->count(),
            'in_progress' => (clone $base)->where('status', TaskStatus::InProgress->value)->count(),
            'completed' => (clone $base)->where('status', TaskStatus::Completed->value)->count(),
            'overdue' => (clone $base)
                ->where('status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', now()->toDateString())
                ->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): Task
    {
        $this->projects->assertManagedBy($creator, (int) $attributes['project_id']);
        $assignee = $this->assertAssignableEmployee((int) $attributes['assigned_to']);
        $this->assertPhaseAndMilestoneBelongToProject(
            (int) $attributes['project_id'],
            $attributes['phase_id'] ?? null,
            $attributes['milestone_id'] ?? null,
        );

        $task = DB::transaction(function () use ($creator, $attributes, $assignee): Task {
            $task = Task::query()->create([
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'project_id' => (int) $attributes['project_id'],
                'phase_id' => $attributes['phase_id'] ?? null,
                'milestone_id' => $attributes['milestone_id'] ?? null,
                'assigned_to' => $assignee->id,
                'created_by' => $creator->id,
                'priority' => $attributes['priority'],
                'status' => $attributes['status'] ?? TaskStatus::Todo->value,
                'deadline' => $attributes['deadline'] ?? null,
                'start_at' => $attributes['start_at'] ?? null,
                'due_at' => $attributes['due_at'] ?? null,
                'timezone' => $attributes['timezone'] ?? config('app.timezone'),
                'location' => $attributes['location'] ?? null,
                'supplier_id' => $attributes['supplier_id'] ?? null,
                'is_client_visible' => (bool) ($attributes['is_client_visible'] ?? false),
            ]);

            $project = $task->project()->first()
                ?? Project::query()->findOrFail($task->project_id);

            $this->activities->recordUserAction(
                project: $project,
                user: $creator,
                action: ProjectActivityAction::TASK_CREATED,
                entityType: 'task',
                entityId: (int) $task->id,
                description: 'Task created',
                metadata: [
                    'title' => $task->title,
                    'assigned_to' => $task->assigned_to,
                    'status' => $task->status instanceof TaskStatus
                        ? $task->status->value
                        : (string) $task->status,
                ],
                isClientVisible: (bool) $task->is_client_visible,
            );

            $this->activities->recordUserAction(
                project: $project,
                user: $creator,
                action: ProjectActivityAction::TASK_ASSIGNED,
                entityType: 'task',
                entityId: (int) $task->id,
                description: 'Task assigned',
                metadata: [
                    'assigned_to' => $assignee->id,
                    'assignee_name' => $assignee->name,
                ],
            );

            return $task;
        });

        $task = $task->load(['assignee', 'creator', 'project', 'supplier']);
        app(PlatformNotifier::class)->taskAssigned($task);
        app(PlatformNotifier::class)->taskSupplierAssigned($task);

        if (isset($attributes['reminders']) && is_array($attributes['reminders'])) {
            app(TaskReminderService::class)->syncReminders($task, $attributes['reminders']);
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, Task $task, array $attributes): Task
    {
        $this->projects->assertManagedBy($actor, (int) $attributes['project_id']);
        $assignee = $this->assertAssignableEmployee((int) $attributes['assigned_to']);
        $phaseId = array_key_exists('phase_id', $attributes)
            ? $attributes['phase_id']
            : $task->phase_id;
        $milestoneId = array_key_exists('milestone_id', $attributes)
            ? $attributes['milestone_id']
            : $task->milestone_id;
        $this->assertPhaseAndMilestoneBelongToProject(
            (int) $attributes['project_id'],
            $phaseId,
            $milestoneId,
        );
        $previousAssigneeId = $task->assigned_to;
        $previousTitle = $task->title;
        $previousStatus = $task->status instanceof TaskStatus
            ? $task->status
            : TaskStatus::tryFrom((string) $task->status);

        $previousSupplierId = $task->supplier_id;
        $scheduleChanged = array_key_exists('start_at', $attributes)
            || array_key_exists('due_at', $attributes)
            || array_key_exists('deadline', $attributes);
        $shouldQueueGoogle = (bool) $task->google_sync_enabled;

        $task->update([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'project_id' => (int) $attributes['project_id'],
            'phase_id' => array_key_exists('phase_id', $attributes)
                ? $attributes['phase_id']
                : $task->phase_id,
            'milestone_id' => array_key_exists('milestone_id', $attributes)
                ? $attributes['milestone_id']
                : $task->milestone_id,
            'assigned_to' => $assignee->id,
            'priority' => $attributes['priority'],
            'status' => $attributes['status'],
            'deadline' => $attributes['deadline'] ?? null,
            'start_at' => $attributes['start_at'] ?? $task->start_at,
            'due_at' => $attributes['due_at'] ?? $task->due_at,
            'timezone' => $attributes['timezone'] ?? $task->timezone,
            'location' => $attributes['location'] ?? $task->location,
            'supplier_id' => array_key_exists('supplier_id', $attributes)
                ? $attributes['supplier_id']
                : $task->supplier_id,
            'is_client_visible' => array_key_exists('is_client_visible', $attributes)
                ? (bool) $attributes['is_client_visible']
                : $task->is_client_visible,
        ]);

        $changedForGoogle = $shouldQueueGoogle && $task->wasChanged([
            'title', 'description', 'start_at', 'due_at', 'deadline', 'location', 'status', 'assigned_to',
        ]);

        $task = $task->fresh(['assignee', 'creator', 'project', 'supplier']);
        app(PlatformNotifier::class)->taskAssigned($task, $previousAssigneeId);
        if ((int) $previousSupplierId !== (int) $task->supplier_id) {
            app(PlatformNotifier::class)->taskSupplierAssigned($task);
        }

        if (isset($attributes['reminders']) && is_array($attributes['reminders'])) {
            app(TaskReminderService::class)->syncReminders($task, $attributes['reminders']);
        } elseif ($scheduleChanged) {
            app(TaskReminderService::class)->ensureDefaultReminders($task);
        }

        if (! TaskCalendarSyncContext::isSyncing()) {
            $newStatus = $task->status instanceof TaskStatus
                ? $task->status
                : TaskStatus::tryFrom((string) $task->status);

            if ($task->title !== $previousTitle) {
                app(TaskCalendarLinkService::class)->syncTitleFromTask($task);
            }

            if ($newStatus === TaskStatus::Completed && $previousStatus !== TaskStatus::Completed) {
                app(TaskCalendarLinkService::class)->syncCompletionFromTask($task);
            }
        }

        if ($changedForGoogle) {
            app(GoogleCalendarTaskSyncService::class)->queueSync($task);
        }

        $project = $task->project;
        if ($project !== null) {
            $newStatusEnum = $task->status instanceof TaskStatus
                ? $task->status
                : TaskStatus::tryFrom((string) $task->status);

            if ($previousAssigneeId !== $task->assigned_to) {
                $this->activities->recordUserAction(
                    project: $project,
                    user: $actor,
                    action: ProjectActivityAction::TASK_ASSIGNED,
                    entityType: 'task',
                    entityId: (int) $task->id,
                    description: 'Task assigned',
                    metadata: [
                        'old_assigned_to' => $previousAssigneeId,
                        'assigned_to' => $task->assigned_to,
                        'assignee_name' => $task->assignee?->name,
                    ],
                );
            }

            if ($previousStatus?->value !== $newStatusEnum?->value) {
                $action = $newStatusEnum === TaskStatus::Completed
                    ? ProjectActivityAction::TASK_COMPLETED
                    : ProjectActivityAction::TASK_STATUS_CHANGED;

                $this->activities->recordUserAction(
                    project: $project,
                    user: $actor,
                    action: $action,
                    entityType: 'task',
                    entityId: (int) $task->id,
                    description: $action === ProjectActivityAction::TASK_COMPLETED
                        ? 'Task completed'
                        : 'Task status changed',
                    metadata: [
                        'old_status' => $previousStatus?->value,
                        'new_status' => $newStatusEnum?->value,
                        'title' => $task->title,
                    ],
                    isClientVisible: (bool) $task->is_client_visible,
                );
            } else {
                $this->activities->recordUserAction(
                    project: $project,
                    user: $actor,
                    action: ProjectActivityAction::TASK_UPDATED,
                    entityType: 'task',
                    entityId: (int) $task->id,
                    description: 'Task updated',
                    metadata: [
                        'title' => $task->title,
                    ],
                    isClientVisible: (bool) $task->is_client_visible,
                );
            }
        }

        return $task;
    }

    public function updateStatus(User $actor, Task $task, TaskStatus $status): Task
    {
        $previousStatus = $task->status instanceof TaskStatus
            ? $task->status
            : TaskStatus::tryFrom((string) $task->status);
        $shouldQueueGoogle = (bool) $task->google_sync_enabled;

        $task->update(['status' => $status]);

        $task = $task->fresh(['assignee', 'creator', 'project']);

        if ($task?->project !== null && $previousStatus?->value !== $status->value) {
            $action = $status === TaskStatus::Completed
                ? ProjectActivityAction::TASK_COMPLETED
                : ProjectActivityAction::TASK_STATUS_CHANGED;

            $this->activities->recordUserAction(
                project: $task->project,
                user: $actor,
                action: $action,
                entityType: 'task',
                entityId: (int) $task->id,
                description: $action === ProjectActivityAction::TASK_COMPLETED
                    ? 'Task completed'
                    : 'Task status changed',
                metadata: [
                    'old_status' => $previousStatus?->value,
                    'new_status' => $status->value,
                    'title' => $task->title,
                ],
                isClientVisible: (bool) $task->is_client_visible,
            );
        }

        if (
            ! TaskCalendarSyncContext::isSyncing()
            && $status === TaskStatus::Completed
            && $previousStatus !== TaskStatus::Completed
        ) {
            app(TaskCalendarLinkService::class)->syncCompletionFromTask($task);
        }

        if ($shouldQueueGoogle) {
            app(GoogleCalendarTaskSyncService::class)->queueSync($task);
        }

        return $task;
    }

    /**
     * @return list<User>
     */
    public function assignees(): array
    {
        return User::query()
            ->active()
            ->whereIn('role', UserRole::taskReceiverValues())
            ->orderBy('name')
            ->get()
            ->all();
    }

    private function assertAssignableEmployee(int $userId): User
    {
        $employee = User::query()->find($userId);

        if ($employee === null || ! $employee->role instanceof UserRole) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Selected employee is not assignable.'],
            ]);
        }

        if (! $employee->is_active) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Cannot assign tasks to a deactivated employee.'],
            ]);
        }

        if (! $employee->role->canReceiveAssignedTasks()) {
            throw ValidationException::withMessages([
                'assigned_to' => ['This role cannot receive assigned tasks.'],
            ]);
        }

        return $employee;
    }

    private function assertPhaseAndMilestoneBelongToProject(
        int $projectId,
        mixed $phaseId,
        mixed $milestoneId,
    ): void {
        if ($phaseId !== null && $phaseId !== '') {
            $exists = ProjectPhase::query()
                ->where('project_id', $projectId)
                ->where('id', (int) $phaseId)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'phase_id' => ['Selected phase does not belong to this project.'],
                ]);
            }
        }

        if ($milestoneId !== null && $milestoneId !== '') {
            $exists = ProjectMilestone::query()
                ->where('project_id', $projectId)
                ->where('id', (int) $milestoneId)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'milestone_id' => ['Selected milestone does not belong to this project.'],
                ]);
            }
        }
    }

    /**
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, bool $includeAssignee): void
    {
        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && in_array($status, array_column(TaskStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $priority = $filters['priority'] ?? null;
        if (is_string($priority) && in_array($priority, array_column(TaskPriority::cases(), 'value'), true)) {
            $query->where('priority', $priority);
        }

        $projectId = (int) ($filters['project_id'] ?? 0);
        if ($projectId > 0) {
            $query->where('project_id', $projectId);
        }

        if ($includeAssignee) {
            $assignedTo = (int) ($filters['assigned_to'] ?? 0);
            if ($assignedTo > 0) {
                $query->where('assigned_to', $assignedTo);
            }
        }

        if (($filters['upcoming'] ?? null) === '1' || ($filters['upcoming'] ?? null) === 1) {
            $query->whereNotNull('deadline')
                ->where('status', '!=', TaskStatus::Completed->value)
                ->orderBy('deadline');

            return;
        }

        $query->latest();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return max(1, min($perPage, 50));
    }
}
