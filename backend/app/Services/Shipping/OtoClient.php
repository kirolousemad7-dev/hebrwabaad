<?php

namespace App\Services\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * OTO API V2 client. The refresh token is exchanged for a one-hour access
 * token and cached. Calls use Bearer auth, time out, and retry only when
 * retrying cannot create a second order or shipment.
 */
class OtoClient
{
    private const CACHE_KEY = 'oto.access_token';

    public function enabled(): bool
    {
        return (bool) config('oto.enabled');
    }

    /**
     * @return array<string, mixed>
     */
    public function healthCheck(): array
    {
        return $this->send('GET', 'healthCheck');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createOrder(array $body): array
    {
        return $this->send('POST', 'createOrder', body: $body, mutating: true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function orderDetails(string $orderId): ?array
    {
        try {
            $payload = $this->send('GET', 'orderDetails', query: ['orderId' => $orderId]);
        } catch (OtoApiException $exception) {
            if ($exception->status === 404 || $exception->otoCode === 'OTO1002') {
                return null;
            }

            throw $exception;
        }

        if (($payload['otoErrorCode'] ?? null) === 'OTO1002') {
            return null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function checkDeliveryFee(array $body): array
    {
        return $this->send('POST', 'checkOTODeliveryFee', body: $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function createShipment(string $orderId, string $deliveryOptionId): array
    {
        return $this->send('POST', 'createShipment', body: [
            'orderId' => $orderId,
            'deliveryOptionId' => ctype_digit($deliveryOptionId) ? (int) $deliveryOptionId : $deliveryOptionId,
        ], mutating: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function orderStatus(string $orderId): array
    {
        return $this->send('POST', 'orderStatus', body: ['orderId' => $orderId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function printAwb(string $orderId): array
    {
        return $this->send('GET', 'print/'.rawurlencode($orderId));
    }

    public function accessToken(bool $force = false): string
    {
        $this->assertEnabled();

        if (! $force) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $refresh = (string) config('oto.refresh_token');
        if ($refresh === '') {
            throw new OtoApiException('رمز الربط مع خدمة الشحن غير مضبوط.', 503);
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->post('refreshToken', ['refresh_token' => $refresh]);
        } catch (ConnectionException) {
            throw new OtoApiException('تعذر الاتصال بخدمة الشحن.', 502);
        }

        if (! $response->successful()) {
            throw new OtoApiException(
                'تعذر تجديد صلاحية الاتصال بخدمة الشحن.',
                $response->status() === 401 ? 401 : 502,
            );
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new OtoApiException('استجابة الشحن لا تحتوي على رمز وصول.', 502);
        }

        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 300);
        Cache::put(self::CACHE_KEY, $token, now()->addSeconds($ttl));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $path,
        array $body = [],
        array $query = [],
        bool $mutating = false,
        int $attempt = 1,
        bool $authRetried = false,
    ): array {
        $token = $this->accessToken();

        try {
            $pending = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeout())
                ->acceptJson()
                ->withToken($token)
                ->asJson();

            $response = $method === 'GET'
                ? $pending->get($path, $query)
                : $pending->post($path, $body);
        } catch (ConnectionException) {
            if (! $mutating && $attempt < 2) {
                $this->pause();

                return $this->send($method, $path, $body, $query, $mutating, $attempt + 1, $authRetried);
            }

            throw new OtoApiException('تعذر الاتصال بخدمة الشحن. لم تُنشأ شحنة.', 502);
        }

        if ($response->status() === 401 && ! $authRetried) {
            Cache::forget(self::CACHE_KEY);
            $this->accessToken(force: true);

            return $this->send($method, $path, $body, $query, $mutating, $attempt, true);
        }

        if ($response->serverError() && ! $mutating && $attempt < 2) {
            $this->pause();

            return $this->send($method, $path, $body, $query, $mutating, $attempt + 1, $authRetried);
        }

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function failure(Response $response): OtoApiException
    {
        $code = $response->json('otoErrorCode');
        $message = $response->json('otoErrorMessage') ?? $response->json('message');
        $safe = is_string($message) ? $this->safeMessage($message) : 'رفضت خدمة الشحن الطلب.';
        $status = $response->status();

        return new OtoApiException(
            $safe,
            $status >= 400 && $status <= 599 ? $status : 502,
            is_string($code) ? $code : null,
        );
    }

    private function safeMessage(string $message): string
    {
        if (preg_match('/token|secret|authorization/i', $message) === 1) {
            return 'رفضت خدمة الشحن الطلب.';
        }

        return mb_substr($message, 0, 300);
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new OtoApiException('تكامل الشحن غير مفعّل.', 503);
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('oto.base_url'), '/');
    }

    private function timeout(): int
    {
        return max(1, (int) config('oto.timeout', 15));
    }

    private function pause(): void
    {
        $milliseconds = max(0, (int) config('oto.retry_sleep_ms', 200));
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
