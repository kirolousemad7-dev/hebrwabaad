<?php

namespace App\Enums;

enum CalendarItemStatus: string
{
    case Scheduled = 'SCHEDULED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case Overdue = 'OVERDUE';

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
            self::Scheduled => 'مجدولة',
            self::InProgress => 'قيد التنفيذ',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
            self::Overdue => 'متأخرة',
        };
    }
}
