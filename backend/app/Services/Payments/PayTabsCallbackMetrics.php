<?php

namespace App\Services\Payments;

use App\Models\OperationsSetting;
use Illuminate\Support\Facades\Cache;

class PayTabsCallbackMetrics
{
    public const SETTING_KEY = 'paytabs_callback_metrics';

    /**
     * @return array{
     *     callback_received: int,
     *     verified: int,
     *     rejected: int,
     *     mismatch: int,
     *     duplicate: int
     * }
     */
    public function snapshot(): array
    {
        $stored = $this->read();

        return [
            'callback_received' => (int) ($stored['callback_received'] ?? 0),
            'verified' => (int) ($stored['verified'] ?? 0),
            'rejected' => (int) ($stored['rejected'] ?? 0),
            'mismatch' => (int) ($stored['mismatch'] ?? 0),
            'duplicate' => (int) ($stored['duplicate'] ?? 0),
        ];
    }

    public function increment(string $key): void
    {
        $allowed = ['callback_received', 'verified', 'rejected', 'mismatch', 'duplicate'];
        if (! in_array($key, $allowed, true)) {
            return;
        }

        $current = $this->snapshot();
        $current[$key] = ((int) $current[$key]) + 1;

        OperationsSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => ['value' => $current]],
        );

        Cache::forget('operations.settings');
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $row = OperationsSetting::query()->where('key', self::SETTING_KEY)->first();
        if ($row === null) {
            return [];
        }

        $value = $row->value;
        if (is_array($value) && array_key_exists('value', $value) && is_array($value['value'])) {
            return $value['value'];
        }

        return is_array($value) ? $value : [];
    }
}
