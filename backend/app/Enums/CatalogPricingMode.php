<?php

namespace App\Enums;

enum CatalogPricingMode: string
{
    case Fixed = 'FIXED';
    case StartingFrom = 'STARTING_FROM';
    case Quote = 'QUOTE';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'سعر ثابت',
            self::StartingFrom => 'يبدأ من',
            self::Quote => 'طلب تسعير',
        };
    }

    /**
     * Only a fixed price may be charged without an owner quote.
     */
    public function isChargeable(): bool
    {
        return $this === self::Fixed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
