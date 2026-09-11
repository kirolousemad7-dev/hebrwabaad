<?php

namespace App\Enums;

enum CrmActivityType: string
{
    case Call = 'CALL';
    case WhatsApp = 'WHATSAPP';
    case Email = 'EMAIL';
    case Meeting = 'MEETING';
    case VideoMeeting = 'VIDEO_MEETING';
    case Demo = 'DEMO';
    case Proposal = 'PROPOSAL';
    case Note = 'NOTE';
    case FollowUp = 'FOLLOW_UP';
    case Task = 'TASK';
    case StageChange = 'STAGE_CHANGE';
    case Assignment = 'ASSIGNMENT';
    case Conversion = 'CONVERSION';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
