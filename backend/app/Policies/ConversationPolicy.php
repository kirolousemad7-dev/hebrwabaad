<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canManageSupport();
    }

    public function view(User $user, Conversation $conversation): bool
    {
        if (! $user->is_active || ! $user->role instanceof UserRole) {
            return false;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        if ($user->role === UserRole::AccountManager) {
            return $this->accountManagerOwns($user, $conversation);
        }

        return false;
    }

    public function viewOwned(User $user, Conversation $conversation): bool
    {
        return $user->is_active
            && $user->role === UserRole::Customer
            && $conversation->customer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Customer;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function replyOwned(User $user, Conversation $conversation): bool
    {
        return $this->viewOwned($user, $conversation);
    }

    public function updateStatus(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    private function accountManagerOwns(User $user, Conversation $conversation): bool
    {
        if ($conversation->assigned_to === $user->id) {
            return true;
        }

        $conversation->loadMissing(['order', 'project']);

        if ($conversation->order && $conversation->order->account_manager_id === $user->id) {
            return true;
        }

        return $conversation->project !== null
            && $conversation->project->account_manager_id === $user->id;
    }
}
