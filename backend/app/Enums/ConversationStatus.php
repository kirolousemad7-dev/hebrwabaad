<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case Resolved = 'RESOLVED';
    case Closed = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::InProgress => 'قيد المتابعة',
            self::Resolved => 'تم الحل',
            self::Closed => 'مغلقة',
        };
    }

    /**
     * @return list<self>
     */
    public static function values(): array
    {
        return self::cases();
    }

    public function allowsMessages(): bool
    {
        return $this !== self::Closed;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Resolved, self::Closed],
            self::InProgress => [self::Resolved, self::Closed],
            self::Resolved => [self::InProgress, self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
