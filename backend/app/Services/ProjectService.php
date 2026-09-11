<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\Operations\ProjectHealthService;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(
        private readonly ProjectHealthService $healthService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginateFor(User $user, array $filters): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['customer', 'accountManager'])
            ->withCount($this->progressCounts());

        $this->scopeVisibleTo($query, $user);
        $this->applyFilters($query, $filters);

        return $query->paginate($this->perPage($filters));
    }

    public function load(Project $project): Project
    {
        return $project->load(['customer', 'accountManager'])
            ->loadCount($this->progressCounts());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $manager, array $attributes): Project
    {
        $customer = $this->assertAssignableCustomer((int) $attributes['customer_id']);

        $project = Project::query()->create([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
            'status' => $attributes['status'] ?? ProjectStatus::Planning->value,
            'started_at' => $attributes['started_at'] ?? null,
            'deadline' => $attributes['deadline'] ?? null,
        ]);

        return $this->load($project);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Project $project, array $attributes): Project
    {
        $customer = $this->assertAssignableCustomer((int) $attributes['customer_id']);
        $oldStatus = $project->status instanceof ProjectStatus
            ? $project->status->value
            : (string) $project->status;
        $newStatus = (string) $attributes['status'];

        $project->update([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'customer_id' => $customer->id,
            'status' => $attributes['status'],
            'started_at' => $attributes['started_at'] ?? null,
            'deadline' => $attributes['deadline'] ?? null,
        ]);

        if ($oldStatus !== $newStatus) {
            try {
                app(WorkflowAutomationEngine::class)->dispatch('project.status_changed', [
                    'source_type' => 'project',
                    'source_id' => $project->id,
                    'title' => 'تحديث حالة مشروع: '.$project->title,
                    'related_type' => 'project',
                    'related_id' => $project->id,
                    'assignee_ids' => array_filter([$project->account_manager_id]),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'payload' => [
                        'project_id' => $project->id,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ],
                ], $oldStatus.'->'.$newStatus);
            } catch (\Throwable) {
                // Workflow hooks must never break project updates.
            }
        }

        return $this->load($project->fresh());
    }

    /**
     * @param  list<array{user_id: int, role?: string}>  $members
     */
    public function syncMembers(Project $project, array $members): Project
    {
        DB::transaction(function () use ($project, $members): void {
            $keep = [];
            foreach ($members as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $user = User::query()->find($userId);
                if ($user === null || ! $user->is_active) {
                    throw ValidationException::withMessages([
                        'members' => ['One or more members are invalid.'],
                    ]);
                }

                $role = (string) ($row['role'] ?? 'member');
                if (! in_array($role, ['manager', 'member'], true)) {
                    $role = 'member';
                }

                ProjectMember::query()->updateOrCreate(
                    ['project_id' => $project->id, 'user_id' => $userId],
                    ['role' => $role],
                );
                $keep[] = $userId;
            }

            ProjectMember::query()
                ->where('project_id', $project->id)
                ->when($keep !== [], fn ($q) => $q->whereNotIn('user_id', $keep), fn ($q) => $q)
                ->delete();
        });

        return $project->fresh(['members.user:id,name,role']) ?? $project->load(['members.user:id,name,role']);
    }

    /**
     * @return array{status: string, label: string, overdue_workspace_tasks: int, overdue_calendar_tasks: int, days_to_deadline: ?int}
     */
    public function health(Project $project): array
    {
        return $this->healthService->evaluate($project);
    }

    /**
     * @return list<User>
     */
    public function customers(?string $search = null): array
    {
        $query = User::query()
            ->active()
            ->where('role', UserRole::Customer)
            ->orderBy('name');

        $term = is_string($search) ? trim($search) : '';
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query->limit(100)->get()->all();
    }

    public function assertManagedBy(User $user, int $projectId): Project
    {
        $project = Project::query()->find($projectId);

        if ($project === null) {
            throw ValidationException::withMessages([
                'project_id' => ['Selected project is not available.'],
            ]);
        }

        if ($project->account_manager_id !== $user->id) {
            throw ValidationException::withMessages([
                'project_id' => ['You cannot assign tasks to this project.'],
            ]);
        }

        return $project;
    }

    /**
     * @return array<int, string|\Closure>
     */
    private function progressCounts(): array
    {
        return [
            'tasks',
            'tasks as todo_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Todo->value),
            'tasks as in_progress_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::InProgress->value),
            'tasks as review_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Review->value),
            'tasks as revision_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Revision->value),
            'tasks as completed_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Completed->value),
            'tasks as overdue_tasks_count' => fn (Builder $query) => $query
                ->where('status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', now()->toDateString()),
        ];
    }

    /**
     * @param  Builder<Project>  $query
     */
    private function scopeVisibleTo(Builder $query, User $user): void
    {
        $role = $user->role;

        if ($role === UserRole::Owner) {
            return;
        }

        if ($role instanceof UserRole && $role->canManageProjects()) {
            $query->where('account_manager_id', $user->id);

            return;
        }

        $query->whereHas('tasks', function (Builder $tasks) use ($user): void {
            $tasks->where('assigned_to', $user->id);
        });
    }

    /**
     * @param  Builder<Project>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
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
        if (is_string($status) && in_array($status, array_column(ProjectStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
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

    private function assertAssignableCustomer(int $userId): User
    {
        $customer = User::query()->find($userId);

        if ($customer === null || $customer->role !== UserRole::Customer) {
            throw ValidationException::withMessages([
                'customer_id' => ['Selected customer is not valid.'],
            ]);
        }

        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer_id' => ['Cannot attach a deactivated customer.'],
            ]);
        }

        return $customer;
    }
}
