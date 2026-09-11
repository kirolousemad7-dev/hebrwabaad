<?php

namespace App\Enums;

enum PrintingQuotationStatus: string
{
    case Draft = 'DRAFT';
    case Sent = 'SENT';
    case Viewed = 'VIEWED';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Accepted,
            self::Rejected,
            self::Expired,
            self::Cancelled,
        ], true);
    }

    public function isPubliclyActionable(): bool
    {
        return in_array($this, [self::Sent, self::Viewed], true);
    }
}
