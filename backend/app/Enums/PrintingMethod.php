<?php

namespace App\Enums;

enum PrintingMethod: string
{
    case Digital = 'DIGITAL';
    case Offset = 'OFFSET';
    case OnDemand = 'ON_DEMAND';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
