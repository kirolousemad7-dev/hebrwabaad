<?php

namespace App\Enums;

enum PrintingDeliveryStatus: string
{
    case Pending = 'PENDING';
    case Scheduled = 'SCHEDULED';
    case OutForDelivery = 'OUT_FOR_DELIVERY';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد التجهيز',
            self::Scheduled => 'مجدول',
            self::OutForDelivery => 'قيد التوصيل',
            self::Delivered => 'تم التسليم',
            self::Cancelled => 'ملغي',
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
