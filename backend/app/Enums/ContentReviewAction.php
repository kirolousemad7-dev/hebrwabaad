<?php

namespace App\Enums;

enum ContentReviewAction: string
{
    case Created = 'created';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
    case Published = 'published';
    case Unpublished = 'unpublished';
    case Archived = 'archived';
}
