<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\Printing\RefundImpactService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentRefundService
{
    public function __construct(
        private readonly PayTabsClient $paytabs,
        private readonly RefundImpactService $refundImpact,
    ) {}

    public function refundableAmount(Payment $payment): string
    {
        if ($payment->status !== PaymentStatus::Paid) {
            return '0.00';
        }

        $paid = $this->money((string) $payment->amount);
        $refunded = $this->confirmedRefundTotal($payment);

        return bccomp($paid, $refunded, 2) === 1
            ? bcsub($paid, $refunded, 2)
            : '0.00';
    }

    /**
     * Net commercial amount still retained after confirmed refunds (for display / eligibility).
     * Never mutates Payment.amount — commercial amount stays immutable.
     */
    public function netPaid(Payment $payment): string
    {
        return $this->refundableAmount($payment);
    }

    public function confirmedRefundTotal(Payment $payment): string
    {
        $sum = PaymentRefund::query()
            ->where('payment_id', $payment->id)
            ->where('status', PaymentRefundStatus::Confirmed->value)
            ->sum('amount');

        return $this->money((string) $sum);
    }

    /**
     * @return list<PaymentRefund>
     */
    public function listForPayment(User $actor, Payment $payment): array
    {
        $this->assertCanRefund($actor);

        return PaymentRefund::query()
            ->with('requester:id,name,email')
            ->where('payment_id', $payment->id)
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /**
     * @return array{refund: PaymentRefund, payment: Payment}
     */
    public function requestRefund(
        User $actor,
        Payment $payment,
        string $amount,
        ?string $reason = null,
        bool $manual = false,
    ): array {
        $this->assertCanRefund($actor);

        $amount = $this->money($amount);
        if (bccomp($amount, '0.00', 2) !== 1) {
            throw ValidationException::withMessages([
                'amount' => ['Refund amount must be greater than zero.'],
            ]);
        }

        if ($payment->status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => ['Only paid payments can be refunded.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $payment, $amount, $reason, $manual): array {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $originalAmount = (string) $locked->amount;

            if ($this->hasOpenRefund($locked)) {
                throw ValidationException::withMessages([
                    'refund' => ['A refund is already pending or processing for this payment.'],
                ]);
            }

            $refundable = $this->refundableAmount($locked);
            if (bccomp($amount, $refundable, 2) === 1) {
                throw ValidationException::withMessages([
                    'amount' => ['Refund amount exceeds the refundable balance.'],
                ]);
            }

            $method = $locked->payment_method instanceof PaymentMethod
                ? $locked->payment_method
                : PaymentMethod::from((string) $locked->payment_method);

            $usePayTabs = ! $manual
                && $method === PaymentMethod::Card
                && $this->paytabs->isConfigured()
                && filled($locked->provider_transaction_id);

            if ($manual || ! $usePayTabs) {
                if (! $manual && $method === PaymentMethod::Card && ! $this->paytabs->isConfigured()) {
                    throw ValidationException::withMessages([
                        'refund' => ['PayTabs is not configured. Use a manual refund or configure PayTabs.'],
                    ]);
                }

                $refund = PaymentRefund::query()->create([
                    'payment_id' => $locked->id,
                    'provider' => $manual || $method->isManual() ? 'manual' : ($locked->provider ?: 'manual'),
                    'amount' => $amount,
                    'currency' => strtoupper((string) $locked->currency),
                    'status' => PaymentRefundStatus::Pending,
                    'reason' => $reason,
                    'is_manual' => true,
                    'requested_by' => $actor->id,
                    'requested_at' => now(),
                ]);

                // Immutable commercial amount — never mutate Payment.amount.
                $locked->refresh();
                if ((string) $locked->amount !== $originalAmount) {
                    throw ValidationException::withMessages([
                        'payment' => ['Payment amount is immutable and must not change during refunds.'],
                    ]);
                }

                return [
                    'refund' => $refund->fresh(['requester']) ?? $refund,
                    'payment' => $locked->fresh() ?? $locked,
                ];
            }

            $refund = PaymentRefund::query()->create([
                'payment_id' => $locked->id,
                'provider' => 'paytabs',
                'amount' => $amount,
                'currency' => strtoupper((string) $locked->currency),
                'status' => PaymentRefundStatus::Processing,
                'reason' => $reason,
                'is_manual' => false,
                'requested_by' => $actor->id,
                'requested_at' => now(),
            ]);

            $cartId = $this->refundCartId($locked, $refund);
            $description = mb_substr($reason ?: ('Refund for payment #'.$locked->id), 0, 120);

            try {
                $response = $this->paytabs->refundTransaction(
                    (string) $locked->provider_transaction_id,
                    $amount,
                    strtoupper((string) $locked->currency),
                    $cartId,
                    $description,
                );
            } catch (HttpException $exception) {
                $refund->update([
                    'status' => PaymentRefundStatus::Failed,
                    'failed_at' => now(),
                    'failure_code' => (string) $exception->getStatusCode(),
                    'failure_message' => 'PayTabs refund request failed.',
                    'metadata' => ['cart_id' => $cartId],
                ]);

                Log::warning('paytabs.refund_request_failed', [
                    'payment_id' => $locked->id,
                    'refund_id' => $refund->id,
                ]);

                return [
                    'refund' => $refund->fresh(['requester']) ?? $refund,
                    'payment' => $locked->fresh() ?? $locked,
                ];
            }

            $responseStatus = strtoupper((string) (
                data_get($response, 'payment_result.response_status')
                ?? $response['response_status']
                ?? ''
            ));
            $tranRef = (string) ($response['tran_ref'] ?? '');

            if ($responseStatus === 'A') {
                $refund->update([
                    'status' => PaymentRefundStatus::Confirmed,
                    'provider_refund_reference' => $tranRef !== '' ? $tranRef : null,
                    'processed_at' => now(),
                    'metadata' => [
                        'cart_id' => $cartId,
                        'response_status' => $responseStatus,
                    ],
                ]);

                $this->refundImpact->handleAfterRefund($locked->fresh() ?? $locked, $refund->fresh() ?? $refund);
            } else {
                $refund->update([
                    'status' => PaymentRefundStatus::Failed,
                    'provider_refund_reference' => $tranRef !== '' ? $tranRef : null,
                    'failed_at' => now(),
                    'failure_code' => $responseStatus !== '' ? $responseStatus : 'UNKNOWN',
                    'failure_message' => 'PayTabs declined the refund.',
                    'metadata' => [
                        'cart_id' => $cartId,
                        'response_status' => $responseStatus,
                    ],
                ]);
            }

            $locked->refresh();
            if ((string) $locked->amount !== $originalAmount) {
                throw ValidationException::withMessages([
                    'payment' => ['Payment amount is immutable and must not change during refunds.'],
                ]);
            }

            return [
                'refund' => $refund->fresh(['requester']) ?? $refund,
                'payment' => $locked->fresh() ?? $locked,
            ];
        });
    }

    /**
     * @return array{refund: PaymentRefund, payment: Payment}
     */
    public function confirmManual(User $actor, PaymentRefund $refund): array
    {
        $this->assertCanRefund($actor);

        if (! $refund->is_manual) {
            throw ValidationException::withMessages([
                'refund' => ['Only manual refunds can be confirmed this way.'],
            ]);
        }

        $status = $refund->status instanceof PaymentRefundStatus
            ? $refund->status
            : PaymentRefundStatus::from((string) $refund->status);

        if (! in_array($status, [PaymentRefundStatus::Pending, PaymentRefundStatus::Processing], true)) {
            throw ValidationException::withMessages([
                'refund' => ['This refund cannot be confirmed.'],
            ]);
        }

        return DB::transaction(function () use ($refund): array {
            /** @var PaymentRefund $lockedRefund */
            $lockedRefund = PaymentRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($lockedRefund->payment_id)->lockForUpdate()->firstOrFail();
            $originalAmount = (string) $payment->amount;

            $refundable = $this->refundableAmount($payment);
            if (bccomp((string) $lockedRefund->amount, $refundable, 2) === 1) {
                throw ValidationException::withMessages([
                    'amount' => ['Refund amount exceeds the refundable balance.'],
                ]);
            }

            $lockedRefund->update([
                'status' => PaymentRefundStatus::Confirmed,
                'processed_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ]);

            $this->refundImpact->handleAfterRefund($payment->fresh() ?? $payment, $lockedRefund->fresh() ?? $lockedRefund);

            $payment->refresh();
            if ((string) $payment->amount !== $originalAmount) {
                throw ValidationException::withMessages([
                    'payment' => ['Payment amount is immutable and must not change during refunds.'],
                ]);
            }

            return [
                'refund' => $lockedRefund->fresh(['requester']) ?? $lockedRefund,
                'payment' => $payment->fresh() ?? $payment,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(PaymentRefund $refund): array
    {
        $status = $refund->status instanceof PaymentRefundStatus
            ? $refund->status->value
            : (string) $refund->status;

        return [
            'id' => $refund->id,
            'payment_id' => $refund->payment_id,
            'provider' => $refund->provider,
            'provider_refund_reference' => $refund->provider_refund_reference,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'status' => $status,
            'reason' => $refund->reason,
            'is_manual' => (bool) $refund->is_manual,
            'requested_by' => $refund->requested_by,
            'requester' => $refund->requester ? [
                'id' => $refund->requester->id,
                'name' => $refund->requester->name,
            ] : null,
            'requested_at' => $refund->requested_at?->toIso8601String(),
            'processed_at' => $refund->processed_at?->toIso8601String(),
            'failed_at' => $refund->failed_at?->toIso8601String(),
            'failure_code' => $refund->failure_code,
            'failure_message' => $refund->failure_message,
            'created_at' => $refund->created_at?->toIso8601String(),
        ];
    }

    private function hasOpenRefund(Payment $payment): bool
    {
        return PaymentRefund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', [
                PaymentRefundStatus::Pending->value,
                PaymentRefundStatus::Processing->value,
            ])
            ->exists();
    }

    private function refundCartId(Payment $payment, PaymentRefund $refund): string
    {
        $base = (string) ($payment->checkout_session_id ?: ('P'.$payment->id));

        return $base.'-RF'.$refund->id;
    }

    private function assertCanRefund(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canRefundPayments()) {
            abort(403);
        }
    }

    private function money(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
