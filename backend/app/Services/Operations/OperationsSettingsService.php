<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Enums\WorkflowRunStatus;
use App\Models\BusinessCalendar;
use App\Models\OperationsSetting;
use App\Models\OutboundWebhook;
use App\Models\User;
use App\Models\WorkflowAutomationRun;
use App\Services\Delivery\DeliveryProviderManager;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\PayTabsCallbackMetrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class OperationsSettingsService
{
    public const DEFAULTS = [
        'printing_approaching_days' => 2,
        'quiet_hours_defaults' => [
            'enabled' => false,
            'start' => '22:00',
            'end' => '07:00',
            'timezone' => 'Asia/Riyadh',
        ],
    ];

    public function __construct(
        private readonly BusinessCalendarService $calendars,
        private readonly OperationsAuditLogger $audit,
        private readonly PaymentProviderManager $paymentProviders,
        private readonly DeliveryProviderManager $deliveryProviders,
        private readonly PayTabsCallbackMetrics $callbackMetrics,
    ) {}

    /**
     * Thin aggregate for Owner settings surface.
     *
     * @return array<string, mixed>
     */
    public function show(User $actor): array
    {
        $this->assertCanManage($actor);

        $stored = $this->allStored();
        $defaultCalendar = BusinessCalendar::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->withCount('holidays')
            ->first()
            ?? BusinessCalendar::query()->where('is_active', true)->withCount('holidays')->orderBy('id')->first();

        return [
            'business_calendar' => $defaultCalendar !== null
                ? $this->calendars->serialize($defaultCalendar)
                : null,
            'printing_approaching_days' => max(0, (int) ($stored['printing_approaching_days'] ?? self::DEFAULTS['printing_approaching_days'])),
            'webhook_count' => OutboundWebhook::query()->count(),
            'automation_failure_24h' => WorkflowAutomationRun::query()
                ->where('status', WorkflowRunStatus::Failed->value)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'quiet_hours_defaults' => $this->quietHoursDefaults($stored),
            'quiet_hours_note' => 'Per-user quiet hours stay on notification preferences; these are org defaults only.',
            'unified_work_driver' => (string) config('operations.unified_work_driver', 'sql'),
            'unified_work_driver_note' => 'Change via OPERATIONS_UNIFIED_WORK_DRIVER in .env (sql|php).',
            'paytabs_available' => $this->paymentProviders->paytabsAvailable(),
            'paytabs_config' => $this->paymentProviders->paytabsConfiguration(),
            'paytabs_callback_metrics' => $this->callbackMetrics->snapshot(),
            'paytabs_docs_path' => 'backend/docs/paytabs-go-live.md',
            'mail_enabled' => $this->mailEnabled(),
            'delivery_modes' => ['pickup', 'manual_delivery'],
            'delivery_providers' => [
                'available' => $this->deliveryProviders->available(),
                'default' => strtolower($this->deliveryProviders->defaultProvider()),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(User $actor, array $payload): array
    {
        $this->assertCanManage($actor);

        if (array_key_exists('printing_approaching_days', $payload)) {
            $days = max(0, min(30, (int) $payload['printing_approaching_days']));
            $this->put('printing_approaching_days', $days);
        }

        if (array_key_exists('quiet_hours_defaults', $payload) && is_array($payload['quiet_hours_defaults'])) {
            $current = $this->quietHoursDefaults($this->allStored());
            $incoming = $payload['quiet_hours_defaults'];
            $merged = [
                'enabled' => array_key_exists('enabled', $incoming)
                    ? (bool) $incoming['enabled']
                    : (bool) $current['enabled'],
                'start' => isset($incoming['start']) ? (string) $incoming['start'] : (string) $current['start'],
                'end' => isset($incoming['end']) ? (string) $incoming['end'] : (string) $current['end'],
                'timezone' => isset($incoming['timezone']) && is_string($incoming['timezone']) && $incoming['timezone'] !== ''
                    ? $incoming['timezone']
                    : (string) $current['timezone'],
            ];
            $this->put('quiet_hours_defaults', $merged);
        }

        if (array_key_exists('default_business_calendar_id', $payload)) {
            $calendarId = $payload['default_business_calendar_id'];
            if ($calendarId !== null) {
                $calendar = BusinessCalendar::query()->findOrFail((int) $calendarId);
                BusinessCalendar::query()->where('is_default', true)->update(['is_default' => false]);
                $calendar->forceFill(['is_default' => true, 'is_active' => true])->save();
            }
        }

        if (array_key_exists('unified_work_driver', $payload)) {
            throw ValidationException::withMessages([
                'unified_work_driver' => ['Set OPERATIONS_UNIFIED_WORK_DRIVER in .env; this value is not writable via API.'],
            ]);
        }

        $this->audit->log($actor, 'operations_settings.updated', null, [
            'keys' => array_keys($payload),
        ]);

        Cache::forget('operations.settings');

        return $this->show($actor);
    }

    public function printingApproachingDays(): int
    {
        return max(0, (int) ($this->allStored()['printing_approaching_days'] ?? self::DEFAULTS['printing_approaching_days']));
    }

    /**
     * @return array<string, mixed>
     */
    private function allStored(): array
    {
        return Cache::remember('operations.settings', 60, function (): array {
            $out = self::DEFAULTS;
            $rows = OperationsSetting::query()->pluck('value', 'key')->all();

            foreach ($rows as $key => $value) {
                $out[$key] = is_array($value) && array_key_exists('value', $value)
                    ? $value['value']
                    : $value;
            }

            return $out;
        });
    }

    private function put(string $key, mixed $value): void
    {
        OperationsSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value]],
        );
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{enabled: bool, start: string, end: string, timezone: string}
     */
    private function quietHoursDefaults(array $stored): array
    {
        $defaults = self::DEFAULTS['quiet_hours_defaults'];
        $value = $stored['quiet_hours_defaults'] ?? $defaults;
        if (! is_array($value)) {
            return $defaults;
        }

        return [
            'enabled' => (bool) ($value['enabled'] ?? $defaults['enabled']),
            'start' => (string) ($value['start'] ?? $defaults['start']),
            'end' => (string) ($value['end'] ?? $defaults['end']),
            'timezone' => (string) ($value['timezone'] ?? $defaults['timezone']),
        ];
    }

    private function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole)
            || ! in_array($actor->role, [UserRole::Owner, UserRole::AdminManager], true)
        ) {
            throw ValidationException::withMessages([
                'settings' => ['Only Owner or Admin Manager can manage operations settings.'],
            ]);
        }
    }

    public function mailEnabled(): bool
    {
        $mailer = strtolower(trim((string) config('mail.default')));
        if ($mailer === '' || in_array($mailer, ['log', 'array', 'null'], true)) {
            return false;
        }

        $from = trim((string) config('mail.from.address'));

        return $from !== '';
    }
}
