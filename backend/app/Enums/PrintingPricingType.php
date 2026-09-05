<?php

namespace App\Enums;

enum PrintingPricingType: string
{
    case Estimated = 'ESTIMATED';
    case QuoteRequired = 'QUOTE_REQUIRED';
    case QuoteReady = 'QUOTE_READY';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
