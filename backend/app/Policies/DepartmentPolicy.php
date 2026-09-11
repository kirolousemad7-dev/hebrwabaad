<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canAccessWorkCalendar();
    }

    public function view(User $user, Department $department): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canManageDepartments();
    }

    public function update(User $user, Department $department): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->create($user);
    }

    public function assign(User $user): bool
    {
        return $this->create($user);
    }
}
