<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\User;

class CalendarItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canAccessWorkCalendar();
    }

    public function view(User $user, CalendarItem $item): bool
    {
        return $this->viewAny($user) && $this->isVisibleTo($user, $item);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CalendarItem $item): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->role instanceof UserRole && $user->role->canManageWorkCalendar()) {
            return true;
        }

        return (int) $item->created_by === (int) $user->id
            || $item->assignees()->where('users.id', $user->id)->exists();
    }

    public function delete(User $user, CalendarItem $item): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->role instanceof UserRole && $user->role->canManageWorkCalendar()) {
            return true;
        }

        return (int) $item->created_by === (int) $user->id;
    }

    public function complete(User $user, CalendarItem $item): bool
    {
        return $this->update($user, $item);
    }

    public function assign(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && ($user->role->canManageWorkCalendar() || $user->role === UserRole::Owner);
    }

    private function isVisibleTo(User $user, CalendarItem $item): bool
    {
        if ($user->role instanceof UserRole && $user->role->canViewTeamCalendar()) {
            return true;
        }

        if ((int) $item->created_by === (int) $user->id) {
            return true;
        }

        return $item->assignees()->where('users.id', $user->id)->exists();
    }
}
