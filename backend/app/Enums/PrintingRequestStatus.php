<?php

namespace App\Enums;

enum PrintingRequestStatus: string
{
    case Pending = 'PENDING';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
