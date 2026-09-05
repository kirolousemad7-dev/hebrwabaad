<?php

namespace App\Services\Consultant;

use Illuminate\Support\Facades\Cache;

class ConsultantSettings
{
    public const CACHE_KEY = 'consultant.settings';

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $defaults = [
            'enabled' => (bool) config('consultant.enabled', true),
            'provider' => (string) config('consultant.provider', 'rules'),
        ];

        $override = Cache::get(self::CACHE_KEY, []);

        return array_merge($defaults, is_array($override) ? $override : []);
    }

    public static function enabled(): bool
    {
        return (bool) (self::all()['enabled'] ?? true);
    }

    public static function provider(): string
    {
        $provider = (string) (self::all()['provider'] ?? 'rules');

        return in_array($provider, ['rules', 'openai'], true) ? $provider : 'rules';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function update(array $data): array
    {
        $current = Cache::get(self::CACHE_KEY, []);
        $current = is_array($current) ? $current : [];

        if (array_key_exists('enabled', $data)) {
            $current['enabled'] = (bool) $data['enabled'];
        }

        if (array_key_exists('provider', $data) && in_array($data['provider'], ['rules', 'openai'], true)) {
            $current['provider'] = $data['provider'];
        }

        Cache::forever(self::CACHE_KEY, $current);

        return self::all();
    }
}
