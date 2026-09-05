<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canManageOrders();
    }

    public function view(User $user, Order $order): bool
    {
        if (! $user->is_active || ! $user->role instanceof UserRole) {
            return false;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        if ($user->role === UserRole::AccountManager) {
            return $order->account_manager_id === $user->id;
        }

        return false;
    }

    public function viewOwned(User $user, Order $order): bool
    {
        return $user->is_active
            && $user->role === UserRole::Customer
            && $order->customer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canManageOrders();
    }

    public function createOwned(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Customer;
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
