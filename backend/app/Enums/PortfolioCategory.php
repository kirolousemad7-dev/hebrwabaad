<?php

namespace App\Enums;

enum PortfolioCategory: string
{
    case Web = 'web';
    case Branding = 'branding';
    case Social = 'social';
    case Video = 'video';
    case Marketing = 'marketing';
    case Events = 'events';
    case Printing = 'printing';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
