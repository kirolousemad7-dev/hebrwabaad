<?php

namespace App\Enums;

enum SupplierQuoteStatus: string
{
    case Requested = 'REQUESTED';
    case Received = 'RECEIVED';
    case UnderReview = 'UNDER_REVIEW';
    case Selected = 'SELECTED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';

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
            self::Requested => 'مطلوب',
            self::Received => 'مستلم',
            self::UnderReview => 'قيد المراجعة',
            self::Selected => 'مختار',
            self::Rejected => 'مرفوض',
            self::Expired => 'منتهي',
        };
    }

    public function isSelectable(): bool
    {
        return in_array($this, [self::Received, self::UnderReview, self::Requested], true);
    }
}
