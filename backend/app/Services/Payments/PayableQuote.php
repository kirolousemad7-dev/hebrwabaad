<?php

namespace App\Services\Payments;

final class PayableQuote
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
    ) {}

    public function isPayable(): bool
    {
        return (float) $this->amount > 0;
    }
}
