<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Received = 'RECEIVED';
    case Confirmed = 'CONFIRMED';
    case InProgress = 'IN_PROGRESS';
    case Review = 'REVIEW';
    case Revision = 'REVISION';
    case Completed = 'COMPLETED';
    case Delivered = 'DELIVERED';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'تم استلام الطلب',
            self::Confirmed => 'تم تأكيد الطلب',
            self::InProgress => 'قيد التنفيذ',
            self::Review => 'قيد المراجعة',
            self::Revision => 'التعديلات',
            self::Completed => 'مكتمل',
            self::Delivered => 'تم التسليم',
        };
    }

    public function progressPercent(): int
    {
        return match ($this) {
            self::Received => 0,
            self::Confirmed => 16,
            self::InProgress => 33,
            self::Review => 50,
            self::Revision => 66,
            self::Completed => 83,
            self::Delivered => 100,
        };
    }

    /**
     * @return list<self>
     */
    public static function lifecycle(): array
    {
        return [
            self::Received,
            self::Confirmed,
            self::InProgress,
            self::Review,
            self::Revision,
            self::Completed,
            self::Delivered,
        ];
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::Confirmed],
            self::Confirmed => [self::InProgress],
            self::InProgress => [self::Review],
            self::Review => [self::Revision, self::Completed],
            self::Revision => [self::InProgress, self::Review],
            self::Completed => [self::Delivered],
            self::Delivered => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
