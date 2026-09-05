<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        $role = $user->role;

        return $user->is_active && $role instanceof UserRole && (
            $role->usesEmployeeWorkspace() || $role === UserRole::Owner
        );
    }

    public function view(User $user, Task $task): bool
    {
        if ($task->assigned_to === $user->id || $task->created_by === $user->id) {
            return true;
        }

        $role = $user->role;

        if ($role === UserRole::Owner) {
            return true;
        }

        if ($role === UserRole::AccountManager && $task->project?->account_manager_id === $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role instanceof UserRole && $user->role->canAssignTasks();
    }

    public function update(User $user, Task $task): bool
    {
        return $this->manage($user, $task);
    }

    public function updateStatus(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id;
    }

    public function manage(User $user, Task $task): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        if ($task->created_by === $user->id) {
            return true;
        }

        return $task->project !== null && $task->project->account_manager_id === $user->id;
    }
}
