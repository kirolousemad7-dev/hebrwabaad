<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\User;

class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canAccessWorkCalendar();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function decide(User $user, ApprovalRequest $request): bool
    {
        if (! $user->is_active || ! ($user->role instanceof UserRole)) {
            return false;
        }

        if ((int) $request->assigned_to === (int) $user->id) {
            return true;
        }

        return $user->role->canManageApprovals() && $user->role === UserRole::Owner;
    }
}
