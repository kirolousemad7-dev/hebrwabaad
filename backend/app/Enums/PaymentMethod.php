<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'CARD';
    case Instapay = 'INSTAPAY';
    case BankTransfer = 'BANK_TRANSFER';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'بطاقة بنكية',
            self::Instapay => 'إنستاباي',
            self::BankTransfer => 'تحويل بنكي',
        };
    }

    public function provider(): string
    {
        return match ($this) {
            self::Card => 'paytabs',
            self::Instapay => 'instapay',
            self::BankTransfer => 'bank_transfer',
        };
    }

    /**
     * Methods the customer settles outside the platform and the owner verifies manually.
     */
    public function isManual(): bool
    {
        return $this === self::Instapay || $this === self::BankTransfer;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
