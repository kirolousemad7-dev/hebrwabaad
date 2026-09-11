<?php

namespace App\Enums;

enum CrmLeadStatus: string
{
    case New = 'NEW';
    case AttemptedContact = 'ATTEMPTED_CONTACT';
    case Contacted = 'CONTACTED';
    case Qualified = 'QUALIFIED';
    case Unqualified = 'UNQUALIFIED';
    case FollowUp = 'FOLLOW_UP';
    case Interested = 'INTERESTED';
    case ProposalSent = 'PROPOSAL_SENT';
    case Negotiation = 'NEGOTIATION';
    case Won = 'WON';
    case Lost = 'LOST';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
