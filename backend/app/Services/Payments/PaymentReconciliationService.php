<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Support\Str;

class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentRefundService $refunds,
    ) {}

    /**
     * Card payments needing staff attention (printing + orders), categorized for V2.
     *
     * Categories: processing_too_long, amount_mismatch, currency_mismatch,
     * failed_verification, refund_mismatch, processing, failed.
     *
     * @return array{items: list<array<string, mixed>>, meta: array{total: int, categories: array<string, int>}}
     */
    public function listNeedingAttention(User $actor, int $limit = 50): array
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canManagePayments()) {
            abort(403);
        }

        $payments = Payment::query()
            ->with([
                'customer:id,name,email',
                'order:id,reference,title',
                'printingQuotation:id,reference,revision,printing_request_id',
                'refunds',
            ])
            ->where('payment_method', PaymentMethod::Card->value)
            ->where(function ($query): void {
                $query->where('status', PaymentStatus::Processing->value)
                    ->orWhere('status', PaymentStatus::Failed->value)
                    ->orWhere(function ($inner): void {
                        $inner->where('status', PaymentStatus::Paid->value)
                            ->whereHas('refunds', function ($refundQuery): void {
                                $refundQuery->whereIn('status', [
                                    PaymentRefundStatus::Pending->value,
                                    PaymentRefundStatus::Processing->value,
                                    PaymentRefundStatus::Failed->value,
                                ]);
                            });
                    });
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $items = $payments->map(fn (Payment $payment): array => $this->serialize($payment))->values()->all();
        $categories = [];
        foreach ($items as $item) {
            $cat = (string) ($item['category'] ?? 'unknown');
            $categories[$cat] = ($categories[$cat] ?? 0) + 1;
        }

        return [
            'items' => $items,
            'meta' => [
                'total' => count($items),
                'categories' => $categories,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Payment $payment): array
    {
        $status = $payment->status instanceof PaymentStatus
            ? $payment->status->value
            : (string) $payment->status;

        $category = $this->categorize($payment);
        $attentionReason = match ($category) {
            'processing_too_long' => 'processing_too_long',
            'amount_mismatch', 'currency_mismatch', 'failed_verification' => $category,
            'refund_mismatch' => 'refund_mismatch',
            default => $status === PaymentStatus::Processing->value ? 'processing' : 'failed',
        };

        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $status,
            'provider' => $payment->provider,
            'provider_status' => $payment->provider_status,
            'provider_transaction_id' => $payment->provider_transaction_id,
            'checkout_session_id' => $payment->checkout_session_id,
            'order_id' => $payment->order_id,
            'printing_quotation_id' => $payment->printing_quotation_id,
            'customer' => $payment->customer ? [
                'id' => $payment->customer->id,
                'name' => $payment->customer->name,
            ] : null,
            'order' => $payment->order ? [
                'id' => $payment->order->id,
                'reference' => $payment->order->reference,
                'title' => $payment->order->title,
            ] : null,
            'printing_quotation' => $payment->printingQuotation ? [
                'id' => $payment->printingQuotation->id,
                'reference' => $payment->printingQuotation->reference,
                'revision' => $payment->printingQuotation->revision,
                'printing_request_id' => $payment->printingQuotation->printing_request_id,
            ] : null,
            'failure_reason' => $this->sanitizeProviderMessage($payment->failure_reason),
            'reconciliation_note' => $this->sanitizeProviderMessage($payment->reconciliation_note),
            'last_reconciled_at' => $payment->last_reconciled_at?->toIso8601String(),
            'updated_at' => $payment->updated_at?->toIso8601String(),
            'href' => '/owner/payments/'.$payment->id,
            'attention_reason' => $attentionReason,
            'category' => $category,
            'net_paid' => $status === PaymentStatus::Paid->value
                ? $this->refunds->netPaid($payment)
                : null,
            'confirmed_refunds' => $status === PaymentStatus::Paid->value
                ? $this->refunds->confirmedRefundTotal($payment)
                : null,
        ];
    }

    private function categorize(Payment $payment): string
    {
        $status = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::from((string) $payment->status);

        $note = (string) ($payment->reconciliation_note ?? '');

        if ($status === PaymentStatus::Paid) {
            $openOrFailedRefund = $payment->refunds->contains(function (PaymentRefund $refund): bool {
                $refundStatus = $refund->status instanceof PaymentRefundStatus
                    ? $refund->status
                    : PaymentRefundStatus::from((string) $refund->status);

                return in_array($refundStatus, [
                    PaymentRefundStatus::Pending,
                    PaymentRefundStatus::Processing,
                    PaymentRefundStatus::Failed,
                ], true);
            });

            if ($openOrFailedRefund) {
                return 'refund_mismatch';
            }
        }

        if (str_contains($note, 'mismatch:amount')) {
            return 'amount_mismatch';
        }

        if (str_contains($note, 'mismatch:currency')) {
            return 'currency_mismatch';
        }

        if ($payment->failure_reason === 'Payment verification failed.'
            || str_starts_with($note, 'mismatch:')) {
            return 'failed_verification';
        }

        if ($status === PaymentStatus::Processing
            && $payment->updated_at !== null
            && $payment->updated_at->lte(now()->subMinutes(30))) {
            return 'processing_too_long';
        }

        if ($status === PaymentStatus::Processing) {
            return 'processing';
        }

        return 'failed';
    }

    public function sanitizeProviderMessage(?string $message): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        $clean = preg_replace('/\b(SK_|server[_-]?key|api[_-]?key|secret|password|Authorization)[=:\s][^\s,;]+/i', '[redacted]', $message) ?? $message;
        $clean = preg_replace('/[A-Za-z0-9+\/]{40,}={0,2}/', '[redacted]', $clean) ?? $clean;

        return Str::limit(trim($clean), 240, '…');
    }
}
