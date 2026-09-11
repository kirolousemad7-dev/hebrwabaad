<?php

namespace App\Services\Printing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PrintingQuotation;
use App\Models\PrintingQuotationEvent;
use App\Models\PrintingRequest;
use App\Models\PrintingStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PrintingRevenueService
{
    public function __construct(
        private readonly PrintingQuotationService $quotations,
        private readonly PrintingExecutionEligibilityService $eligibility,
    ) {}

    /**
     * Command-center revenue / quote pipeline section.
     *
     * @return array<string, mixed>|null
     */
    public function commandCenterSection(User $actor): ?array
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canViewPrintingRevenueSection()) {
            return null;
        }

        $includeAmounts = $actor->role->canViewPrintingRevenue() || $actor->role->canManagePayments();

        $awaiting = PrintingQuotation::query()
            ->whereIn('status', [
                PrintingQuotationStatus::Sent->value,
                PrintingQuotationStatus::Viewed->value,
            ])
            ->get(['id', 'total', 'valid_until', 'status']);

        $expiringSoon = $awaiting->filter(function (PrintingQuotation $quotation): bool {
            if ($quotation->valid_until === null) {
                return false;
            }

            $until = $quotation->valid_until->copy()->startOfDay();

            return $until->between(
                now()->startOfDay(),
                now()->addDays(3)->endOfDay(),
            );
        });

        $accepted = PrintingQuotation::query()
            ->with('printingRequest:id,status')
            ->where('status', PrintingQuotationStatus::Accepted->value)
            ->get();

        $paymentsRequired = 0;
        $readyAfterPayment = 0;
        $executionEligible = 0;
        $paymentsRequiredValue = '0.00';
        $readyAfterPaymentValue = '0.00';

        foreach ($accepted as $quotation) {
            $summary = $this->quotations->paymentSummary($quotation);
            $request = $quotation->printingRequest;
            $requestStatus = $request?->status instanceof PrintingRequestStatus
                ? $request->status
                : ($request ? PrintingRequestStatus::tryFrom((string) $request->status) : null);

            if (! $summary['requirement_met']) {
                $paymentsRequired++;
                $paymentsRequiredValue = bcadd($paymentsRequiredValue, (string) ($summary['remaining'] ?? '0'), 2);

                continue;
            }

            if ($request !== null && $this->eligibility->eligible($request)['eligible']) {
                $executionEligible++;
            }

            if ($requestStatus === PrintingRequestStatus::Pending) {
                $readyAfterPayment++;
                $readyAfterPaymentValue = bcadd($readyAfterPaymentValue, (string) $quotation->total, 2);
            }
        }

        $readyForDelivery = PrintingRequest::query()
            ->where('status', PrintingRequestStatus::ReadyForDelivery->value)
            ->count();

        $pendingReview = Payment::query()
            ->where('status', PaymentStatus::PendingVerification->value)
            ->count();

        $paymentsProcessing = Payment::query()
            ->where('payment_method', PaymentMethod::Card->value)
            ->where('status', PaymentStatus::Processing->value)
            ->count();

        $failedPayments = Payment::query()
            ->where('status', PaymentStatus::Failed->value)
            ->count();

        $refundsConfirmedCount = PaymentRefund::query()
            ->where('status', PaymentRefundStatus::Confirmed->value)
            ->count();

        $reconcileAttention = Payment::query()
            ->where('payment_method', PaymentMethod::Card->value)
            ->where(function ($query): void {
                $query->where('status', PaymentStatus::Processing->value)
                    ->orWhere(function ($inner): void {
                        $inner->where('status', PaymentStatus::Failed->value)
                            ->where(function ($fail): void {
                                $fail->where('failure_reason', 'Payment verification failed.')
                                    ->orWhere('reconciliation_note', 'like', 'mismatch:%')
                                    ->orWhereNotNull('provider_status');
                            });
                    });
            })
            ->count();

        $section = [
            'quotes_awaiting_customer' => $awaiting->count(),
            'quotes_expiring_soon' => $expiringSoon->count(),
            'payments_required' => $paymentsRequired,
            'awaiting_payment' => $paymentsRequired,
            'pending_review' => $pendingReview,
            'pending_processing' => $paymentsProcessing,
            'payments_processing' => $paymentsProcessing,
            'failed_payments' => $failedPayments,
            'refunds_confirmed_count' => $refundsConfirmedCount,
            'reconciliation_problems' => $reconcileAttention,
            'reconcile_attention' => $reconcileAttention,
            'execution_eligible' => $executionEligible,
            'ready_to_start_after_payment' => $readyAfterPayment,
            'ready_for_delivery' => $readyForDelivery,
            'include_amounts' => $includeAmounts,
        ];

        if ($includeAmounts) {
            $section['amounts'] = [
                'quotes_awaiting_customer_value' => $this->sumTotals($awaiting),
                'quotes_expiring_soon_value' => $this->sumTotals($expiringSoon),
                'payments_required_remaining' => $paymentsRequiredValue,
                'awaiting_payment_remaining' => $paymentsRequiredValue,
                'ready_to_start_after_payment_value' => $readyAfterPaymentValue,
                'pending_review_amount' => $this->money((string) Payment::query()
                    ->where('status', PaymentStatus::PendingVerification->value)
                    ->sum('amount')),
                'pending_processing_amount' => $this->money((string) Payment::query()
                    ->where('payment_method', PaymentMethod::Card->value)
                    ->where('status', PaymentStatus::Processing->value)
                    ->sum('amount')),
                'failed_payments_amount' => $this->money((string) Payment::query()
                    ->where('status', PaymentStatus::Failed->value)
                    ->sum('amount')),
                'refunds_confirmed_amount' => $this->money((string) PaymentRefund::query()
                    ->where('status', PaymentRefundStatus::Confirmed->value)
                    ->sum('amount')),
            ];
        } else {
            $section['amounts'] = null;
        }

        return $section;
    }

    /**
     * Commercial printing funnel for insights.
     *
     * @return array<string, mixed>
     */
    public function printingFunnel(User $actor, int $days): array
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canViewPrintingRevenueSection()) {
            abort(403);
        }

        $days = in_array($days, [7, 30, 90], true) ? $days : 7;
        $to = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();

        $sent = PrintingQuotation::query()
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$from, $to])
            ->get(['id', 'sent_at', 'viewed_at', 'accepted_at', 'printing_request_id']);

        $viewed = PrintingQuotation::query()
            ->whereNotNull('viewed_at')
            ->whereBetween('viewed_at', [$from, $to])
            ->get(['id', 'sent_at', 'viewed_at', 'accepted_at', 'printing_request_id']);

        $accepted = PrintingQuotation::query()
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$from, $to])
            ->get(['id', 'sent_at', 'viewed_at', 'accepted_at', 'printing_request_id']);

        $paymentMetEvents = PrintingQuotationEvent::query()
            ->where('event', 'payment_requirement_met')
            ->whereBetween('created_at', [$from, $to])
            ->with('quotation:id,accepted_at,printing_request_id')
            ->get();

        $inProduction = PrintingStatusHistory::query()
            ->where('to_status', PrintingRequestStatus::InProgress->value)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'printing_request_id', 'created_at']);

        $delivered = PrintingStatusHistory::query()
            ->where('to_status', PrintingRequestStatus::Completed->value)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'printing_request_id', 'created_at']);

        $stages = [
            [
                'key' => 'sent',
                'label' => 'مُرسل',
                'count' => $sent->count(),
                'avg_hours_from_previous' => null,
            ],
            [
                'key' => 'viewed',
                'label' => 'مُشاهد',
                'count' => $viewed->count(),
                'avg_hours_from_previous' => $this->avgHoursBetween(
                    $viewed,
                    fn (PrintingQuotation $q) => $q->sent_at,
                    fn (PrintingQuotation $q) => $q->viewed_at,
                ),
            ],
            [
                'key' => 'accepted',
                'label' => 'مقبول',
                'count' => $accepted->count(),
                'avg_hours_from_previous' => $this->avgHoursBetween(
                    $accepted,
                    fn (PrintingQuotation $q) => $q->viewed_at ?? $q->sent_at,
                    fn (PrintingQuotation $q) => $q->accepted_at,
                ),
            ],
            [
                'key' => 'payment_met',
                'label' => 'استيفاء الدفع',
                'count' => $paymentMetEvents->count(),
                'avg_hours_from_previous' => $this->avgHoursBetween(
                    $paymentMetEvents,
                    fn (PrintingQuotationEvent $event) => $event->quotation?->accepted_at,
                    fn (PrintingQuotationEvent $event) => $event->created_at,
                ),
            ],
            [
                'key' => 'in_production',
                'label' => 'قيد الإنتاج',
                'count' => $inProduction->count(),
                'avg_hours_from_previous' => null,
            ],
            [
                'key' => 'delivered',
                'label' => 'مُسلَّم',
                'count' => $delivered->count(),
                'avg_hours_from_previous' => $this->avgHoursBetween(
                    $delivered,
                    function (PrintingStatusHistory $row) {
                        return PrintingStatusHistory::query()
                            ->where('printing_request_id', $row->printing_request_id)
                            ->where('to_status', PrintingRequestStatus::InProgress->value)
                            ->orderByDesc('id')
                            ->value('created_at');
                    },
                    fn (PrintingStatusHistory $row) => $row->created_at,
                ),
            ],
        ];

        return [
            'period_days' => $days,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'stages' => $stages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function insights(User $actor, int $days): array
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canViewPrintingRevenueSection()) {
            abort(403);
        }

        $days = in_array($days, [7, 30, 90], true) ? $days : 7;
        $to = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();
        $includeAmounts = $actor->role->canViewPrintingRevenue();

        $sentCount = PrintingQuotation::query()
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$from, $to])
            ->count();

        $acceptedRows = PrintingQuotation::query()
            ->where('status', PrintingQuotationStatus::Accepted->value)
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$from, $to])
            ->get(['id', 'total', 'sent_at', 'accepted_at']);

        $acceptedCount = $acceptedRows->count();
        $acceptanceRate = $sentCount > 0
            ? round(($acceptedCount / $sentCount) * 100, 1)
            : 0.0;

        $responseHours = $acceptedRows
            ->filter(fn (PrintingQuotation $q): bool => $q->sent_at !== null && $q->accepted_at !== null)
            ->map(fn (PrintingQuotation $q): float => $q->sent_at->diffInSeconds($q->accepted_at) / 3600)
            ->values();

        $avgHours = $responseHours->isEmpty()
            ? null
            : round((float) $responseHours->avg(), 1);

        $payload = [
            'period_days' => $days,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'quotations_sent' => $sentCount,
            'accepted_count' => $acceptedCount,
            'acceptance_rate' => $acceptanceRate,
            'avg_response_hours' => $avgHours,
            'include_amounts' => $includeAmounts,
        ];

        if ($includeAmounts) {
            $acceptedValue = $acceptedRows->reduce(
                fn (string $carry, PrintingQuotation $q): string => bcadd($carry, (string) $q->total, 2),
                '0.00',
            );
            $paymentsReceived = Payment::query()
                ->whereNotNull('printing_quotation_id')
                ->where('status', PaymentStatus::Paid->value)
                ->where(function ($query) use ($from, $to): void {
                    $query->whereBetween('paid_at', [$from, $to])
                        ->orWhere(function ($inner) use ($from, $to): void {
                            $inner->whereNull('paid_at')
                                ->whereBetween('created_at', [$from, $to]);
                        });
                })
                ->sum('amount');

            $outstanding = '0.00';
            PrintingQuotation::query()
                ->where('status', PrintingQuotationStatus::Accepted->value)
                ->orderBy('id')
                ->chunkById(100, function ($quotations) use (&$outstanding): void {
                    foreach ($quotations as $quotation) {
                        $summary = $this->quotations->paymentSummary($quotation);
                        if (! $summary['requirement_met']) {
                            $outstanding = bcadd($outstanding, (string) ($summary['remaining'] ?? '0'), 2);
                        }
                    }
                });

            $payload['accepted_value'] = $acceptedValue;
            $payload['payments_received_sum'] = $this->money((string) $paymentsReceived);
            $payload['outstanding_accepted'] = $outstanding;
        } else {
            $payload['accepted_value'] = null;
            $payload['payments_received_sum'] = null;
            $payload['outstanding_accepted'] = null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function customerHistory(User $actor, User $customer): array
    {
        $this->assertCanViewCustomerHistory($actor);

        if ($customer->role !== UserRole::Customer) {
            abort(404);
        }

        $includeAmounts = $actor->role instanceof UserRole && $actor->role->canViewPrintingRevenue();

        $requests = PrintingRequest::query()
            ->where('user_id', $customer->id)
            ->latest('id')
            ->limit(100)
            ->get(['id', 'product_name', 'status', 'quantity', 'required_date', 'quoted_price', 'created_at']);

        $quotations = PrintingQuotation::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(100)
            ->get();

        $payments = Payment::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('printing_quotation_id')
            ->latest('id')
            ->limit(100)
            ->get();

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'printing_requests' => $requests->map(function (PrintingRequest $request) use ($includeAmounts): array {
                return [
                    'id' => $request->id,
                    'product_name' => $request->product_name,
                    'status' => $request->status instanceof \BackedEnum
                        ? $request->status->value
                        : $request->status,
                    'quantity' => $request->quantity,
                    'required_date' => $request->required_date?->toDateString(),
                    'quoted_price' => $includeAmounts ? $request->quoted_price : null,
                    'created_at' => $request->created_at?->toIso8601String(),
                    'href' => '/operations/printing/'.$request->id,
                ];
            })->values()->all(),
            'quotations' => $quotations->map(function (PrintingQuotation $quotation) use ($includeAmounts): array {
                return [
                    'id' => $quotation->id,
                    'reference' => $quotation->reference,
                    'revision' => $quotation->revision,
                    'printing_request_id' => $quotation->printing_request_id,
                    'status' => $quotation->status instanceof \BackedEnum
                        ? $quotation->status->value
                        : $quotation->status,
                    'total' => $includeAmounts ? $quotation->total : null,
                    'currency' => $quotation->currency,
                    'sent_at' => $quotation->sent_at?->toIso8601String(),
                    'accepted_at' => $quotation->accepted_at?->toIso8601String(),
                    'href' => '/operations/printing-quotations/'.$quotation->id,
                ];
            })->values()->all(),
            'payments' => $payments->map(function (Payment $payment) use ($includeAmounts): array {
                return [
                    'id' => $payment->id,
                    'printing_quotation_id' => $payment->printing_quotation_id,
                    'amount' => $includeAmounts ? $payment->amount : null,
                    'currency' => $payment->currency,
                    'status' => $payment->status instanceof \BackedEnum
                        ? $payment->status->value
                        : $payment->status,
                    'payment_method' => $payment->payment_method instanceof \BackedEnum
                        ? $payment->payment_method->value
                        : $payment->payment_method,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'href' => $payment->printing_quotation_id
                        ? '/operations/printing-quotations/'.$payment->printing_quotation_id
                        : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(User $actor): array
    {
        $this->assertCanExportQuotations($actor);
        $includeAmounts = $actor->role instanceof UserRole && $actor->role->canViewPrintingRevenue();

        return PrintingQuotation::query()
            ->with(['customer:id,name,email', 'printingRequest:id,product_name,status'])
            ->latest('id')
            ->limit(2000)
            ->get()
            ->map(function (PrintingQuotation $quotation) use ($includeAmounts): array {
                return [
                    'id' => (string) $quotation->id,
                    'reference' => (string) $quotation->reference,
                    'revision' => (string) $quotation->revision,
                    'status' => $quotation->status instanceof \BackedEnum
                        ? $quotation->status->value
                        : (string) $quotation->status,
                    'customer' => (string) ($quotation->customer?->name ?? ''),
                    'product_name' => (string) ($quotation->printingRequest?->product_name ?? ''),
                    'total' => $includeAmounts ? (string) $quotation->total : '',
                    'currency' => (string) $quotation->currency,
                    'payment_policy' => $quotation->payment_policy instanceof \BackedEnum
                        ? $quotation->payment_policy->value
                        : (string) $quotation->payment_policy,
                    'valid_until' => (string) ($quotation->valid_until?->toDateString() ?? ''),
                    'sent_at' => (string) ($quotation->sent_at?->toIso8601String() ?? ''),
                    'accepted_at' => (string) ($quotation->accepted_at?->toIso8601String() ?? ''),
                    'printing_request_id' => (string) $quotation->printing_request_id,
                ];
            })
            ->all();
    }

    private function assertCanViewCustomerHistory(User $actor): void
    {
        if (! ($actor->role instanceof UserRole)) {
            abort(403);
        }

        if ($actor->role->canViewPrintingRevenueSection() || $actor->role->canReviewPrintingRequests()) {
            return;
        }

        abort(403);
    }

    private function assertCanExportQuotations(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canViewPrintingRevenueSection()) {
            abort(403);
        }
    }

    /**
     * @param  Collection<int, PrintingQuotation>  $quotations
     */
    private function sumTotals($quotations): string
    {
        $sum = '0.00';
        foreach ($quotations as $quotation) {
            $sum = bcadd($sum, (string) $quotation->total, 2);
        }

        return $sum;
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @param  callable(mixed): mixed  $fromResolver
     * @param  callable(mixed): mixed  $toResolver
     */
    private function avgHoursBetween($rows, callable $fromResolver, callable $toResolver): ?float
    {
        $hours = $rows
            ->map(function ($row) use ($fromResolver, $toResolver): ?float {
                $from = $fromResolver($row);
                $to = $toResolver($row);
                if ($from === null || $to === null) {
                    return null;
                }

                $fromTs = $from instanceof \DateTimeInterface ? $from : (is_string($from) ? Carbon::parse($from) : null);
                $toTs = $to instanceof \DateTimeInterface ? $to : (is_string($to) ? Carbon::parse($to) : null);
                if ($fromTs === null || $toTs === null) {
                    return null;
                }

                return $fromTs->diffInSeconds($toTs) / 3600;
            })
            ->filter(fn (?float $value): bool => $value !== null)
            ->values();

        if ($hours->isEmpty()) {
            return null;
        }

        return round((float) $hours->avg(), 1);
    }

    private function money(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
