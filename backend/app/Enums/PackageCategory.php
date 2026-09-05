<?php

namespace App\Enums;

enum PackageCategory: string
{
    case General = 'GENERAL';
    case Marketing = 'MARKETING';
    case Events = 'EVENTS';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
