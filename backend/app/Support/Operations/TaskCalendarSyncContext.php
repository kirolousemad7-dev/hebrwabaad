<?php

namespace App\Support\Operations;

/**
 * Request-scoped guard that prevents Task ↔ Calendar soft-link sync recursion.
 */
class TaskCalendarSyncContext
{
    private static int $depth = 0;

    private static ?string $direction = null;

    public static function entering(string $direction): void
    {
        self::$depth++;
        self::$direction = $direction;
    }

    public static function isSyncing(): bool
    {
        return self::$depth > 0;
    }

    public static function leave(): void
    {
        self::$depth = max(0, self::$depth - 1);

        if (self::$depth === 0) {
            self::$direction = null;
        }
    }

    public static function depth(): int
    {
        return self::$depth;
    }

    public static function direction(): ?string
    {
        return self::$direction;
    }

    public static function clear(): void
    {
        self::$depth = 0;
        self::$direction = null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function with(string $direction, callable $callback): mixed
    {
        self::entering($direction);

        try {
            return $callback();
        } finally {
            self::leave();
        }
    }
}
