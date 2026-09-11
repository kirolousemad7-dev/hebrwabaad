<?php

namespace App\Services\Printing;

use App\Enums\PaymentRefundStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Support\Calendar\CalendarRelatedEntityUrlResolver;

class RefundImpactService
{
    public function __construct(
        private readonly PrintingExecutionEligibilityService $eligibility,
        private readonly CalendarRelatedEntityUrlResolver $urls,
    ) {}

    /**
     * Recalculate printing eligibility after a confirmed refund.
     * Never auto-changes printing request status.
     */
    public function handleAfterRefund(Payment $payment, PaymentRefund $refund): void
    {
        if ($payment->printing_quotation_id === null) {
            return;
        }

        $payment->loadMissing(['printingQuotation.printingRequest']);
        $request = $payment->printingQuotation?->printingRequest;
        if ($request === null) {
            return;
        }

        $wasEligible = $this->wasLikelyEligibleBeforeRefund($request, $payment, $refund);
        $after = $this->eligibility->eligible($request);

        if ($wasEligible && ! $after['eligible'] && $this->isExecutionStatus($request)) {
            $metadata = is_array($refund->metadata) ? $refund->metadata : [];
            $metadata['attention'] = 'refund_after_execution';
            $metadata['printing_request_id'] = $request->id;
            $metadata['eligibility_reasons'] = $after['reasons'];
            $refund->update(['metadata' => $metadata]);
        }
    }

    /**
     * Command Center attention items for refunds that broke execution eligibility.
     *
     * @return list<array<string, mixed>>
     */
    public function attentionItems(User $actor, int $limit = 8): array
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canViewCommandCenter()) {
            return [];
        }

        $refunds = PaymentRefund::query()
            ->with(['payment.printingQuotation.printingRequest'])
            ->where('status', PaymentRefundStatus::Confirmed->value)
            ->where('metadata->attention', 'refund_after_execution')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $items = [];
        foreach ($refunds as $refund) {
            $request = $refund->payment?->printingQuotation?->printingRequest;
            if ($request === null || ! $this->isExecutionStatus($request)) {
                continue;
            }

            $items[] = [
                'type' => 'refund_after_execution',
                'severity' => 'critical',
                'title' => 'استرداد بعد بدء التنفيذ #'.$request->id,
                'description' => 'تم استرداد دفعة وأصبح الطلب غير مؤهل للتنفيذ دون تغيير حالته تلقائيًا',
                'source' => 'payment_refund',
                'related_type' => 'printing_request',
                'related_id' => $request->id,
                'payment_id' => $refund->payment_id,
                'refund_id' => $refund->id,
                'href' => $this->urls->resolve($actor, 'printing_request', (int) $request->id)
                    ?? '/operations/printing?item='.$request->id,
            ];
        }

        return $items;
    }

    private function wasLikelyEligibleBeforeRefund(
        PrintingRequest $request,
        Payment $payment,
        PaymentRefund $refund,
    ): bool {
        $after = $this->eligibility->eligible($request);
        if ($after['eligible']) {
            return true;
        }

        // Reconstruct: if adding this refund amount back would meet payment requirement, it was eligible.
        $summary = $after['payment_summary'];
        if (! is_array($summary)) {
            return $this->isExecutionStatus($request);
        }

        $netPaid = (string) ($summary['paid'] ?? '0.00');
        $restored = bcadd($netPaid, $this->money((string) $refund->amount), 2);
        $policy = (string) ($summary['payment_policy'] ?? '');
        $deposit = isset($summary['deposit_required']) ? (string) $summary['deposit_required'] : null;
        $total = (string) ($summary['total'] ?? '0.00');

        $paymentOk = match ($policy) {
            'DEPOSIT' => $deposit !== null && bccomp($restored, $deposit, 2) >= 0,
            'FULL' => bccomp($restored, $total, 2) >= 0,
            'NONE' => true,
            default => false,
        };

        if (! $paymentOk) {
            return false;
        }

        $nonPaymentReasons = array_values(array_filter(
            $after['reasons'],
            fn (string $reason): bool => ! in_array($reason, ['deposit_not_met', 'full_payment_not_met'], true),
        ));

        return $nonPaymentReasons === [];
    }

    private function isExecutionStatus(PrintingRequest $request): bool
    {
        $status = $request->status instanceof PrintingRequestStatus
            ? $request->status
            : PrintingRequestStatus::from((string) $request->status);

        return in_array($status, [
            PrintingRequestStatus::InProgress,
            PrintingRequestStatus::ReadyForDelivery,
            PrintingRequestStatus::Completed,
        ], true);
    }

    private function money(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
