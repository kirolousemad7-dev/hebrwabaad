<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSubmission;

class WorkSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ($user->canSubmitEmployeeWork() || $user->canReviewContent());
    }

    public function view(User $user, WorkSubmission $work): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->canReviewContent()) {
            return true;
        }

        return $user->canSubmitEmployeeWork() && (int) $work->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->canSubmitEmployeeWork();
    }

    public function update(User $user, WorkSubmission $work): bool
    {
        return $this->view($user, $work) && $user->canSubmitEmployeeWork() && (int) $work->user_id === (int) $user->id;
    }

    public function submit(User $user, WorkSubmission $work): bool
    {
        return $this->update($user, $work);
    }

    public function delete(User $user, WorkSubmission $work): bool
    {
        return $this->update($user, $work);
    }

    public function review(User $user, WorkSubmission $work): bool
    {
        return $user->is_active && $user->canReviewContent();
    }
}
