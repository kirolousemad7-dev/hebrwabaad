<?php

namespace App\Support\Operations;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Unified identity for workspace Task rows and CalendarItem TASK rows.
 *
 * Formats: task:{id} | calendar:{id} | calendar:{id}:{Y-m-d}
 */
final class UnifiedWorkReference
{
    private function __construct(
        private readonly string $sourceType,
        private readonly int $sourceId,
        private readonly ?Carbon $occurrenceDate,
    ) {}

    public static function parse(string $ref): self
    {
        $value = trim($ref);

        if (preg_match('/^task:(\d+)$/', $value, $matches) === 1) {
            return new self('task', (int) $matches[1], null);
        }

        if (preg_match('/^calendar:(\d+):(\d{4}-\d{2}-\d{2})$/', $value, $matches) === 1) {
            return new self('calendar', (int) $matches[1], Carbon::parse($matches[2])->startOfDay());
        }

        if (preg_match('/^calendar:(\d+)$/', $value, $matches) === 1) {
            return new self('calendar', (int) $matches[1], null);
        }

        throw new InvalidArgumentException('Invalid unified work reference.');
    }

    public static function forTask(int $taskId): self
    {
        return new self('task', $taskId, null);
    }

    public static function forCalendar(int $itemId, ?Carbon $occurrenceDate = null): self
    {
        return new self('calendar', $itemId, $occurrenceDate?->copy()->startOfDay());
    }

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public function sourceId(): int
    {
        return $this->sourceId;
    }

    public function occurrenceDate(): ?Carbon
    {
        return $this->occurrenceDate?->copy();
    }

    public function toString(): string
    {
        if ($this->sourceType === 'task') {
            return 'task:'.$this->sourceId;
        }

        if ($this->occurrenceDate !== null) {
            return 'calendar:'.$this->sourceId.':'.$this->occurrenceDate->toDateString();
        }

        return 'calendar:'.$this->sourceId;
    }

    public function isTask(): bool
    {
        return $this->sourceType === 'task';
    }

    public function isCalendar(): bool
    {
        return $this->sourceType === 'calendar';
    }
}
