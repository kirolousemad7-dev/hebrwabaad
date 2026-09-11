<?php

namespace App\Services\Operations;

use App\Jobs\ProcessWebhookDelivery;
use App\Models\OutboundWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WebhookDispatcher
{
    /** Minutes to wait after failed attempts 1–4 before the next try. */
    public const BACKOFF_MINUTES = [1, 5, 30, 120];

    public const MAX_ATTEMPTS = 5;

    /**
     * Queue deliveries for every active webhook subscribed to the event.
     *
     * @param  array<string, mixed>  $context
     */
    public function queue(string $event, array $context, ?string $sourceKey = null): int
    {
        $payload = $this->safePayload($event, $context);
        $sourceKey ??= $this->sourceKey($context, $event);

        $webhooks = OutboundWebhook::query()
            ->where('is_active', true)
            ->whereJsonContains('events', $event)
            ->orderBy('id')
            ->get();

        $queued = 0;
        foreach ($webhooks as $webhook) {
            try {
                $this->queueDelivery($webhook, $event, $payload, $sourceKey);
                $queued++;
            } catch (Throwable $e) {
                Log::warning('Webhook queue skipped', [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $queued;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queueDelivery(
        OutboundWebhook $webhook,
        string $event,
        array $payload,
        string $sourceKey,
    ): WebhookDelivery {
        $idempotencyKey = sprintf('%d|%s|%s', $webhook->id, $event, $sourceKey);

        $existing = WebhookDelivery::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $delivery = WebhookDelivery::query()->create([
            'delivery_id' => (string) Str::uuid(),
            'outbound_webhook_id' => $webhook->id,
            'event' => $event,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempt_count' => 0,
            'next_retry_at' => now(),
        ]);

        ProcessWebhookDelivery::dispatch($delivery->id);

        return $delivery;
    }

    public function processDue(int $limit = 50): int
    {
        $ids = WebhookDelivery::query()
            ->where('status', WebhookDelivery::STATUS_PENDING)
            ->where(function ($q): void {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $processed = 0;
        foreach ($ids as $id) {
            $this->attempt((int) $id);
            $processed++;
        }

        return $processed;
    }

    public function attempt(int $deliveryId): void
    {
        $delivery = WebhookDelivery::query()->with('webhook')->find($deliveryId);
        if ($delivery === null || $delivery->status !== WebhookDelivery::STATUS_PENDING) {
            return;
        }

        $webhook = $delivery->webhook;
        if ($webhook === null || $webhook->trashed() || ! $webhook->is_active) {
            $delivery->forceFill([
                'status' => WebhookDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'response_summary' => 'Webhook inactive or deleted',
            ])->save();

            return;
        }

        $delivery->increment('attempt_count');
        $delivery->refresh();

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $secret = Crypt::decryptString($webhook->secret_encrypted);
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Hebr-Event' => $delivery->event,
                    'X-Hebr-Delivery' => $delivery->delivery_id,
                    'X-Hebr-Signature' => $signature,
                    'User-Agent' => 'Hebr-OutboundWebhook/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $summary = Str::limit(trim($response->body()), 500, '');
            $status = $response->status();

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => WebhookDelivery::STATUS_DELIVERED,
                    'response_status' => $status,
                    'response_summary' => $summary !== '' ? $summary : null,
                    'delivered_at' => now(),
                    'next_retry_at' => null,
                    'failed_at' => null,
                ])->save();

                $webhook->forceFill(['last_delivery_at' => now()])->save();

                return;
            }

            $this->scheduleRetryOrFail($delivery, $status, $summary !== '' ? $summary : "HTTP {$status}");
        } catch (Throwable $e) {
            $this->scheduleRetryOrFail($delivery, null, Str::limit($e->getMessage(), 500, ''));
        }
    }

    private function scheduleRetryOrFail(WebhookDelivery $delivery, ?int $responseStatus, string $summary): void
    {
        if ($delivery->attempt_count >= self::MAX_ATTEMPTS) {
            $delivery->forceFill([
                'status' => WebhookDelivery::STATUS_FAILED,
                'response_status' => $responseStatus,
                'response_summary' => $summary,
                'failed_at' => now(),
                'next_retry_at' => null,
            ])->save();

            return;
        }

        $delayIndex = $delivery->attempt_count - 1;
        $minutes = self::BACKOFF_MINUTES[$delayIndex] ?? end(self::BACKOFF_MINUTES);

        $delivery->forceFill([
            'response_status' => $responseStatus,
            'response_summary' => $summary,
            'next_retry_at' => now()->addMinutes((int) $minutes),
        ])->save();
    }

    /**
     * Allowlisted payload fields only — no secrets, tokens, or CRM financials.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function safePayload(string $event, array $context): array
    {
        $keys = ['id', 'status', 'title', 'reference', 'source_type', 'source_id', 'related_type', 'related_id'];

        // Order events may include a non-sensitive total when present as order_total.
        if (str_starts_with($event, 'order.')) {
            $keys[] = 'order_total';
            $keys[] = 'total';
        }

        $payload = [
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
        ];

        $bag = array_merge(
            $context,
            is_array($context['payload'] ?? null) ? $context['payload'] : [],
        );

        foreach ($keys as $key) {
            if (! array_key_exists($key, $bag)) {
                continue;
            }
            $value = $bag[$key];
            if (is_scalar($value) || $value === null) {
                $payload[$key] = $value;
            }
        }

        if (! isset($payload['id']) && isset($bag['source_id']) && is_scalar($bag['source_id'])) {
            $payload['id'] = $bag['source_id'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sourceKey(array $context, string $event): string
    {
        $type = (string) ($context['source_type'] ?? 'none');
        $id = isset($context['source_id']) ? (string) $context['source_id'] : '0';
        $extra = isset($context['idempotency_suffix'])
            ? (string) $context['idempotency_suffix']
            : substr(hash('sha256', json_encode($context) ?: $event), 0, 16);

        return $type.':'.$id.':'.$extra;
    }
}
