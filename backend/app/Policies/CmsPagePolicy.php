<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CmsPage;
use App\Models\User;

class CmsPagePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, CmsPage $cmsPage): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, CmsPage $cmsPage): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, CmsPage $cmsPage): bool
    {
        return $this->manages($user);
    }

    public function publish(User $user, CmsPage $cmsPage): bool
    {
        return $this->manages($user);
    }

    public function manageFooter(User $user, CmsPage $cmsPage): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && in_array($user->role, [UserRole::Owner, UserRole::AdminManager], true);
    }
}
