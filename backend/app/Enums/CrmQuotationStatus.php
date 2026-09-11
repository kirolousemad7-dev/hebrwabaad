<?php

namespace App\Enums;

enum CrmQuotationStatus: string
{
    case Draft = 'DRAFT';
    case PendingApproval = 'PENDING_APPROVAL';
    case Approved = 'APPROVED';
    case Sent = 'SENT';
    case Viewed = 'VIEWED';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Approved, self::Sent],
            self::PendingApproval => [self::Approved, self::Draft, self::Rejected],
            self::Approved => [self::Sent, self::Draft],
            self::Sent => [self::Viewed, self::Accepted, self::Rejected, self::Expired],
            self::Viewed => [self::Accepted, self::Rejected, self::Expired],
            self::Accepted, self::Rejected, self::Expired => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
