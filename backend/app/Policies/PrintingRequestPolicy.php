<?php

namespace App\Policies;

use App\Models\PrintingRequest;
use App\Models\User;

class PrintingRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canReviewPrintingRequests() || $user->role->value === 'CUSTOMER';
    }

    public function view(User $user, PrintingRequest $printingRequest): bool
    {
        if ($user->role->canReviewPrintingRequests()) {
            return true;
        }

        return $user->role->value === 'CUSTOMER' && $printingRequest->user_id === $user->id;
    }

    public function download(User $user, PrintingRequest $printingRequest): bool
    {
        return $this->view($user, $printingRequest);
    }

    public function price(User $user, PrintingRequest $printingRequest): bool
    {
        return $user->role->canReviewPrintingRequests();
    }
}
