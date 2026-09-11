<?php

namespace App\Enums;

enum CalendarVisibility: string
{
    case Private = 'PRIVATE';
    case Participants = 'PARTICIPANTS';
    case Team = 'TEAM';

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
            self::Private => 'خاص',
            self::Participants => 'المشاركون',
            self::Team => 'عام داخل الإدارة',
        };
    }
}
