<?php

namespace App\Enums;

enum PaymentRefundStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Confirmed = 'CONFIRMED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Confirmed, self::Failed, self::Cancelled], true);
    }

    public function countsAgainstPaid(): bool
    {
        return $this === self::Confirmed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
