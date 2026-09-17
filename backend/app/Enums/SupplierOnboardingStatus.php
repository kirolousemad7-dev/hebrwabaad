<?php

namespace App\Enums;

enum SupplierOnboardingStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case InReview = 'IN_REVIEW';
    case Completed = 'COMPLETED';
    case Rejected = 'REJECTED';
}
