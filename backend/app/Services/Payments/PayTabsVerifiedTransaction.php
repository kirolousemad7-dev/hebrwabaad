<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;

final class PayTabsVerifiedTransaction
{
    public function __construct(
        public readonly string $tranRef,
        public readonly string $cartId,
        public readonly string $amount,
        public readonly string $currency,
        public readonly int $profileId,
        public readonly string $responseStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayTabsPayload(array $payload): ?self
    {
        $tranRef = self::stringValue($payload['tran_ref'] ?? null);
        $cartId = self::stringValue($payload['cart_id'] ?? null);
        $amount = self::decimalValue($payload['cart_amount'] ?? null);
        $currency = self::stringValue($payload['cart_currency'] ?? null);
        $profileId = self::intValue($payload['profileId'] ?? $payload['profile_id'] ?? null);
        $responseStatus = self::stringValue(
            data_get($payload, 'payment_result.response_status') ?? $payload['response_status'] ?? null
        );

        if ($tranRef === null || $cartId === null || $amount === null || $currency === null || $profileId === null) {
            return null;
        }

        return new self(
            $tranRef,
            $cartId,
            $amount,
            strtoupper($currency),
            $profileId,
            strtoupper($responseStatus ?? ''),
        );
    }

    public function matchesPayment(Payment $payment, int $expectedProfileId): bool
    {
        if ($this->profileId !== $expectedProfileId) {
            return false;
        }

        if ($this->cartId !== (string) $payment->checkout_session_id) {
            return false;
        }

        if (strtoupper((string) $payment->currency) !== $this->currency) {
            return false;
        }

        return $this->amountsEqual((string) $payment->amount, $this->amount);
    }

    public function mismatchReason(Payment $payment, int $expectedProfileId): ?string
    {
        if ($this->profileId !== $expectedProfileId) {
            return 'profile';
        }

        if ($this->cartId !== (string) $payment->checkout_session_id) {
            return 'cart_id';
        }

        if (strtoupper((string) $payment->currency) !== $this->currency) {
            return 'currency';
        }

        if (! $this->amountsEqual((string) $payment->amount, $this->amount)) {
            return 'amount';
        }

        return null;
    }

    public function mappedStatus(): ?PaymentStatus
    {
        return match ($this->responseStatus) {
            'A' => PaymentStatus::Paid,
            'D', 'E' => PaymentStatus::Failed,
            'C' => PaymentStatus::Cancelled,
            default => null,
        };
    }

    private function amountsEqual(string $left, string $right): bool
    {
        return abs(((float) $left) - ((float) $right)) < 0.005;
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function decimalValue(mixed $value): ?string
    {
        if (! is_numeric($value) && ! is_string($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private static function intValue(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
