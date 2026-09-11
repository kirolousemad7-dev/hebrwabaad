<?php

namespace App\Services\Printing;

use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;

class PrintingExecutionEligibilityService
{
    public function __construct(
        private readonly PrintingQuotationService $quotations,
        private readonly PrintingCustomerApprovalService $approvals,
    ) {}

    /**
     * @return array{eligible: bool, reasons: list<string>, quotation: PrintingQuotation|null, payment_summary: array<string, mixed>|null}
     */
    public function eligible(PrintingRequest $request): array
    {
        $reasons = [];

        $status = $request->status instanceof PrintingRequestStatus
            ? $request->status
            : PrintingRequestStatus::from((string) $request->status);

        if ($status === PrintingRequestStatus::Cancelled) {
            $reasons[] = 'printing_request_cancelled';
        }

        if ($status === PrintingRequestStatus::Completed) {
            $reasons[] = 'printing_request_completed';
        }

        if ($this->approvals->hasPendingCheckpoints($request)) {
            $reasons[] = 'customer_approvals_pending';
        } elseif (! $this->approvals->allApprovedOrNone($request)) {
            $reasons[] = 'customer_approvals_incomplete';
        }

        $quotation = $this->latestAcceptedQuotation($request);

        if ($quotation === null) {
            $reasons[] = 'no_accepted_quotation';

            return [
                'eligible' => false,
                'reasons' => $reasons,
                'quotation' => null,
                'payment_summary' => null,
            ];
        }

        $summary = $this->quotations->paymentSummary($quotation);
        $policy = $quotation->payment_policy instanceof PrintingPaymentPolicy
            ? $quotation->payment_policy
            : PrintingPaymentPolicy::from((string) $quotation->payment_policy);

        if ($policy === PrintingPaymentPolicy::Deposit && ! $summary['requirement_met']) {
            $reasons[] = 'deposit_not_met';
        }

        if ($policy === PrintingPaymentPolicy::Full && ! $summary['requirement_met']) {
            $reasons[] = 'full_payment_not_met';
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'quotation' => $quotation,
            'payment_summary' => $summary,
        ];
    }

    public function latestAcceptedQuotation(PrintingRequest $request): ?PrintingQuotation
    {
        return PrintingQuotation::query()
            ->where('printing_request_id', $request->id)
            ->where('status', PrintingQuotationStatus::Accepted->value)
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->first();
    }

    public function hasAcceptedQuotation(PrintingRequest $request): bool
    {
        return $this->latestAcceptedQuotation($request) !== null;
    }
}
