<?php

namespace App\Enums;

enum CmsPageType: string
{
    case General = 'GENERAL';
    case About = 'ABOUT';
    case Contact = 'CONTACT';
    case Policy = 'POLICY';
    case Terms = 'TERMS';
    case Warranty = 'WARRANTY';
    case Shipping = 'SHIPPING';
    case Returns = 'RETURNS';
    case ServicesPolicy = 'SERVICES_POLICY';

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
            self::General => 'عامة',
            self::About => 'من نحن',
            self::Contact => 'تواصل',
            self::Policy => 'سياسة',
            self::Terms => 'شروط',
            self::Warranty => 'ضمان',
            self::Shipping => 'شحن',
            self::Returns => 'استرجاع',
            self::ServicesPolicy => 'سياسات الخدمات',
        };
    }
}
