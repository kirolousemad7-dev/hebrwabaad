<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(private readonly ProjectService $projects) {}

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

        $task = Task::query()->create([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'project_id' => (int) $attributes['project_id'],
            'assigned_to' => $assignee->id,
            'created_by' => $creator->id,
            'priority' => $attributes['priority'],
            'status' => $attributes['status'] ?? TaskStatus::Todo->value,
            'deadline' => $attributes['deadline'] ?? null,
        ]);

        $task = $task->load(['assignee', 'creator', 'project']);
        app(PlatformNotifier::class)->taskAssigned($task);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, Task $task, array $attributes): Task
    {
        $this->projects->assertManagedBy($actor, (int) $attributes['project_id']);
        $assignee = $this->assertAssignableEmployee((int) $attributes['assigned_to']);
        $previousAssigneeId = $task->assigned_to;

        $task->update([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'project_id' => (int) $attributes['project_id'],
            'assigned_to' => $assignee->id,
            'priority' => $attributes['priority'],
            'status' => $attributes['status'],
            'deadline' => $attributes['deadline'] ?? null,
        ]);

        $task = $task->fresh(['assignee', 'creator', 'project']);
        app(PlatformNotifier::class)->taskAssigned($task, $previousAssigneeId);

        return $task;
    }

    public function updateStatus(Task $task, TaskStatus $status): Task
    {
        $task->update(['status' => $status]);

        return $task->fresh(['assignee', 'creator', 'project']);
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
