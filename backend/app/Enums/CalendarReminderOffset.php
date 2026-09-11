<?php

namespace App\Enums;

enum CalendarReminderOffset: string
{
    case AtStart = 'AT_START';
    case Minutes5 = 'MINUTES_5';
    case Minutes10 = 'MINUTES_10';
    case Minutes15 = 'MINUTES_15';
    case Minutes30 = 'MINUTES_30';
    case Hour1 = 'HOUR_1';
    case Hours2 = 'HOURS_2';
    case Day1 = 'DAY_1';
    case Day2 = 'DAY_2';
    case Week1 = 'WEEK_1';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function minutesBefore(): int
    {
        return match ($this) {
            self::AtStart => 0,
            self::Minutes5 => 5,
            self::Minutes10 => 10,
            self::Minutes15 => 15,
            self::Minutes30 => 30,
            self::Hour1 => 60,
            self::Hours2 => 120,
            self::Day1 => 1440,
            self::Day2 => 2880,
            self::Week1 => 10080,
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::AtStart => 'عند الموعد',
            self::Minutes5 => 'قبل 5 دقائق',
            self::Minutes10 => 'قبل 10 دقائق',
            self::Minutes15 => 'قبل 15 دقيقة',
            self::Minutes30 => 'قبل 30 دقيقة',
            self::Hour1 => 'قبل ساعة',
            self::Hours2 => 'قبل ساعتين',
            self::Day1 => 'قبل يوم',
            self::Day2 => 'قبل يومين',
            self::Week1 => 'قبل أسبوع',
        };
    }
}
