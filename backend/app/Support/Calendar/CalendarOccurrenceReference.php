<?php

namespace App\Support\Calendar;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Distinguishes real calendar_items rows from virtual recurrence occurrences.
 *
 * Virtual identity format (API list only): "{itemId}:{Y-m-d}"
 * Mutations must always target the real item id + optional occurrence_at.
 */
final class CalendarOccurrenceReference
{
    private function __construct(
        private readonly int $itemId,
        private readonly ?Carbon $occurrenceAt,
        private readonly bool $virtual,
    ) {}

    public static function forRealItem(int $itemId): self
    {
        return new self($itemId, null, false);
    }

    public static function forOccurrence(int $itemId, Carbon $occurrenceAt): self
    {
        return new self($itemId, $occurrenceAt->copy(), true);
    }

    /**
     * Parse a route/API id that may be numeric or "{id}:{Y-m-d}".
     */
    public static function parse(string|int $raw, ?string $occurrenceAtHint = null): self
    {
        $value = trim((string) $raw);

        if ($value === '' || ! preg_match('/^\d+(?::\d{4}-\d{2}-\d{2})?$/', $value)) {
            throw new InvalidArgumentException('Invalid calendar occurrence reference.');
        }

        if (str_contains($value, ':')) {
            [$idPart, $datePart] = explode(':', $value, 2);

            return self::forOccurrence((int) $idPart, Carbon::parse($datePart)->startOfDay());
        }

        $itemId = (int) $value;
        if ($occurrenceAtHint !== null && $occurrenceAtHint !== '') {
            return self::forOccurrence($itemId, Carbon::parse($occurrenceAtHint));
        }

        return self::forRealItem($itemId);
    }

    public function itemId(): int
    {
        return $this->itemId;
    }

    public function occurrenceAt(): ?Carbon
    {
        return $this->occurrenceAt?->copy();
    }

    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    public function toListId(): string|int
    {
        if ($this->virtual && $this->occurrenceAt !== null) {
            return $this->itemId.':'.$this->occurrenceAt->toDateString();
        }

        return $this->itemId;
    }

    public function occurrenceDateString(): ?string
    {
        return $this->occurrenceAt?->toDateString();
    }
}
