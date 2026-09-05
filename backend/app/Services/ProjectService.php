<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ProjectService
{
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

        $project->update([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'customer_id' => $customer->id,
            'status' => $attributes['status'],
            'started_at' => $attributes['started_at'] ?? null,
            'deadline' => $attributes['deadline'] ?? null,
        ]);

        return $this->load($project->fresh());
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
