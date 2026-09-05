<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PayTabsClient
{
    public function isConfigured(): bool
    {
        return $this->profileId() > 0
            && $this->serverKey() !== ''
            && $this->baseUrl() !== '';
    }

    public function createHostedPage(Payment $payment, string $returnUrl): CardCheckoutSession
    {
        $this->assertConfigured();

        $payment->loadMissing(['order', 'customer']);
        $cartId = $this->cartId($payment);
        $payload = [
            'profile_id' => $this->profileId(),
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $cartId,
            'cart_currency' => strtoupper((string) $payment->currency),
            'cart_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'cart_description' => mb_substr((string) ($payment->order?->title ?: 'طلب حبر'), 0, 120),
            'paypage_lang' => 'ar',
            'hide_shipping' => true,
            'callback' => $this->callbackUrl(),
            'return' => $returnUrl,
            'payment_methods' => ['creditcard'],
        ];

        $customerDetails = $this->customerDetails($payment);
        if ($customerDetails !== []) {
            $payload['customer_details'] = $customerDetails;
        }

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->withHeaders(['Authorization' => $this->serverKey()])
                ->post($this->paymentRequestUrl(), $payload);
        } catch (ConnectionException) {
            Log::warning('paytabs.request_timeout', ['payment_id' => $payment->id]);
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        if (! $response->successful()) {
            Log::warning('paytabs.request_failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
            ]);
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        $redirectUrl = $response->json('redirect_url');
        $tranRef = $response->json('tran_ref');

        if (! is_string($redirectUrl) || $redirectUrl === '' || ! is_string($tranRef) || $tranRef === '') {
            Log::warning('paytabs.request_incomplete', ['payment_id' => $payment->id]);
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        return new CardCheckoutSession($cartId, $redirectUrl, $tranRef);
    }

    /**
     * @return array<string, mixed>
     */
    public function queryTransaction(string $tranRef): array
    {
        $this->assertConfigured();

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->withHeaders(['Authorization' => $this->serverKey()])
                ->post($this->paymentQueryUrl(), [
                    'profile_id' => $this->profileId(),
                    'tran_ref' => $tranRef,
                ]);
        } catch (ConnectionException) {
            Log::warning('paytabs.query_timeout');
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        if (! $response->successful()) {
            Log::warning('paytabs.query_failed', ['status' => $response->status()]);
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        return $json;
    }

    public function cartId(Payment $payment): string
    {
        $payment->loadMissing('order');
        $reference = $payment->order?->reference ?: 'HEBR-ORD';

        return $reference.'-P'.$payment->id;
    }

    public function paymentIdFromCartId(string $cartId): ?int
    {
        if (preg_match('/-P(\d+)$/', $cartId, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    public function profileId(): int
    {
        return (int) config('payments.paytabs.profile_id');
    }

    public function callbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/webhooks/paytabs';
    }

    public function returnUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/payments/paytabs/return';
    }

    public function paymentRequestUrl(): string
    {
        return $this->baseUrl().'/payment/request';
    }

    public function paymentQueryUrl(): string
    {
        return $this->baseUrl().'/payment/query';
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            Log::warning('paytabs.not_configured');
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function customerDetails(Payment $payment): array
    {
        $customer = $payment->customer;
        $details = [];

        $name = trim((string) ($customer?->name ?? ''));
        if ($name !== '') {
            $details['name'] = $name;
        }

        $email = trim((string) ($customer?->email ?? ''));
        if ($email !== '') {
            $details['email'] = $email;
        }

        $ip = request()?->ip();
        if (is_string($ip) && $ip !== '') {
            $details['ip'] = $ip;
        }

        $country = $this->countryFromCurrency((string) $payment->currency);
        if ($country !== null) {
            $details['country'] = $country;
        }

        return $details;
    }

    private function countryFromCurrency(string $currency): ?string
    {
        return match (strtoupper($currency)) {
            'SAR' => 'SA',
            'EGP' => 'EG',
            default => null,
        };
    }

    private function serverKey(): string
    {
        return (string) config('payments.paytabs.server_key');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('payments.paytabs.base_url'), '/');
    }

    private function timeout(): int
    {
        return max(3, (int) config('payments.paytabs.timeout', 15));
    }
}
