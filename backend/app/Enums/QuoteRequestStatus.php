<?php

namespace App\Enums;

enum QuoteRequestStatus: string
{
    case New = 'NEW';
    case UnderReview = 'UNDER_REVIEW';
    case NeedsInformation = 'NEEDS_INFORMATION';
    case ReadyToPrice = 'READY_TO_PRICE';
    case Quoted = 'QUOTED';
    case RevisionRequested = 'REVISION_REQUESTED';
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

    public function labelAr(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::UnderReview => 'قيد المراجعة',
            self::NeedsInformation => 'نحتاج معلومات إضافية',
            self::ReadyToPrice => 'جاهز للتسعير',
            self::Quoted => 'تم إرسال عرض السعر',
            self::RevisionRequested => 'طلب تعديل',
            self::Accepted => 'تم القبول',
            self::Rejected => 'مرفوض',
            self::Expired => 'منتهي',
            self::Cancelled => 'ملغي',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Accepted, self::Rejected, self::Expired, self::Cancelled], true);
    }
}
