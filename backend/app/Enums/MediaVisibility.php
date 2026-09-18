<?php

namespace App\Enums;

enum MediaVisibility: string
{
    case Public = 'PUBLIC';
    case Internal = 'INTERNAL';
    case Private = 'PRIVATE';
    case Customer = 'CUSTOMER';
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
            self::Public => 'عام',
            self::Internal => 'داخلي',
            self::Private => 'خاص',
            self::Customer => 'عميل',
            self::Supplier => 'مورد',
        };
    }
}
