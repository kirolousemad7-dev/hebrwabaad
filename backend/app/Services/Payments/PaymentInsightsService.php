<?php

namespace App\Services\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Models\PrintingQuotation;
use App\Models\PrintingQuotationEvent;
use App\Models\PrintingStatusHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class PaymentInsightsService
{
    /**
     * @return array<string, mixed>
     */
    public function payments(User $actor, int $days): array
    {
        $this->assertCanView($actor);

        $days = in_array($days, [7, 30, 90], true) ? $days : 7;
        $to = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();
        $includeAmounts = $this->includeAmounts($actor);

        $successful = Payment::query()
            ->where('status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$from, $to])
            ->get(['id', 'amount', 'payment_method', 'paid_at', 'created_at']);

        $failedAttempts = PaymentAttempt::query()
            ->where('status', PaymentAttemptStatus::Failed->value)
            ->whereBetween('failed_at', [$from, $to])
            ->count();

        $pending = Payment::query()
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
                PaymentStatus::PendingVerification->value,
            ])
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $refundTotal = PaymentRefund::query()
            ->where('status', PaymentRefundStatus::Confirmed->value)
            ->whereBetween('processed_at', [$from, $to])
            ->sum('amount');

        $methodSplit = Payment::query()
            ->where('status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('payment_method, COUNT(*) as count')
            ->groupBy('payment_method')
            ->pluck('count', 'payment_method')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $avgConfirmHours = $this->avgConfirmHours($successful);

        $payload = [
            'period_days' => $days,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'successful' => $successful->count(),
            'failed_attempts' => $failedAttempts,
            'pending' => $pending,
            'method_split' => $methodSplit,
            'avg_confirm_hours' => $avgConfirmHours,
            'include_amounts' => $includeAmounts,
        ];

        if ($includeAmounts) {
            $payload['refund_total'] = $this->money((string) $refundTotal);
            $payload['successful_amount'] = $this->sumAmounts($successful);
        } else {
            $payload['refund_total'] = null;
            $payload['successful_amount'] = null;
        }

        return $payload;
    }

    /**
     * Payment funnel: accepted → checkout_created → confirmed → eligible → in_production.
     *
     * @return array<string, mixed>
     */
    public function paymentFunnel(User $actor, int $days): array
    {
        $this->assertCanView($actor);

        $days = in_array($days, [7, 30, 90], true) ? $days : 7;
        $to = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();

        $accepted = PrintingQuotation::query()
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$from, $to])
            ->count();

        $checkoutCreated = PrintingQuotationEvent::query()
            ->where('event', 'checkout_created')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $confirmed = Payment::query()
            ->whereNotNull('printing_quotation_id')
            ->where('status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$from, $to])
            ->count();

        $eligible = PrintingQuotationEvent::query()
            ->where('event', 'payment_requirement_met')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $inProduction = PrintingStatusHistory::query()
            ->where('to_status', PrintingRequestStatus::InProgress->value)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            'period_days' => $days,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'stages' => [
                ['key' => 'accepted', 'label' => 'مقبول', 'count' => $accepted],
                ['key' => 'checkout_created', 'label' => 'بدء الدفع', 'count' => $checkoutCreated],
                ['key' => 'confirmed', 'label' => 'مؤكد', 'count' => $confirmed],
                ['key' => 'eligible', 'label' => 'جاهز للتنفيذ', 'count' => $eligible],
                ['key' => 'in_production', 'label' => 'قيد الإنتاج', 'count' => $inProduction],
            ],
            'counts' => [
                'accepted' => $accepted,
                'checkout_created' => $checkoutCreated,
                'confirmed' => $confirmed,
                'eligible' => $eligible,
                'in_production' => $inProduction,
            ],
        ];
    }

    private function assertCanView(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canViewPrintingRevenueSection()) {
            abort(403);
        }
    }

    private function includeAmounts(User $actor): bool
    {
        return $actor->role instanceof UserRole
            && ($actor->role->canViewPrintingRevenue() || $actor->role->canManagePayments());
    }

    /**
     * @param  Collection<int, Payment>  $payments
     */
    private function avgConfirmHours($payments): ?float
    {
        $hours = $payments
            ->map(function (Payment $payment): ?float {
                if ($payment->created_at === null || $payment->paid_at === null) {
                    return null;
                }

                return $payment->created_at->diffInSeconds($payment->paid_at) / 3600;
            })
            ->filter(fn (?float $value): bool => $value !== null)
            ->values();

        if ($hours->isEmpty()) {
            return null;
        }

        return round((float) $hours->avg(), 1);
    }

    /**
     * @param  Collection<int, Payment>  $payments
     */
    private function sumAmounts($payments): string
    {
        $sum = '0.00';
        foreach ($payments as $payment) {
            $sum = bcadd($sum, (string) $payment->amount, 2);
        }

        return $sum;
    }

    private function money(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
