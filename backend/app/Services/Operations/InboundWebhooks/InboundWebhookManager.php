<?php

namespace App\Services\Operations\InboundWebhooks;

use App\Enums\PaymentStatus;
use App\Models\InboundWebhookIntegration;
use App\Models\InboundWebhookReceipt;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Strict signed inbound webhooks (Phase 6N).
 *
 * Allowlisted integration_type only — handler classes are hardcoded, never from request.
 * PayTabs remains on its dedicated /api/webhooks/paytabs route.
 */
class InboundWebhookManager
{
    public const TYPE_GENERIC_HMAC = 'generic_hmac';

    public const MAX_TIMESTAMP_SKEW_SECONDS = 300;

    /** @var list<string> */
    public const ALLOWED_TYPES = [
        self::TYPE_GENERIC_HMAC,
    ];

    /** @var list<string> */
    public const GENERIC_HMAC_EVENTS = [
        'ping',
        'payment.confirm',
    ];

    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    /**
     * @return list<string>
     */
    public static function allowedTypes(): array
    {
        return self::ALLOWED_TYPES;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(InboundWebhookIntegration $integration, Request $request): array
    {
        if (! $integration->is_active || $integration->trashed()) {
            throw new HttpException(404, 'Integration not found.');
        }

        if (! in_array($integration->integration_type, self::ALLOWED_TYPES, true)) {
            throw new HttpException(422, 'Unsupported integration type.');
        }

        return match ($integration->integration_type) {
            self::TYPE_GENERIC_HMAC => $this->handleGenericHmac($integration, $request),
            default => throw new HttpException(422, 'Unsupported integration type.'),
        };
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function handleGenericHmac(InboundWebhookIntegration $integration, Request $request): array
    {
        $rawBody = $request->getContent();
        $timestamp = trim((string) $request->header('X-Hebr-Timestamp', ''));
        $signature = trim((string) $request->header('X-Hebr-Signature', ''));
        $deliveryId = trim((string) $request->header('X-Hebr-Delivery', ''));

        if ($deliveryId === '' || strlen($deliveryId) > 191) {
            return $this->reject($integration, 'missing_delivery', 401, [
                'event' => (string) ($request->input('event') ?? 'unknown'),
                'delivery_id' => $deliveryId !== '' ? $deliveryId : 'invalid-'.uniqid('', true),
            ]);
        }

        $existing = InboundWebhookReceipt::query()->where('delivery_id', $deliveryId)->first();
        if ($existing !== null) {
            return [
                'status' => (int) ($existing->response_status ?: 200),
                'body' => [
                    'ok' => $existing->status === InboundWebhookReceipt::STATUS_PROCESSED,
                    'replay' => true,
                    'delivery_id' => $existing->delivery_id,
                    'result' => $existing->result_summary,
                ],
            ];
        }

        if (! $this->verifyGenericHmacSignature($integration, $rawBody, $timestamp, $signature)) {
            return $this->storeRejected(
                $integration,
                $deliveryId,
                (string) ($request->input('event') ?? 'unknown'),
                'invalid_signature',
                401,
                $this->payloadMeta($request->all()),
            );
        }

        $payload = $request->all();
        $event = (string) ($payload['event'] ?? '');

        if (! in_array($event, self::GENERIC_HMAC_EVENTS, true)) {
            return $this->storeRejected(
                $integration,
                $deliveryId,
                $event !== '' ? $event : 'unknown',
                'unknown_event',
                422,
                $this->payloadMeta($payload),
            );
        }

        try {
            $result = match ($event) {
                'ping' => ['pong' => true],
                'payment.confirm' => $this->confirmPayment($payload),
                default => throw ValidationException::withMessages(['event' => ['Unsupported event.']]),
            };
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Validation failed.';

            return $this->storeRejected(
                $integration,
                $deliveryId,
                $event,
                is_string($message) ? $message : 'Validation failed.',
                422,
                $this->payloadMeta($payload),
            );
        } catch (HttpException $e) {
            return $this->storeRejected(
                $integration,
                $deliveryId,
                $event,
                $e->getMessage(),
                $e->getStatusCode(),
                $this->payloadMeta($payload),
            );
        }

        $receipt = InboundWebhookReceipt::query()->create([
            'inbound_webhook_integration_id' => $integration->id,
            'integration_type' => $integration->integration_type,
            'event' => $event,
            'delivery_id' => $deliveryId,
            'status' => InboundWebhookReceipt::STATUS_PROCESSED,
            'response_status' => 200,
            'result_summary' => is_string($result['summary'] ?? null)
                ? $result['summary']
                : 'processed',
            'payload_meta' => $this->payloadMeta($payload),
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'replay' => false,
                'delivery_id' => $receipt->delivery_id,
                'event' => $event,
                'result' => $result,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function confirmPayment(array $payload): array
    {
        $paymentId = (int) ($payload['payment_id'] ?? 0);
        if ($paymentId < 1) {
            throw ValidationException::withMessages([
                'payment_id' => ['payment_id is required.'],
            ]);
        }

        $providerTxn = isset($payload['provider_transaction_id'])
            ? trim((string) $payload['provider_transaction_id'])
            : null;

        $payment = $this->payments->confirmFromInboundWebhook(
            $paymentId,
            $providerTxn !== '' ? $providerTxn : null,
        );

        return [
            'summary' => 'payment_confirmed:'.$payment->id,
            'payment_id' => $payment->id,
            'status' => $payment->status instanceof PaymentStatus
                ? $payment->status->value
                : (string) $payment->status,
        ];
    }

    private function verifyGenericHmacSignature(
        InboundWebhookIntegration $integration,
        string $rawBody,
        string $timestamp,
        string $signature,
    ): bool {
        if ($timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        $ts = (int) $timestamp;
        if (abs(now()->timestamp - $ts) > self::MAX_TIMESTAMP_SKEW_SECONDS) {
            return false;
        }

        if ($signature === '') {
            return false;
        }

        $secret = Crypt::decryptString($integration->secret_encrypted);
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadMeta(array $payload): array
    {
        $meta = [
            'event' => isset($payload['event']) ? (string) $payload['event'] : null,
            'payment_id' => isset($payload['payment_id']) ? (int) $payload['payment_id'] : null,
        ];

        // Phase 6O correlation
        if (isset($payload['external_reference']) && is_scalar($payload['external_reference'])) {
            $meta['external_reference'] = substr((string) $payload['external_reference'], 0, 191);
        }

        return array_filter($meta, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{status: int, body: array<string, mixed>}
     */
    private function storeRejected(
        InboundWebhookIntegration $integration,
        string $deliveryId,
        string $event,
        string $summary,
        int $status,
        array $meta,
    ): array {
        try {
            DB::transaction(function () use ($integration, $deliveryId, $event, $summary, $status, $meta): void {
                InboundWebhookReceipt::query()->create([
                    'inbound_webhook_integration_id' => $integration->id,
                    'integration_type' => $integration->integration_type,
                    'event' => substr($event, 0, 80),
                    'delivery_id' => $deliveryId,
                    'status' => InboundWebhookReceipt::STATUS_REJECTED,
                    'response_status' => $status,
                    'result_summary' => substr($summary, 0, 500),
                    'payload_meta' => $meta,
                    'received_at' => now(),
                    'processed_at' => now(),
                ]);
            });
        } catch (\Throwable) {
            // Unique race on delivery_id — treat as replay of rejection.
            $existing = InboundWebhookReceipt::query()->where('delivery_id', $deliveryId)->first();
            if ($existing !== null) {
                return [
                    'status' => (int) ($existing->response_status ?: $status),
                    'body' => [
                        'ok' => false,
                        'replay' => true,
                        'delivery_id' => $existing->delivery_id,
                        'error' => $existing->result_summary,
                    ],
                ];
            }
        }

        return [
            'status' => $status,
            'body' => [
                'ok' => false,
                'replay' => false,
                'delivery_id' => $deliveryId,
                'error' => $summary,
            ],
        ];
    }

    /**
     * @param  array{event?: string, delivery_id: string}  $context
     * @return array{status: int, body: array<string, mixed>}
     */
    private function reject(
        InboundWebhookIntegration $integration,
        string $summary,
        int $status,
        array $context,
    ): array {
        return $this->storeRejected(
            $integration,
            $context['delivery_id'],
            (string) ($context['event'] ?? 'unknown'),
            $summary,
            $status,
            [],
        );
    }
}
