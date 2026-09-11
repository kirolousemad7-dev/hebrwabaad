<?php

namespace App\Enums;

enum PrintingRequestStatus: string
{
    case Pending = 'PENDING';
    case InProgress = 'IN_PROGRESS';
    case ReadyForDelivery = 'READY_FOR_DELIVERY';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::InProgress => 'قيد التنفيذ',
            self::ReadyForDelivery => 'جاهز للتسليم',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
        };
    }

    /**
     * Open operational statuses (still on the board / overdue-eligible).
     *
     * @return list<self>
     */
    public static function openStatuses(): array
    {
        return [
            self::Pending,
            self::InProgress,
            self::ReadyForDelivery,
        ];
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::openStatuses(),
        );
    }

    public function isOpen(): bool
    {
        return in_array($this, self::openStatuses(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress, self::Cancelled],
            self::InProgress => [self::ReadyForDelivery, self::Cancelled],
            self::ReadyForDelivery => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
