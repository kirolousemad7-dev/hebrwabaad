<?php

namespace App\Enums;

enum PrintingShape: string
{
    case Rectangle = 'RECTANGLE';
    case Square = 'SQUARE';
    case Circle = 'CIRCLE';
    case Custom = 'CUSTOM';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
