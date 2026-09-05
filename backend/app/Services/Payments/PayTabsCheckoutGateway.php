<?php

namespace App\Services\Payments;

use App\Models\Payment;

class PayTabsCheckoutGateway implements CardPaymentGateway
{
    public function __construct(private readonly PayTabsClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function createCheckoutSession(Payment $payment, string $successUrl, string $cancelUrl): CardCheckoutSession
    {
        return $this->client->createHostedPage($payment, $successUrl !== '' ? $successUrl : $this->client->returnUrl());
    }
}
