<?php

namespace Tests\Support;

use App\Models\Payment;
use App\Services\Payments\CardCheckoutSession;
use App\Services\Payments\CardPaymentGateway;

class FakeCardPaymentGateway implements CardPaymentGateway
{
    public bool $configured = true;

    public int $sessionsCreated = 0;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function createCheckoutSession(Payment $payment, string $successUrl, string $cancelUrl): CardCheckoutSession
    {
        $this->sessionsCreated++;

        return new CardCheckoutSession(
            'HEBR-CART-P'.$payment->id,
            'https://secure-egypt.paytabs.com/payment/page/test_'.$payment->id,
            'TST_FAKE_'.$payment->id,
        );
    }
}
