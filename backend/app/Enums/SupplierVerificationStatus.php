<?php

namespace App\Enums;

enum SupplierVerificationStatus: string
{
    case Unverified = 'UNVERIFIED';
    case EmailVerified = 'EMAIL_VERIFIED';
    case PhoneVerified = 'PHONE_VERIFIED';
    case FullyVerified = 'FULLY_VERIFIED';
}
