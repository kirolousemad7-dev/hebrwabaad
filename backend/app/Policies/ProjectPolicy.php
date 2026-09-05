<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role instanceof UserRole && $user->role->canViewAssignedProjects();
    }

    public function view(User $user, Project $project): bool
    {
        $role = $user->role;

        if (! $role instanceof UserRole) {
            return false;
        }

        if ($role === UserRole::Owner) {
            return true;
        }

        if ($role->canManageProjects() && $project->account_manager_id === $user->id) {
            return true;
        }

        if (! $role->canReceiveAssignedTasks()) {
            return false;
        }

        return $project->tasks()->where('assigned_to', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->role instanceof UserRole && $user->role->canManageProjects();
    }

    public function update(User $user, Project $project): bool
    {
        return $this->create($user) && $project->account_manager_id === $user->id;
    }

    public function viewOwned(User $user, Project $project): bool
    {
        return $user->is_active
            && $user->role === UserRole::Customer
            && $project->customer_id === $user->id;
    }
}
