<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\OutboundWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutboundWebhookService
{
    public function __construct(
        private readonly OperationsAuditLogger $audit,
        private readonly WebhookDispatcher $dispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{webhook: OutboundWebhook, secret: string}
     */
    public function create(User $actor, array $attributes): array
    {
        $this->assertCanManage($actor);
        $this->assertUrl((string) $attributes['url']);
        $events = $this->normalizeEvents($attributes['events'] ?? []);

        $secret = Str::random(40);
        $webhook = OutboundWebhook::query()->create([
            'name' => $attributes['name'],
            'url' => $attributes['url'],
            'events' => $events,
            'secret_encrypted' => Crypt::encryptString($secret),
            'secret_hint' => substr($secret, -4),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'created_by' => $actor->id,
        ]);

        $this->audit->log($actor, 'webhook.created', $webhook, [
            'events' => $events,
            'url' => $webhook->url,
        ]);

        return ['webhook' => $webhook, 'secret' => $secret];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, OutboundWebhook $webhook, array $attributes): OutboundWebhook
    {
        $this->assertCanManage($actor);

        if (isset($attributes['url'])) {
            $this->assertUrl((string) $attributes['url']);
        }

        if (isset($attributes['events'])) {
            $attributes['events'] = $this->normalizeEvents($attributes['events']);
        }

        $webhook->fill(collect($attributes)->only([
            'name', 'url', 'events', 'is_active',
        ])->all())->save();

        $this->audit->log($actor, 'webhook.updated', $webhook, [
            'changes' => array_keys($attributes),
        ]);

        return $webhook->fresh() ?? $webhook;
    }

    public function delete(User $actor, OutboundWebhook $webhook): void
    {
        $this->assertCanManage($actor);
        $webhook->delete();
        $this->audit->log($actor, 'webhook.deleted', $webhook);
    }

    /**
     * @return array{delivery: WebhookDelivery, webhook: OutboundWebhook}
     */
    public function queueTestDelivery(User $actor, OutboundWebhook $webhook): array
    {
        $this->assertCanManage($actor);

        $event = (string) (($webhook->events[0] ?? null) ?: WorkflowTrigger::OrderCreated->value);
        $delivery = $this->dispatcher->queueDelivery(
            $webhook,
            $event,
            [
                'event' => $event,
                'test' => true,
                'id' => null,
                'status' => 'test',
                'title' => 'Webhook test delivery',
                'reference' => 'test-'.Str::lower(Str::random(8)),
            ],
            'test:'.Str::uuid()->toString(),
        );

        $this->audit->log($actor, 'webhook.test_queued', $webhook, [
            'delivery_id' => $delivery->delivery_id,
            'event' => $event,
        ]);

        return ['delivery' => $delivery, 'webhook' => $webhook];
    }

    public function decryptSecret(OutboundWebhook $webhook): string
    {
        return Crypt::decryptString($webhook->secret_encrypted);
    }

    /**
     * Validate URL against SSRF rules. Allows http in local/testing.
     */
    public function assertUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw ValidationException::withMessages([
                'url' => ['URL must be a valid http(s) address.'],
            ]);
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        $allowHttp = app()->environment(['local', 'testing']);
        if ($scheme === 'https') {
            // ok
        } elseif ($scheme === 'http' && $allowHttp) {
            // ok in local/testing
        } else {
            throw ValidationException::withMessages([
                'url' => ['URL must use https'.($allowHttp ? ' or http' : '').'.'],
            ]);
        }

        if ($this->isBlockedHost($host)) {
            throw ValidationException::withMessages([
                'url' => ['URL host is not allowed.'],
            ]);
        }

        $ips = $this->resolveHostIps($host);
        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw ValidationException::withMessages([
                    'url' => ['URL resolves to a private or blocked address.'],
                ]);
            }
        }
    }

    /**
     * @param  list<mixed>  $events
     * @return list<string>
     */
    public function normalizeEvents(array $events): array
    {
        $allowed = WorkflowTrigger::values();
        $normalized = [];

        foreach ($events as $event) {
            $value = (string) $event;
            if (! in_array($value, $allowed, true)) {
                throw ValidationException::withMessages([
                    'events' => ["Unsupported event: {$value}"],
                ]);
            }
            $normalized[] = $value;
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            throw ValidationException::withMessages([
                'events' => ['At least one event is required.'],
            ]);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(OutboundWebhook $webhook, ?string $plainSecret = null): array
    {
        $data = [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'events' => $webhook->events ?? [],
            'secret_hint' => $webhook->secret_hint,
            'is_active' => (bool) $webhook->is_active,
            'created_by' => $webhook->created_by,
            'last_delivery_at' => $webhook->last_delivery_at?->toIso8601String(),
            'created_at' => $webhook->created_at?->toIso8601String(),
            'updated_at' => $webhook->updated_at?->toIso8601String(),
        ];

        if ($plainSecret !== null) {
            $data['secret'] = $plainSecret;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDelivery(WebhookDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'delivery_id' => $delivery->delivery_id,
            'outbound_webhook_id' => $delivery->outbound_webhook_id,
            'event' => $delivery->event,
            'idempotency_key' => $delivery->idempotency_key,
            'status' => $delivery->status,
            'attempt_count' => $delivery->attempt_count,
            'response_status' => $delivery->response_status,
            'response_summary' => $delivery->response_summary,
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }

    private function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canManageIntegrations()) {
            throw ValidationException::withMessages([
                'webhook' => ['Only Owner or Admin Manager can manage webhooks.'],
            ]);
        }
    }

    private function isBlockedHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || $host === 'metadata.google.internal') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isBlockedIp($host);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        // Skip DNS in unit tests when faking — example.com still resolves publicly.
        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = (string) $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $ips[] = (string) $record['ipv6'];
            }
        }

        if ($ips === []) {
            $ipv4 = @gethostbyname($host);
            if (is_string($ipv4) && $ipv4 !== $host && filter_var($ipv4, FILTER_VALIDATE_IP)) {
                $ips[] = $ipv4;
            }
        }

        return array_values(array_unique($ips));
    }

    private function isBlockedIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === '0.0.0.0') {
            return true;
        }

        if (str_starts_with($ip, '10.')
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '169.254.')
        ) {
            return true;
        }

        // 172.16.0.0 – 172.31.255.255
        if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip) === 1) {
            return true;
        }

        // Common cloud metadata
        if ($ip === '169.254.169.254' || str_starts_with($ip, 'fd') || str_starts_with($ip, 'fc')) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $lower = strtolower($ip);
            if (str_starts_with($lower, 'fe80:') || str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd')) {
                return true;
            }
        }

        return false;
    }
}
