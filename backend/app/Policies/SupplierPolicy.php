<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ($user->canReviewContent() || $user->role === UserRole::Supplier);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->canReviewContent()) {
            return true;
        }

        return $supplier->belongsToUser($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->canReviewContent();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->view($user, $supplier);
    }

    public function publish(User $user, Supplier $supplier): bool
    {
        return $user->is_active && $user->canReviewContent();
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->is_active && $user->canReviewContent();
    }
}
