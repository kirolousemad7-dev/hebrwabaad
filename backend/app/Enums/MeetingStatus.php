<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Scheduled = 'SCHEDULED';
    case Started = 'STARTED';
    case Ended = 'ENDED';
    case Cancelled = 'CANCELLED';

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
            self::Scheduled => 'مجدول',
            self::Started => 'جارٍ',
            self::Ended => 'منتهٍ',
            self::Cancelled => 'ملغى',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Started], true);
    }
}
