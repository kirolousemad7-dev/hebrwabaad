<?php

namespace App\Support;

/**
 * Request-scoped guard that prevents Google Calendar ↔ Task sync recursion.
 */
class GoogleCalendarSyncContext
{
    private static int $depth = 0;

    public static function entering(): void
    {
        self::$depth++;
    }

    public static function isSyncing(): bool
    {
        return self::$depth > 0;
    }

    public static function leave(): void
    {
        self::$depth = max(0, self::$depth - 1);
    }

    public static function clear(): void
    {
        self::$depth = 0;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        self::entering();

        try {
            return $callback();
        } finally {
            self::leave();
        }
    }
}
