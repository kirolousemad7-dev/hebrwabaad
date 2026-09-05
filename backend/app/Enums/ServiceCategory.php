<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case Strategy = 'STRATEGY';
    case Content = 'CONTENT';
    case Production = 'PRODUCTION';
    case Stores = 'STORES';
    case Campaigns = 'CAMPAIGNS';
    case Printing = 'PRINTING';
    case Other = 'OTHER';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
