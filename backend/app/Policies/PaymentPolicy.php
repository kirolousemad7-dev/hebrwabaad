<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesPayments($user);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->managesPayments($user);
    }

    public function viewOwned(User $user, Payment $payment): bool
    {
        return $user->is_active
            && $user->role === UserRole::Customer
            && $payment->customer_id === $user->id;
    }

    public function createOwned(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Customer;
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $this->managesPayments($user);
    }

    public function manageSettings(User $user): bool
    {
        return $this->managesPayments($user);
    }

    private function managesPayments(User $user): bool
    {
        return $user->is_active
            && $user->role instanceof UserRole
            && $user->role->canManagePayments();
    }
}
