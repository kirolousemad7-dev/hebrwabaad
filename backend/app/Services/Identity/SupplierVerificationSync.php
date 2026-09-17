<?php

namespace App\Services\Identity;

use App\Enums\SupplierVerificationStatus;
use App\Models\Supplier;

class SupplierVerificationSync
{
    public function sync(Supplier $supplier): Supplier
    {
        $emailOk = $supplier->email_verified_at !== null
            || ($supplier->user?->email_verified_at !== null);
        $phoneOk = $supplier->phone_verified_at !== null;

        $status = match (true) {
            $emailOk && $phoneOk => SupplierVerificationStatus::FullyVerified,
            $emailOk => SupplierVerificationStatus::EmailVerified,
            $phoneOk => SupplierVerificationStatus::PhoneVerified,
            default => SupplierVerificationStatus::Unverified,
        };

        $supplier->forceFill([
            'verification_status' => $status,
            'email_verified_at' => $emailOk
                ? ($supplier->email_verified_at ?? $supplier->user?->email_verified_at ?? now())
                : null,
        ])->save();

        return $supplier->refresh();
    }
}
