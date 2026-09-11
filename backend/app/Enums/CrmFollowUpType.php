<?php

namespace App\Enums;

enum CrmFollowUpType: string
{
    case Call = 'CALL';
    case WhatsApp = 'WHATSAPP';
    case Email = 'EMAIL';
    case Meeting = 'MEETING';
    case Demo = 'DEMO';
    case Proposal = 'PROPOSAL';
    case Other = 'OTHER';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
