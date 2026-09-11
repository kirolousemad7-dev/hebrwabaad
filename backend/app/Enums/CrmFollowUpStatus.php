<?php

namespace App\Enums;

enum CrmFollowUpStatus: string
{
    case Scheduled = 'SCHEDULED';
    case Completed = 'COMPLETED';
    case Missed = 'MISSED';
    case Overdue = 'OVERDUE';
    case Rescheduled = 'RESCHEDULED';
    case Cancelled = 'CANCELLED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
