<?php

namespace App\Enums;

enum CommercialQuotationStatus: string
{
    case Draft = 'DRAFT';
    case Sent = 'SENT';
    case Viewed = 'VIEWED';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isPubliclyActionable(): bool
    {
        return in_array($this, [self::Sent, self::Viewed], true);
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Sent => 'مُرسل',
            self::Viewed => 'تمت المشاهدة',
            self::Accepted => 'مقبول',
            self::Rejected => 'مرفوض',
            self::Expired => 'منتهي',
            self::Cancelled => 'ملغي',
        };
    }
}
