<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case All = 'all';
    case Tasks = 'tasks';
    case Calendar = 'calendar';
    case Projects = 'projects';
    case Printing = 'printing';
    case Approvals = 'approvals';
    case Automation = 'automation';
    case Crm = 'crm';
    case Alerts = 'alerts';

    /**
     * @return list<string>
     */
    public static function filterableValues(): array
    {
        return array_values(array_filter(
            array_column(self::cases(), 'value'),
            fn (string $value): bool => $value !== self::All->value,
        ));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
