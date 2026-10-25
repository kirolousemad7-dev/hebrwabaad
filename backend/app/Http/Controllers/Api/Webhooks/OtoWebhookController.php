<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\OtoWebhookEvent;
use App\Services\Shipping\OtoShipmentService;
use App\Services\Shipping\OtoSignature;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OtoWebhookController extends Controller
{
    public function __construct(private readonly OtoShipmentService $shipments) {}

    public function handle(Request $request): JsonResponse
    {
        if (! config('oto.enabled')) {
            return ApiResponse::error('OTO shipping is disabled.', 404);
        }

        $secret = (string) config('oto.webhook_secret');
        if ($secret === '') {
            Log::warning('oto.webhook_rejected', ['reason' => 'missing_secret']);

            return ApiResponse::error('Invalid webhook signature.', 401);
        }

        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = $request->all();
        }

        $orderId = isset($payload['orderId']) ? (string) $payload['orderId'] : '';
        $timestamp = isset($payload['timestamp']) ? (string) $payload['timestamp'] : '';
        $signature = isset($payload['signature']) ? (string) $payload['signature'] : '';
        $type = $this->eventType($payload);

        if ($orderId === '' || $timestamp === '' || $signature === '' || $type === null) {
            Log::warning('oto.webhook_rejected', ['reason' => 'invalid_payload']);

            return ApiResponse::error('Invalid webhook signature.', 401);
        }

        if (! $this->freshTimestamp($timestamp)) {
            Log::warning('oto.webhook_rejected', ['reason' => 'stale_timestamp']);

            return ApiResponse::error('Invalid webhook signature.', 401);
        }

        $expected = $type === 'shipmentError'
            ? OtoSignature::shipmentError($orderId, (string) ($payload['errorCode'] ?? ''), $timestamp, $secret)
            : OtoSignature::orderStatus($orderId, (string) ($payload['status'] ?? ''), $timestamp, $secret);

        if (! OtoSignature::matches($signature, $expected)) {
            Log::warning('oto.webhook_rejected', ['reason' => 'bad_signature', 'type' => $type]);

            return ApiResponse::error('Invalid webhook signature.', 401);
        }

        $marker = $type === 'shipmentError'
            ? (string) ($payload['errorCode'] ?? '')
            : (string) ($payload['status'] ?? '');
        $key = hash('sha256', $type.'|'.$orderId.'|'.$marker.'|'.$timestamp);

        if (OtoWebhookEvent::query()->where('idempotency_key', $key)->exists()) {
            return ApiResponse::success(['duplicate' => true]);
        }

        try {
            $event = OtoWebhookEvent::query()->create([
                'event_type' => $type,
                'oto_order_id' => $orderId,
                'idempotency_key' => $key,
                'payload' => $payload,
                'signature' => mb_substr($signature, 0, 255),
                'processed_at' => null,
            ]);
        } catch (QueryException) {
            return ApiResponse::success(['duplicate' => true]);
        }

        $this->shipments->applyWebhook($type, $payload);
        $event->forceFill(['processed_at' => now()])->save();

        return ApiResponse::success(['accepted' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventType(array $payload): ?string
    {
        if (array_key_exists('errorCode', $payload) || array_key_exists('errorMessage', $payload)) {
            return 'shipmentError';
        }

        if (array_key_exists('status', $payload)) {
            return 'orderStatus';
        }

        return null;
    }

    private function freshTimestamp(string $timestamp): bool
    {
        if (preg_match('/^\d+$/', $timestamp) !== 1) {
            return false;
        }

        $value = (int) $timestamp;
        $seconds = $value > 1_000_000_000_000 ? intdiv($value, 1000) : $value;
        $tolerance = max(30, (int) config('oto.webhook_tolerance_seconds', 900));

        return abs(Carbon::now()->getTimestamp() - $seconds) <= $tolerance;
    }
}
