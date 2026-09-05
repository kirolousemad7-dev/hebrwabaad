<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface CardPaymentGateway
{
    public function isConfigured(): bool;

    public function createCheckoutSession(Payment $payment, string $successUrl, string $cancelUrl): CardCheckoutSession;
}
