<?php

namespace App\Support\Calendar;

use App\Enums\CalendarItemType;

/**
 * Transparent workload thresholds and time-block conflict rules.
 */
final class CalendarWorkloadRules
{
    public const LIGHT_MAX = 4;

    public const MEDIUM_MAX = 9;

    /**
     * @return 'light'|'medium'|'heavy'
     */
    public static function level(int $openTaskCount, int $urgentCount = 0, int $overdueCount = 0): string
    {
        $score = $openTaskCount + ($urgentCount * 2) + ($overdueCount * 2);

        if ($score <= self::LIGHT_MAX) {
            return 'light';
        }

        if ($score <= self::MEDIUM_MAX) {
            return 'medium';
        }

        return 'heavy';
    }

    public static function levelLabelAr(string $level): string
    {
        return match ($level) {
            'light' => 'خفيف',
            'medium' => 'متوسط',
            'heavy' => 'مرتفع',
            default => $level,
        };
    }

    /**
     * Types that occupy a calendar time slot for conflict detection.
     *
     * @return list<string>
     */
    public static function timeBlockingTypes(): array
    {
        return [
            CalendarItemType::Meeting->value,
            CalendarItemType::Appointment->value,
            CalendarItemType::Call->value,
            CalendarItemType::Event->value,
        ];
    }
}
