<?php

namespace App\Services\Printing;

use App\Enums\PrintingRequestStatus;

class PrintingCustomerStatusMapper
{
    public static function label(PrintingRequestStatus|string $status): string
    {
        $resolved = $status instanceof PrintingRequestStatus
            ? $status
            : PrintingRequestStatus::tryFrom((string) $status);

        return match ($resolved) {
            PrintingRequestStatus::Pending => 'جاري تجهيز الطلب',
            PrintingRequestStatus::InProgress => 'جاري التنفيذ',
            PrintingRequestStatus::ReadyForDelivery => 'جاهز للتسليم',
            PrintingRequestStatus::Completed => 'تم التسليم',
            PrintingRequestStatus::Cancelled => 'تم إلغاء الطلب',
            default => 'جاري تجهيز الطلب',
        };
    }

    public static function key(PrintingRequestStatus|string $status): string
    {
        if ($status instanceof PrintingRequestStatus) {
            return $status->value;
        }

        $resolved = PrintingRequestStatus::tryFrom((string) $status);

        return $resolved?->value ?? PrintingRequestStatus::Pending->value;
    }
}
