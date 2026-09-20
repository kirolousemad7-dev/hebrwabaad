<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ManagedFile;
use App\Models\User;

class ManagedFilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role instanceof UserRole;
    }

    public function view(User $user, ManagedFile $file): bool
    {
        if (! $user->is_active || ! $user->role instanceof UserRole) {
            return false;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        if ($user->role === UserRole::Customer) {
            return $this->customerOwns($user, $file);
        }

        return $this->staffCanAccess($user, $file);
    }

    public function download(User $user, ManagedFile $file): bool
    {
        return $this->view($user, $file);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role instanceof UserRole;
    }

    /**
     * Publishing / unpublishing for customers — Owner or managing Account Manager only.
     * Project members and task assignees can view files but cannot mark them client-visible.
     */
    public function updateClientVisibility(User $user, ManagedFile $file): bool
    {
        if (! $user->is_active || ! $user->role instanceof UserRole) {
            return false;
        }

        if ($user->role === UserRole::Customer) {
            return false;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        if ($user->role !== UserRole::AccountManager) {
            return false;
        }

        $file->loadMissing(['project', 'order']);

        if ($file->project && (int) $file->project->account_manager_id === (int) $user->id) {
            return true;
        }

        return $file->order !== null
            && (int) $file->order->account_manager_id === (int) $user->id;
    }

    private function customerOwns(User $user, ManagedFile $file): bool
    {
        if (! $file->is_client_visible) {
            return false;
        }

        $file->loadMissing(['project', 'order']);

        if ($file->project && $file->project->customer_id === $user->id) {
            return true;
        }

        return $file->order !== null && $file->order->customer_id === $user->id;
    }

    private function staffCanAccess(User $user, ManagedFile $file): bool
    {
        $file->loadMissing(['project', 'order', 'task']);

        if ($user->role === UserRole::AccountManager) {
            if ($file->project) {
                if ($file->project->account_manager_id === $user->id) {
                    return true;
                }

                if ($file->project->members()->where('user_id', $user->id)->exists()) {
                    return true;
                }
            }

            return $file->order !== null && $file->order->account_manager_id === $user->id;
        }

        if ($file->task && $file->task->assigned_to === $user->id) {
            return true;
        }

        if ($file->project === null) {
            return false;
        }

        if ($file->project->members()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return $file->project->tasks()->where('assigned_to', $user->id)->exists();
    }
}
