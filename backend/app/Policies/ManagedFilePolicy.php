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

    private function customerOwns(User $user, ManagedFile $file): bool
    {
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
            if ($file->project && $file->project->account_manager_id === $user->id) {
                return true;
            }

            return $file->order !== null && $file->order->account_manager_id === $user->id;
        }

        if ($file->task && $file->task->assigned_to === $user->id) {
            return true;
        }

        return $file->project !== null
            && $file->project->tasks()->where('assigned_to', $user->id)->exists();
    }
}
