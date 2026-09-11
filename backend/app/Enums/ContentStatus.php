<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case UnderReview = 'UNDER_REVIEW';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case ChangesRequested = 'CHANGES_REQUESTED';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    public function contributorCanEdit(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested, self::Rejected], true);
    }

    public function contributorCanSubmit(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested, self::Rejected], true);
    }

    public function contributorCanDelete(): bool
    {
        return $this === self::Draft;
    }

    public function reviewerCanModerate(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::Approved], true);
    }

    /**
     * Statuses a contributor is never allowed to assign directly.
     *
     * @return list<self>
     */
    public static function publisherOnly(): array
    {
        return [self::Approved, self::Published, self::UnderReview, self::Archived];
    }
}
