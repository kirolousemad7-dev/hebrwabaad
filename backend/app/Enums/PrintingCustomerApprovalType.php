<?php

namespace App\Enums;

enum PrintingCustomerApprovalType: string
{
    case Design = 'DESIGN';
    case Size = 'SIZE';
    case Final = 'FINAL';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
