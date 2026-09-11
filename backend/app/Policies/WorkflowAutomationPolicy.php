<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowAutomation;

class WorkflowAutomationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canManageAutomations();
    }

    public function view(User $user, WorkflowAutomation $automation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WorkflowAutomation $automation): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, WorkflowAutomation $automation): bool
    {
        return $this->viewAny($user);
    }
}
