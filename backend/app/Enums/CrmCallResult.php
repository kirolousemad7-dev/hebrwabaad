<?php

namespace App\Enums;

enum CrmCallResult: string
{
    case Answered = 'ANSWERED';
    case NoAnswer = 'NO_ANSWER';
    case Busy = 'BUSY';
    case WrongNumber = 'WRONG_NUMBER';
    case CallBackLater = 'CALL_BACK_LATER';
    case Interested = 'INTERESTED';
    case NotInterested = 'NOT_INTERESTED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
