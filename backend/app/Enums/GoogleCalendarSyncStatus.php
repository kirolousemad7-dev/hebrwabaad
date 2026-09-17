<?php

namespace App\Enums;

enum GoogleCalendarSyncStatus: string
{
    case None = 'NONE';
    case Pending = 'PENDING';
    case Synced = 'SYNCED';
    case Error = 'ERROR';
    case Disabled = 'DISABLED';
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
            self::None => 'غير مربوط',
            self::Pending => 'قيد المزامنة',
            self::Synced => 'متزامن',
            self::Error => 'خطأ في المزامنة',
            self::Disabled => 'المزامنة متوقفة',
            self::Cancelled => 'ملغى في التقويم',
        };
    }
}
