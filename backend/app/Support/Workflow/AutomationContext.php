<?php

namespace App\Support\Workflow;

/**
 * Request-scoped bag so nested calendar creates inherit automation depth / chain.
 */
class AutomationContext
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $bag = null;

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$bag ?? [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function with(array $context, callable $callback): mixed
    {
        $previous = self::$bag;
        self::$bag = array_merge(self::all(), $context);

        try {
            return $callback();
        } finally {
            self::$bag = $previous;
        }
    }

    public static function clear(): void
    {
        self::$bag = null;
    }
}
