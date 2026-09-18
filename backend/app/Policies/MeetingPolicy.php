<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Meetings\MeetingService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MeetingPolicy
{
    public function __construct(private readonly MeetingService $meetings) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role instanceof UserRole;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        try {
            $this->meetings->assertCanViewMeeting($user, $meeting);

            return true;
        } catch (HttpException) {
            return false;
        }
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && ($user->role->usesEmployeeWorkspace() || $user->role === UserRole::Owner || $user->role === UserRole::AdminManager);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->meetings->canManageMeeting($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->meetings->canManageMeeting($user, $meeting);
    }
}
