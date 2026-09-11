<?php

namespace App\Enums;

enum CalendarSource: string
{
    case Manual = 'MANUAL';
    case Crm = 'CRM';
    case Order = 'ORDER';
    case Project = 'PROJECT';
    case Printing = 'PRINTING';
    case Payment = 'PAYMENT';
    case Content = 'CONTENT';
    case Supplier = 'SUPPLIER';

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
            self::Manual => 'يدوي',
            self::Crm => 'CRM',
            self::Order => 'طلب',
            self::Project => 'مشروع',
            self::Printing => 'طباعة',
            self::Payment => 'دفع',
            self::Content => 'محتوى',
            self::Supplier => 'مورد',
        };
    }
}
