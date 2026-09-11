<?php

namespace App\Enums;

enum QuotationLineCategory: string
{
    case Creative = 'CREATIVE';
    case Production = 'PRODUCTION';
    case Printing = 'PRINTING';
    case Shipping = 'SHIPPING';
    case Rental = 'RENTAL';
    case Other = 'OTHER';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Creative => 'إبداعي',
            self::Production => 'إنتاج',
            self::Printing => 'طباعة',
            self::Shipping => 'شحن',
            self::Rental => 'تأجير',
            self::Other => 'أخرى',
        };
    }
}
