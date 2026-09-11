<?php

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case Started = 'STARTED';
    case Redirected = 'REDIRECTED';
    case Failed = 'FAILED';
    case Verified = 'VERIFIED';
    case Abandoned = 'ABANDONED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
