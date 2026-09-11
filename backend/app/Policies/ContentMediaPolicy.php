<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ContentMedia;
use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\WorkSubmission;

class ContentMediaPolicy
{
    public function view(?User $user, ContentMedia $media): bool
    {
        if ($media->isPublishedParent()) {
            return true;
        }

        if ($user === null || ! $user->is_active) {
            return false;
        }

        if ($user->canReviewContent() || (int) $media->uploaded_by === (int) $user->id) {
            return true;
        }

        $parent = $media->attachable;

        if ($parent instanceof WorkSubmission) {
            return (int) $parent->user_id === (int) $user->id;
        }

        if ($parent instanceof Supplier) {
            return $parent->belongsToUser($user);
        }

        if ($parent instanceof SupplierPortfolioItem || $parent instanceof SupplierProduct) {
            return $parent->supplier?->belongsToUser($user) === true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->is_active && (
            $user->canSubmitEmployeeWork()
            || $user->canReviewContent()
            || $user->role === UserRole::Supplier
        );
    }
}
