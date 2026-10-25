<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Paid = 'PAID';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
    case PendingVerification = 'PENDING_VERIFICATION';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'في انتظار الدفع',
            self::Processing => 'قيد المعالجة',
            self::Paid => 'تم تأكيد الدفع',
            self::Failed => 'فشل الدفع',
            self::Cancelled => 'ملغى',
            self::PendingVerification => 'في انتظار مراجعة التحويل',
            self::Rejected => 'تم رفض إثبات الدفع',
        };
    }

    public function countsAsRevenue(): bool
    {
        return $this === self::Paid;
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::PendingVerification], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Cancelled, self::Rejected], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::PendingVerification, self::Cancelled],
            self::Processing => [self::Paid, self::Failed, self::Cancelled],
            self::PendingVerification => [self::Paid, self::Rejected],
            self::Failed, self::Cancelled => [self::Processing],
            self::Rejected => [self::PendingVerification],
            self::Paid => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
