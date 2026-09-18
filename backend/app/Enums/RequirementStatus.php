<?php

namespace App\Enums;

enum RequirementStatus: string
{
    case New = 'NEW';
    case Qualified = 'QUALIFIED';
    case InProgress = 'IN_PROGRESS';
    case Converted = 'CONVERTED';
    case Closed = 'CLOSED';

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
            self::New => 'جديد',
            self::Qualified => 'مؤهل',
            self::InProgress => 'قيد المتابعة',
            self::Converted => 'محوّل',
            self::Closed => 'مغلق',
        };
    }
}
