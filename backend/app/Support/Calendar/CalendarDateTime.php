<?php

namespace App\Support\Calendar;

use Carbon\Carbon;

/**
 * UTC storage helpers for calendar timestamps.
 * Timed events: ISO-8601 instants in UTC.
 * All-day events: store as UTC midnight of the intended calendar date (Y-m-d).
 */
final class CalendarDateTime
{
    public static function normalizeStart(string $value, bool $allDay): Carbon
    {
        if ($allDay) {
            return self::allDayUtcDate($value);
        }

        return Carbon::parse($value)->utc();
    }

    public static function normalizeEnd(?string $value, bool $allDay): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($allDay) {
            return self::allDayUtcDate($value);
        }

        return Carbon::parse($value)->utc();
    }

    /**
     * Parse a date or datetime into UTC midnight for that calendar day (no local shift).
     */
    public static function allDayUtcDate(string $value): Carbon
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches) === 1) {
            return Carbon::createFromFormat('Y-m-d', $matches[1], 'UTC')->startOfDay();
        }

        return Carbon::parse($value)->utc()->startOfDay();
    }

    public static function icsAllDayDate(?Carbon $date): string
    {
        $date ??= now('UTC');

        return $date->copy()->utc()->format('Ymd');
    }
}
