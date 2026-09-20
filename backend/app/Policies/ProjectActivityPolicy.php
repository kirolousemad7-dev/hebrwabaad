<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Support\ProjectActivityAction;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectActivityPolicy
{
    use HandlesAuthorization;

    /**
     * Staff/owner: reuse ProjectPolicy::view.
     * Customer: reuse ProjectPolicy::viewOwned and only client-visible rows.
     */
    public function viewAny(User $user, Project $project): bool
    {
        if (! $user->is_active || ! $user->role instanceof UserRole) {
            return false;
        }

        if ($user->role === UserRole::Customer) {
            return $user->can('viewOwned', $project);
        }

        return $user->can('view', $project);
    }

    public function view(User $user, ProjectActivity $activity): bool
    {
        $activity->loadMissing('project');

        if ($activity->project === null) {
            return false;
        }

        if (! $this->viewAny($user, $activity->project)) {
            return false;
        }

        if ($user->role === UserRole::Customer) {
            return $activity->is_client_visible
                && in_array($activity->action, ProjectActivityAction::customerSafeActions(), true);
        }

        return true;
    }
}
