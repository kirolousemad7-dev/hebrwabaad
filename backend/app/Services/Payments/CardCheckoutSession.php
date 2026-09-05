<?php

namespace App\Services\Payments;

final class CardCheckoutSession
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $url,
        public readonly ?string $providerTransactionId = null,
    ) {}
}
