<?php

namespace App\Services\Crm;

use App\Models\CrmSetting;
use Illuminate\Support\Facades\Cache;

class CrmSettingsService
{
    public const DEFAULTS = [
        'discount_max_percent' => 10,
        'stale_lead_days' => 7,
        'new_lead_sla_minutes' => 60,
        'assignment_mode' => 'manual',
        'round_robin_cursor' => 0,
    ];

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = CrmSetting::query()->pluck('value', 'key')->all();
        $config = self::DEFAULTS;

        foreach ($stored as $key => $value) {
            $config[$key] = is_array($value) && array_key_exists('value', $value)
                ? $value['value']
                : $value;
        }

        return $config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function setMany(array $values): array
    {
        $allowed = array_keys(self::DEFAULTS);

        foreach ($values as $key => $value) {
            if (! in_array($key, $allowed, true) && $key !== 'round_robin_cursor') {
                continue;
            }

            CrmSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => ['value' => $value]],
            );
        }

        Cache::forget('crm.settings');

        return $this->all();
    }

    public function discountMaxPercent(): float
    {
        return (float) $this->get('discount_max_percent', 10);
    }

    public function staleLeadDays(): int
    {
        return max(1, (int) $this->get('stale_lead_days', 7));
    }

    public function newLeadSlaMinutes(): int
    {
        return max(1, (int) $this->get('new_lead_sla_minutes', 60));
    }

    public function assignmentMode(): string
    {
        $mode = (string) $this->get('assignment_mode', 'manual');

        return in_array($mode, ['manual', 'round_robin', 'by_source', 'by_service'], true)
            ? $mode
            : 'manual';
    }
}
