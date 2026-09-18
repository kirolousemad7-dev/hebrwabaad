<?php

namespace App\Enums;

enum SupplierVisibility: string
{
    case Internal = 'INTERNAL';
    case Vendor = 'VENDOR';
    case Customer = 'CUSTOMER';
    case Public = 'PUBLIC';
    case Private = 'PRIVATE';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Document visibility allowed for supplier uploads.
     *
     * @return list<string>
     */
    public static function documentValues(): array
    {
        return [
            self::Internal->value,
            self::Vendor->value,
            self::Customer->value,
            self::Public->value,
        ];
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Internal => 'داخلي',
            self::Vendor => 'مورد',
            self::Customer => 'عميل',
            self::Public => 'عام',
            self::Private => 'خاص',
        };
    }
}
