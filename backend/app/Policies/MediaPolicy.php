<?php

namespace App\Policies;

use App\Enums\MediaVisibility;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\Media;
use App\Models\Meeting;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Task;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Validation\ValidationException;

class MediaPolicy
{
    public function __construct(
        private readonly MediaService $media,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role instanceof UserRole;
    }

    public function view(User $user, Media $media): bool
    {
        if (! $user->is_active || ! $user->role instanceof UserRole) {
            return false;
        }

        if ($user->role === UserRole::Owner || $user->role === UserRole::AdminManager) {
            return true;
        }

        if ((int) $media->uploaded_by === (int) $user->id) {
            return true;
        }

        $visibility = $media->visibility instanceof MediaVisibility
            ? $media->visibility
            : MediaVisibility::tryFrom((string) $media->visibility);

        if ($visibility === MediaVisibility::Public) {
            return true;
        }

        if ($visibility === MediaVisibility::Private) {
            return $user->role === UserRole::Owner || $user->role === UserRole::AdminManager;
        }

        if ($visibility === MediaVisibility::Internal) {
            return $user->role->isStaff();
        }

        $media->loadMissing('owner');
        $owner = $media->owner;

        if ($owner === null) {
            return false;
        }

        if ($visibility === MediaVisibility::Customer) {
            if ($user->role->isStaff()) {
                return true;
            }

            return $user->role === UserRole::Customer && $this->customerLinked($user, $owner);
        }

        if ($visibility === MediaVisibility::Supplier) {
            if ($user->role->isStaff()) {
                return true;
            }

            return $user->role === UserRole::Supplier && $this->supplierLinked($user, $owner);
        }

        return false;
    }

    public function download(User $user, Media $media): bool
    {
        return $this->view($user, $media);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role instanceof UserRole;
    }

    public function update(User $user, Media $media): bool
    {
        return $this->manage($user, $media);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->manage($user, $media);
    }

    public function duplicate(User $user, Media $media): bool
    {
        return $this->manage($user, $media);
    }

    private function manage(User $user, Media $media): bool
    {
        if (! $this->view($user, $media)) {
            return false;
        }

        if ($user->role === UserRole::Owner || $user->role === UserRole::AdminManager) {
            return true;
        }

        if ((int) $media->uploaded_by === (int) $user->id && $user->role?->isStaff()) {
            return true;
        }

        $media->loadMissing('owner');
        if ($media->owner === null) {
            return false;
        }

        try {
            $this->media->assertCanAttach($user, $media->owner);
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    private function customerLinked(User $user, mixed $owner): bool
    {
        if ($owner instanceof User) {
            return (int) $owner->id === (int) $user->id;
        }

        if ($owner instanceof Project) {
            return (int) $owner->customer_id === (int) $user->id;
        }

        if ($owner instanceof CommercialQuotation) {
            return (int) $owner->customer_id === (int) $user->id;
        }

        if ($owner instanceof Payment) {
            return (int) $owner->customer_id === (int) $user->id;
        }

        if ($owner instanceof Meeting) {
            return (int) ($owner->customer_id ?? 0) === (int) $user->id && (bool) $owner->include_customer;
        }

        return false;
    }

    private function supplierLinked(User $user, mixed $owner): bool
    {
        $supplierId = Supplier::query()->where('user_id', $user->id)->value('id');
        if ($supplierId === null) {
            return false;
        }

        if ($owner instanceof Supplier) {
            return (int) $owner->id === (int) $supplierId;
        }

        if ($owner instanceof SupplierProduct) {
            return (int) $owner->supplier_id === (int) $supplierId;
        }

        if ($owner instanceof Task) {
            return (int) ($owner->supplier_id ?? 0) === (int) $supplierId;
        }

        if ($owner instanceof Meeting) {
            return (int) ($owner->supplier_id ?? 0) === (int) $supplierId;
        }

        return false;
    }
}
