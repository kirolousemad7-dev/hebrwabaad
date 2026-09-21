<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MarketingSection;
use App\Models\Media;
use App\Models\User;

class MarketingSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, MarketingSection $marketingSection): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, MarketingSection $marketingSection): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, MarketingSection $marketingSection): bool
    {
        return $this->manages($user);
    }

    public function manageMedia(User $user): bool
    {
        return $this->manages($user);
    }

    public function manageMediaItem(User $user, Media $media): bool
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
