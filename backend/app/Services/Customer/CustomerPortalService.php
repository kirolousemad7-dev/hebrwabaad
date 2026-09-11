<?php

namespace App\Services\Customer;

use App\Enums\PaymentStatus;
use App\Enums\PrintingCustomerApprovalStatus;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Models\CustomerPortalAccess;
use App\Models\Payment;
use App\Models\PrintingCustomerApproval;
use App\Models\PrintingQuotation;
use App\Models\PrintingQuotationEvent;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Printing\PrintingCustomerStatusMapper;
use App\Services\Printing\PrintingDeliveryService;
use App\Services\Printing\PrintingQuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerPortalService
{
    public function __construct(
        private readonly PrintingQuotationService $quotations,
        private readonly PrintingDeliveryService $deliveries,
    ) {}

    /**
     * @return array{access: CustomerPortalAccess, raw_token: string}
     */
    public function createAccess(User $staff, User $customer, ?\DateTimeInterface $expiresAt = null): array
    {
        $this->assertStaffCanManagePortal($staff);
        $this->assertCustomer($customer);

        $rawToken = Str::random(64);

        $access = CustomerPortalAccess::query()->create([
            'customer_id' => $customer->id,
            'token_hash' => CustomerPortalAccess::hashToken($rawToken),
            'token_hint' => substr($rawToken, -8),
            'expires_at' => $expiresAt,
            'created_by' => $staff->id,
        ]);

        return ['access' => $access, 'raw_token' => $rawToken];
    }

    /**
     * @return array{access: CustomerPortalAccess, raw_token: string}
     */
    public function createAccessForCustomer(User $customer, ?\DateTimeInterface $expiresAt = null): array
    {
        $this->assertCustomer($customer);

        $rawToken = Str::random(64);

        $access = CustomerPortalAccess::query()->create([
            'customer_id' => $customer->id,
            'token_hash' => CustomerPortalAccess::hashToken($rawToken),
            'token_hint' => substr($rawToken, -8),
            'expires_at' => $expiresAt ?? now()->addDays(7),
            'created_by' => null,
        ]);

        return ['access' => $access, 'raw_token' => $rawToken];
    }

    public function findByToken(string $rawToken): CustomerPortalAccess
    {
        if ($rawToken === '') {
            throw ValidationException::withMessages([
                'token' => ['Portal link not found.'],
            ]);
        }

        $access = CustomerPortalAccess::query()
            ->where('token_hash', CustomerPortalAccess::hashToken($rawToken))
            ->first();

        if ($access === null) {
            throw ValidationException::withMessages([
                'token' => ['Portal link not found.'],
            ]);
        }

        if ($access->isRevoked()) {
            throw ValidationException::withMessages([
                'token' => ['Portal link has been revoked.'],
            ]);
        }

        if ($access->isExpired()) {
            throw ValidationException::withMessages([
                'token' => ['Portal link has expired.'],
            ]);
        }

        $access->forceFill(['last_accessed_at' => now()])->save();

        return $access->load('customer:id,name,email');
    }

    public function revoke(User $staff, User $customer): int
    {
        $this->assertStaffCanManagePortal($staff);
        $this->assertCustomer($customer);

        return CustomerPortalAccess::query()
            ->where('customer_id', $customer->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function portalPayload(User $customer, ?string $portalToken = null): array
    {
        return [
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'summary' => $this->summaryPayload($customer),
            'timeline' => $this->timelinePayload($customer),
            'documents' => $this->documentsPayload($customer, $portalToken),
            'support_href' => null,
            'quotations' => $this->quotationsPayload($customer),
            'payments' => $this->paymentsPayload($customer),
            'printing' => $this->printingPayload($customer),
            'approvals' => $this->approvalsPayload($customer),
            'files' => $this->filesPayload($customer),
        ];
    }

    /**
     * @return array{
     *     awaiting_quote_response: int,
     *     payments_due: int,
     *     approvals_due: int,
     *     in_progress: int,
     *     ready_for_pickup: int
     * }
     */
    public function summaryPayload(User $customer): array
    {
        $awaitingQuote = PrintingQuotation::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                PrintingQuotationStatus::Sent->value,
                PrintingQuotationStatus::Viewed->value,
            ])
            ->count();

        $acceptedQuotes = PrintingQuotation::query()
            ->where('customer_id', $customer->id)
            ->where('status', PrintingQuotationStatus::Accepted->value)
            ->get();

        $paymentsDue = 0;
        foreach ($acceptedQuotes as $quotation) {
            $summary = $this->quotations->paymentSummary($quotation);
            if (! $summary['requirement_met'] && bccomp((string) $summary['amount_due_now'], '0', 2) === 1) {
                $paymentsDue++;
            }
        }

        $requestIds = PrintingRequest::query()
            ->where('user_id', $customer->id)
            ->pluck('id');

        $approvalsDue = PrintingCustomerApproval::query()
            ->whereIn('printing_request_id', $requestIds)
            ->where('status', PrintingCustomerApprovalStatus::Pending->value)
            ->count();

        $inProgress = PrintingRequest::query()
            ->where('user_id', $customer->id)
            ->where('status', PrintingRequestStatus::InProgress->value)
            ->count();

        $readyForPickup = PrintingRequest::query()
            ->where('user_id', $customer->id)
            ->where('status', PrintingRequestStatus::ReadyForDelivery->value)
            ->count();

        return [
            'awaiting_quote_response' => $awaitingQuote,
            'payments_due' => $paymentsDue,
            'approvals_due' => $approvalsDue,
            'in_progress' => $inProgress,
            'ready_for_pickup' => $readyForPickup,
        ];
    }

    /**
     * Safe customer-facing timeline (bounded).
     *
     * @return list<array<string, mixed>>
     */
    public function timelinePayload(User $customer, int $limit = 30): array
    {
        $quotationIds = PrintingQuotation::query()
            ->where('customer_id', $customer->id)
            ->pluck('id');

        $events = PrintingQuotationEvent::query()
            ->whereIn('printing_quotation_id', $quotationIds)
            ->whereIn('event', [
                'sent',
                'viewed',
                'accepted',
                'rejected',
                'payment_recorded',
                'status_changed',
                'payment_requirement_met',
                'portal_approval_decided',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (PrintingQuotationEvent $event): array {
                $meta = is_array($event->meta) ? $event->meta : [];
                $safeMeta = match ($event->event) {
                    'status_changed' => [
                        'status_key' => $meta['status_key'] ?? $meta['to_status'] ?? null,
                        'status_label' => $meta['status_label'] ?? null,
                    ],
                    'payment_recorded' => [
                        'amount' => $meta['amount'] ?? null,
                        'currency' => $meta['currency'] ?? null,
                    ],
                    'portal_approval_decided' => [
                        'decision' => $meta['decision'] ?? null,
                    ],
                    default => null,
                };

                return [
                    'type' => $event->event,
                    'label' => $this->timelineLabel($event->event),
                    'at' => $event->created_at?->toIso8601String(),
                    'meta' => $safeMeta,
                ];
            })
            ->values()
            ->all();

        return array_values(array_reverse($events));
    }

    /**
     * @return array{quotes: list<array<string, mixed>>, receipts: list<array<string, mixed>>}
     */
    public function documentsPayload(User $customer, ?string $portalToken = null): array
    {
        $quotes = PrintingQuotation::query()
            ->where('customer_id', $customer->id)
            ->where('status', PrintingQuotationStatus::Accepted->value)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (PrintingQuotation $quotation) use ($portalToken): array {
                $path = $portalToken !== null && $portalToken !== ''
                    ? '/api/public/portal/'.$portalToken.'/documents/quotations/'.$quotation->id.'/pdf'
                    : null;

                return [
                    'kind' => 'accepted_quote_pdf',
                    'quotation_id' => $quotation->id,
                    'reference' => $quotation->reference,
                    'revision' => $quotation->revision,
                    'path' => $path,
                ];
            })
            ->values()
            ->all();

        $receipts = Payment::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('printing_quotation_id')
            ->where('status', PaymentStatus::Paid->value)
            ->with('printingQuotation:id,reference')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (Payment $payment) use ($portalToken): array {
                $path = $portalToken !== null && $portalToken !== ''
                    ? '/api/public/portal/'.$portalToken.'/documents/payments/'.$payment->id.'/receipt'
                    : null;

                return [
                    'kind' => 'payment_receipt',
                    'payment_id' => $payment->id,
                    'quotation_reference' => $payment->printingQuotation?->reference,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'path' => $path,
                ];
            })
            ->values()
            ->all();

        return [
            'quotes' => $quotes,
            'receipts' => $receipts,
        ];
    }

    public function quotationPdfForPortal(CustomerPortalAccess $access, int $quotationId): Response
    {
        $quotation = PrintingQuotation::query()
            ->whereKey($quotationId)
            ->where('customer_id', $access->customer_id)
            ->where('status', PrintingQuotationStatus::Accepted->value)
            ->first();

        if ($quotation === null) {
            throw ValidationException::withMessages([
                'document' => ['Document not found.'],
            ]);
        }

        $quotation = $this->quotations->load($quotation);
        $html = $this->quotations->renderHtmlForPortal($quotation);

        if (class_exists(Pdf::class)) {
            try {
                $pdf = Pdf::loadHTML($html);

                return $pdf->download($quotation->reference.'-r'.$quotation->revision.'.pdf');
            } catch (\Throwable) {
                // fall through
            }
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$quotation->reference.'.html"',
        ]);
    }

    /**
     * @return array<string, mixed>|Response
     */
    public function paymentReceiptForPortal(CustomerPortalAccess $access, int $paymentId): array|Response
    {
        $payment = Payment::query()
            ->whereKey($paymentId)
            ->where('customer_id', $access->customer_id)
            ->where('status', PaymentStatus::Paid->value)
            ->whereNotNull('printing_quotation_id')
            ->first();

        if ($payment === null) {
            throw ValidationException::withMessages([
                'document' => ['Receipt not found.'],
            ]);
        }

        return $this->quotations->paymentReceiptPayload($payment);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function quotationsPayload(User $customer): array
    {
        return PrintingQuotation::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', [PrintingQuotationStatus::Draft->value, PrintingQuotationStatus::Cancelled->value])
            ->with('printingRequest:id,product_name,quantity')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (PrintingQuotation $quotation): array {
                $status = $quotation->status instanceof PrintingQuotationStatus
                    ? $quotation->status
                    : PrintingQuotationStatus::from((string) $quotation->status);

                return [
                    'id' => $quotation->id,
                    'reference' => $quotation->reference,
                    'revision' => $quotation->revision,
                    'status' => $status->value,
                    'status_label' => $this->quotationStatusLabel($status),
                    'total' => $quotation->total,
                    'currency' => $quotation->currency,
                    'valid_until' => $quotation->valid_until?->toDateString(),
                    'product_name' => $quotation->printingRequest?->product_name,
                    'payment_summary' => $this->quotations->paymentSummary($quotation),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{items: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function paymentsPayload(User $customer): array
    {
        $items = Payment::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('printing_quotation_id')
            ->with('printingQuotation:id,reference,currency')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (Payment $payment): array {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status instanceof \BackedEnum ? $payment->status->value : (string) $payment->status,
                    'payment_method' => $payment->payment_method instanceof \BackedEnum
                        ? $payment->payment_method->value
                        : (string) $payment->payment_method,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'quotation_reference' => $payment->printingQuotation?->reference,
                ];
            })
            ->values()
            ->all();

        $paid = Payment::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('printing_quotation_id')
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        return [
            'items' => $items,
            'summary' => [
                'paid_total' => number_format((float) $paid, 2, '.', ''),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function printingPayload(User $customer): array
    {
        return PrintingRequest::query()
            ->where('user_id', $customer->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (PrintingRequest $request): array {
                return [
                    'id' => $request->id,
                    'product_name' => $request->product_name,
                    'quantity' => $request->quantity,
                    'required_date' => $request->required_date?->toDateString(),
                    'status_key' => PrintingCustomerStatusMapper::key($request->status),
                    'status_label' => PrintingCustomerStatusMapper::label($request->status),
                    'delivered_at' => $request->delivered_at?->toIso8601String(),
                    'delivery' => $this->deliveries->customerSafePayloadForRequest($request),
                    'files' => $this->customerVisibleFilesForRequest($request),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function approvalsPayload(User $customer): array
    {
        $requestIds = PrintingRequest::query()
            ->where('user_id', $customer->id)
            ->pluck('id');

        return PrintingCustomerApproval::query()
            ->whereIn('printing_request_id', $requestIds)
            ->where('status', PrintingCustomerApprovalStatus::Pending->value)
            ->with('printingRequest:id,product_name,user_id')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PrintingCustomerApproval $approval): array => [
                'id' => $approval->id,
                'title' => $approval->title,
                'type' => $approval->type instanceof \BackedEnum ? $approval->type->value : (string) $approval->type,
                'status' => $approval->status instanceof \BackedEnum ? $approval->status->value : (string) $approval->status,
                'product_name' => $approval->printingRequest?->product_name,
                'notes' => $approval->notes,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filesPayload(User $customer): array
    {
        return PrintingRequest::query()
            ->where('user_id', $customer->id)
            ->whereNotNull('original_filename')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->flatMap(fn (PrintingRequest $request): array => $this->customerVisibleFilesForRequest($request))
            ->values()
            ->all();
    }

    public function decideApproval(
        CustomerPortalAccess $access,
        int $approvalId,
        string $decision,
        ?string $notes = null,
    ): PrintingCustomerApproval {
        $approval = PrintingCustomerApproval::query()
            ->with('printingRequest:id,user_id,product_name,status')
            ->find($approvalId);

        if ($approval === null || (int) $approval->printingRequest?->user_id !== (int) $access->customer_id) {
            throw ValidationException::withMessages([
                'approval' => ['Approval not found.'],
            ]);
        }

        $status = $approval->status instanceof PrintingCustomerApprovalStatus
            ? $approval->status
            : PrintingCustomerApprovalStatus::from((string) $approval->status);

        $normalized = strtolower(trim($decision));
        $target = match ($normalized) {
            'approve', 'approved' => PrintingCustomerApprovalStatus::Approved,
            'reject', 'rejected' => PrintingCustomerApprovalStatus::Rejected,
            default => throw ValidationException::withMessages([
                'decision' => ['Decision must be approve or reject.'],
            ]),
        };

        if ($status === $target) {
            return $approval;
        }

        if ($status !== PrintingCustomerApprovalStatus::Pending) {
            throw ValidationException::withMessages([
                'approval' => ['This approval has already been decided.'],
            ]);
        }

        $approval->update([
            'status' => $target,
            'decided_at' => now(),
            'notes' => $notes ?? $approval->notes,
        ]);

        $fresh = $approval->fresh(['printingRequest:id,user_id,product_name,status']) ?? $approval;
        $this->recordPortalApprovalActivity($fresh, $target);

        return $fresh;
    }

    private function recordPortalApprovalActivity(
        PrintingCustomerApproval $approval,
        PrintingCustomerApprovalStatus $decision,
    ): void {
        $quotation = PrintingQuotation::query()
            ->where('printing_request_id', $approval->printing_request_id)
            ->where('customer_id', $approval->printingRequest?->user_id)
            ->orderByDesc('id')
            ->first();

        if ($quotation === null) {
            return;
        }

        $this->quotations->recordEvent($quotation, 'portal_approval_decided', null, 'customer', [
            'approval_id' => $approval->id,
            'decision' => $decision->value,
            'type' => $approval->type instanceof \BackedEnum ? $approval->type->value : (string) $approval->type,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customerVisibleFilesForRequest(PrintingRequest $request): array
    {
        if (blank($request->original_filename)) {
            return [];
        }

        return [[
            'printing_request_id' => $request->id,
            'name' => $request->original_filename,
            'kind' => 'artwork',
            'customer_visible' => true,
        ]];
    }

    private function timelineLabel(string $event): string
    {
        return match ($event) {
            'sent' => 'تم إرسال عرض السعر',
            'viewed' => 'تم فتح عرض السعر',
            'accepted' => 'تم قبول عرض السعر',
            'rejected' => 'تم رفض عرض السعر',
            'payment_recorded' => 'تم استلام دفعة',
            'status_changed' => 'تحديث حالة الطلب',
            'payment_requirement_met' => 'اكتملت متطلبات الدفع',
            'portal_approval_decided' => 'تم البت في الموافقة',
            default => $event,
        };
    }

    private function quotationStatusLabel(PrintingQuotationStatus $status): string
    {
        return match ($status) {
            PrintingQuotationStatus::Sent, PrintingQuotationStatus::Viewed => 'بانتظار ردك',
            PrintingQuotationStatus::Accepted => 'مقبول',
            PrintingQuotationStatus::Rejected => 'مرفوض',
            PrintingQuotationStatus::Expired => 'منتهي',
            default => $status->value,
        };
    }

    private function assertStaffCanManagePortal(User $staff): void
    {
        if (! ($staff->role instanceof UserRole) || ! $staff->role->canReviewPrintingRequests()) {
            throw ValidationException::withMessages([
                'portal' => ['You cannot manage customer portal access.'],
            ]);
        }
    }

    private function assertCustomer(User $customer): void
    {
        if (! ($customer->role instanceof UserRole) || $customer->role !== UserRole::Customer) {
            throw ValidationException::withMessages([
                'customer' => ['Portal access is only available for customers.'],
            ]);
        }
    }
}
