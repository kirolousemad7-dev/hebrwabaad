<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OperationalSavedView;
use App\Models\User;

class OperationalSavedViewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canAccessWorkCalendar();
    }

    public function view(User $user, OperationalSavedView $operationalSavedView): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ((int) $operationalSavedView->user_id === (int) $user->id) {
            return true;
        }

        return (bool) $operationalSavedView->is_shared
            && $user->role instanceof UserRole
            && $user->role->isStaff();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, OperationalSavedView $operationalSavedView): bool
    {
        return $this->viewAny($user)
            && (int) $operationalSavedView->user_id === (int) $user->id;
    }

    public function delete(User $user, OperationalSavedView $operationalSavedView): bool
    {
        return $this->update($user, $operationalSavedView);
    }
}
