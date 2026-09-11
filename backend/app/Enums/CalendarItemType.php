<?php

namespace App\Enums;

enum CalendarItemType: string
{
    case Task = 'TASK';
    case Meeting = 'MEETING';
    case Appointment = 'APPOINTMENT';
    case FollowUp = 'FOLLOW_UP';
    case Call = 'CALL';
    case Deadline = 'DEADLINE';
    case Reminder = 'REMINDER';
    case Event = 'EVENT';
    case Other = 'OTHER';

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
            self::Task => 'مهمة',
            self::Meeting => 'اجتماع',
            self::Appointment => 'موعد',
            self::FollowUp => 'متابعة',
            self::Call => 'مكالمة',
            self::Deadline => 'موعد نهائي',
            self::Reminder => 'تذكير',
            self::Event => 'فعالية',
            self::Other => 'أخرى',
        };
    }
}
