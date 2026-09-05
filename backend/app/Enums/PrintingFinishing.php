<?php

namespace App\Enums;

enum PrintingFinishing: string
{
    case None = 'NONE';
    case Gloss = 'GLOSS';
    case Matte = 'MATTE';
    case Cut = 'CUT';
    case DieCut = 'DIE_CUT';
    case Custom = 'CUSTOM';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
