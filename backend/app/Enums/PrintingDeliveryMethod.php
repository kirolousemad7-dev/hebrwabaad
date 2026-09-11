<?php

namespace App\Enums;

enum PrintingDeliveryMethod: string
{
    case Pickup = 'pickup';
    case ManualDelivery = 'manual_delivery';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'استلام من الفرع',
            self::ManualDelivery => 'توصيل يدوي',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
