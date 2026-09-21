<?php

namespace App\Services\Printing;

use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PrintingQuotation;
use App\Models\PrintingQuotationEvent;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Customer\CustomerCommunicationService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Pdf\PdfFactory;
use App\Services\Workflow\WorkflowAutomationEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrintingQuotationService
{
    public function __construct(
        private readonly WorkflowAutomationEngine $workflows,
        private readonly PaymentProviderManager $paymentProviders,
        private readonly CustomerCommunicationService $communications,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PrintingQuotation>
     */
    public function paginateFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        $this->assertCanManage($actor);

        $query = PrintingQuotation::query()->with([
            'printingRequest:id,product_name,quantity,status,user_id',
            'customer:id,name,email',
            'creator:id,name,email',
        ]);

        if (isset($filters['printing_request_id'])) {
            $query->where('printing_request_id', (int) $filters['printing_request_id']);
        }

        if (is_string($filters['status'] ?? null) && in_array($filters['status'], PrintingQuotationStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('id')->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 50)));
    }

    public function load(PrintingQuotation $quotation): PrintingQuotation
    {
        return $quotation->load([
            'printingRequest:id,product_name,quantity,status,user_id,quoted_price,estimated_price',
            'customer:id,name,email',
            'creator:id,name,email',
            'payments',
            'supersedes:id,reference,revision,status',
            'events' => fn ($q) => $q->limit(50),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{quotation: PrintingQuotation, public_token: null}
     */
    public function create(User $actor, PrintingRequest $request, array $data): array
    {
        $this->assertCanManage($actor);

        $amounts = $this->resolveAmounts($request, $data);
        $policy = $this->resolvePolicy($request, $data);
        $deposit = $this->resolveDeposit($policy, $amounts['total'], $data);

        $quotation = DB::transaction(function () use ($actor, $request, $data, $amounts, $policy, $deposit): PrintingQuotation {
            $quotation = PrintingQuotation::query()->create([
                'reference' => $this->generateReference(),
                'revision' => 1,
                'printing_request_id' => $request->id,
                'customer_id' => $request->user_id,
                'created_by' => $actor->id,
                'status' => PrintingQuotationStatus::Draft,
                'currency' => $data['currency'] ?? 'SAR',
                'subtotal' => $amounts['subtotal'],
                'tax_amount' => $amounts['tax_amount'],
                'discount_amount' => $amounts['discount_amount'],
                'total' => $amounts['total'],
                'deposit_required' => $deposit,
                'payment_policy' => $policy,
                'valid_until' => $data['valid_until'] ?? now()->addDays(14)->toDateString(),
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'snapshot' => [
                    'line_items' => [[
                        'description' => $request->product_name,
                        'quantity' => (int) $request->quantity,
                        'unit_price' => $amounts['subtotal'],
                        'line_total' => $amounts['subtotal'],
                    ]],
                ],
            ]);

            $this->recordEvent($quotation, 'created', $actor, 'staff');

            return $this->load($quotation);
        });

        return ['quotation' => $quotation, 'public_token' => null];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(User $actor, PrintingQuotation $quotation, array $data): PrintingQuotation
    {
        $this->assertCanManage($actor);
        $this->assertDraft($quotation);

        $quotation->loadMissing('printingRequest');
        $request = $quotation->printingRequest;
        if ($request === null) {
            throw ValidationException::withMessages([
                'quotation' => ['Printing request is missing.'],
            ]);
        }

        $amounts = $this->resolveAmounts($request, array_merge([
            'subtotal' => $quotation->subtotal,
            'tax_amount' => $quotation->tax_amount,
            'discount_amount' => $quotation->discount_amount,
            'total' => $quotation->total,
        ], $data));

        $policy = array_key_exists('payment_policy', $data)
            ? $this->resolvePolicy($request, $data)
            : ($quotation->payment_policy instanceof PrintingPaymentPolicy
                ? $quotation->payment_policy
                : PrintingPaymentPolicy::from((string) $quotation->payment_policy));

        $deposit = $this->resolveDeposit($policy, $amounts['total'], array_merge([
            'deposit_required' => $quotation->deposit_required,
        ], $data));

        $snapshot = is_array($quotation->snapshot) ? $quotation->snapshot : [];
        $snapshot['line_items'] = [[
            'description' => $request->product_name,
            'quantity' => (int) $request->quantity,
            'unit_price' => $amounts['subtotal'],
            'line_total' => $amounts['subtotal'],
        ]];

        $quotation->update([
            'subtotal' => $amounts['subtotal'],
            'tax_amount' => $amounts['tax_amount'],
            'discount_amount' => $amounts['discount_amount'],
            'total' => $amounts['total'],
            'deposit_required' => $deposit,
            'payment_policy' => $policy,
            'currency' => $data['currency'] ?? $quotation->currency,
            'valid_until' => array_key_exists('valid_until', $data) ? $data['valid_until'] : $quotation->valid_until,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $quotation->notes,
            'terms' => array_key_exists('terms', $data) ? $data['terms'] : $quotation->terms,
            'snapshot' => $snapshot,
        ]);

        $this->recordEvent($quotation, 'updated', $actor, 'staff');

        return $this->load($quotation->fresh() ?? $quotation);
    }

    /**
     * @return array{quotation: PrintingQuotation, public_token: string}
     */
    public function send(User $actor, PrintingQuotation $quotation): array
    {
        $this->assertCanManage($actor);

        $status = $this->statusOf($quotation);
        if (! in_array($status, [PrintingQuotationStatus::Draft, PrintingQuotationStatus::Sent], true)) {
            throw ValidationException::withMessages([
                'quotation' => ['يمكن إرسال عروض الأسعار المسودة فقط.'],
            ]);
        }

        if ($this->policyOf($quotation) !== PrintingPaymentPolicy::None
            && bccomp((string) $quotation->total, '0', 2) < 1) {
            throw ValidationException::withMessages([
                'total' => ['يجب أن يكون إجمالي عرض السعر أكبر من صفر.'],
            ]);
        }

        $rawToken = $this->generateRawToken();

        $quotation = DB::transaction(function () use ($actor, $quotation, $rawToken): PrintingQuotation {
            $quotation->update([
                'status' => PrintingQuotationStatus::Sent,
                'sent_at' => $quotation->sent_at ?? now(),
                'public_token_hash' => PrintingQuotation::hashToken($rawToken),
                'public_token_hint' => substr($rawToken, -8),
                'token_revoked_at' => null,
                'viewed_at' => null,
            ]);

            $this->recordEvent($quotation, 'sent', $actor, 'staff', [
                'token_hint' => substr($rawToken, -8),
            ]);

            return $this->load($quotation->fresh() ?? $quotation);
        });

        $this->dispatchWorkflow(WorkflowTrigger::PrintingQuotationSent, $quotation, $actor);

        // Single email orchestration — automations stay in-app only; dedupe prevents double-send with /email.
        $this->communications->sendQuotationEmail($quotation, $rawToken, $actor);

        return ['quotation' => $quotation, 'public_token' => $rawToken];
    }

    /**
     * @return array{quotation: PrintingQuotation, public_token: null}
     */
    public function revise(User $actor, PrintingQuotation $quotation): array
    {
        $this->assertCanManage($actor);

        $status = $this->statusOf($quotation);
        if (! in_array($status, [PrintingQuotationStatus::Sent, PrintingQuotationStatus::Viewed], true)) {
            throw ValidationException::withMessages([
                'quotation' => ['Only sent or viewed quotations can be revised.'],
            ]);
        }

        $revision = DB::transaction(function () use ($actor, $quotation): PrintingQuotation {
            $quotation->update([
                'token_revoked_at' => now(),
                'status' => PrintingQuotationStatus::Cancelled,
            ]);
            $this->recordEvent($quotation, 'superseded', $actor, 'staff');

            $next = PrintingQuotation::query()->create([
                'reference' => $this->revisionReference($quotation->reference, ((int) $quotation->revision) + 1),
                'revision' => ((int) $quotation->revision) + 1,
                'printing_request_id' => $quotation->printing_request_id,
                'customer_id' => $quotation->customer_id,
                'created_by' => $actor->id,
                'status' => PrintingQuotationStatus::Draft,
                'currency' => $quotation->currency,
                'subtotal' => $quotation->subtotal,
                'tax_amount' => $quotation->tax_amount,
                'discount_amount' => $quotation->discount_amount,
                'total' => $quotation->total,
                'deposit_required' => $quotation->deposit_required,
                'payment_policy' => $quotation->payment_policy,
                'valid_until' => $quotation->valid_until,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'supersedes_id' => $quotation->id,
                'snapshot' => $quotation->snapshot,
            ]);

            $this->recordEvent($next, 'revised', $actor, 'staff', [
                'supersedes_id' => $quotation->id,
            ]);

            return $this->load($next);
        });

        return ['quotation' => $revision, 'public_token' => null];
    }

    public function findUsableByRawToken(string $rawToken): PrintingQuotation
    {
        $quotation = PrintingQuotation::findByRawToken($rawToken);

        if ($quotation === null) {
            throw ValidationException::withMessages([
                'token' => ['عرض السعر غير موجود.'],
            ]);
        }

        if ($quotation->isTokenRevoked()) {
            throw ValidationException::withMessages([
                'token' => ['رابط عرض السعر لم يعد صالحًا.'],
            ]);
        }

        $status = $this->statusOf($quotation);
        if (in_array($status, [PrintingQuotationStatus::Expired, PrintingQuotationStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'token' => ['عرض السعر لم يعد متاحًا.'],
            ]);
        }

        if ($quotation->isExpiredByDate() && $status->isPubliclyActionable()) {
            $this->markExpired($quotation);
            throw ValidationException::withMessages([
                'token' => ['انتهت صلاحية عرض السعر.'],
            ]);
        }

        return $this->load($quotation);
    }

    public function findByTrackingToken(string $rawToken): PrintingQuotation
    {
        $quotation = PrintingQuotation::findByTrackingToken($rawToken);

        if ($quotation === null) {
            throw ValidationException::withMessages([
                'token' => ['Tracking link not found.'],
            ]);
        }

        return $quotation->load([
            'printingRequest:id,product_name,required_date,status,delivered_at,original_filename,file_path',
            'events' => fn ($q) => $q->orderBy('id')->limit(100),
        ]);
    }

    /**
     * First customer open of the tracking page only (access audit).
     */
    public function recordTrackView(PrintingQuotation $quotation): void
    {
        $already = PrintingQuotationEvent::query()
            ->where('printing_quotation_id', $quotation->id)
            ->where('event', 'track_viewed')
            ->exists();

        if ($already) {
            return;
        }

        $this->recordEvent($quotation, 'track_viewed', null, 'customer');
    }

    /**
     * @return array<string, mixed>
     */
    public function trackingPayload(PrintingQuotation $quotation): array
    {
        $request = $quotation->printingRequest;
        $status = $request?->status instanceof PrintingRequestStatus
            ? $request->status
            : PrintingRequestStatus::from((string) ($request?->status ?? PrintingRequestStatus::Pending->value));

        return [
            'status_key' => PrintingCustomerStatusMapper::key($status),
            'status_label' => PrintingCustomerStatusMapper::label($status),
            'product_name' => $request?->product_name,
            'required_date' => $request?->required_date?->toDateString(),
            'delivered_at' => $request?->delivered_at?->toIso8601String(),
            'reference' => $quotation->reference,
            'timeline' => $this->publicTimelineEvents($quotation),
            'files' => $this->customerVisibleFiles($request),
            'delivery' => $request !== null
                ? app(PrintingDeliveryService::class)->customerSafePayloadForRequest($request)
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staffTimeline(User $actor, PrintingQuotation $quotation): array
    {
        $this->assertCanManage($actor);
        $quotation->loadMissing(['events' => fn ($q) => $q->orderBy('id')->limit(200)]);

        return $quotation->events->map(function (PrintingQuotationEvent $event): array {
            return [
                'id' => $event->id,
                'event' => $event->event,
                'actor_id' => $event->actor_id,
                'actor_type' => $event->actor_type,
                'meta' => is_array($event->meta) ? $event->meta : null,
                'created_at' => $event->created_at?->toIso8601String(),
                'public' => $this->isPublicTimelineEvent($event->event),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function recordPublicSafeStatusChanged(
        PrintingQuotation $quotation,
        PrintingRequestStatus $from,
        PrintingRequestStatus $to,
        ?User $actor = null,
    ): void {
        $this->recordEvent($quotation, 'status_changed', $actor, $actor ? 'staff' : 'system', [
            'from_status' => $from->value,
            'to_status' => $to->value,
            'status_key' => PrintingCustomerStatusMapper::key($to),
            'status_label' => PrintingCustomerStatusMapper::label($to),
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordPaymentRecorded(PrintingQuotation $quotation, array $meta, ?User $actor = null): void
    {
        $safe = [
            'amount' => $meta['amount'] ?? null,
            'currency' => $meta['currency'] ?? $quotation->currency,
            'method' => $meta['method'] ?? null,
            'paid_at' => $meta['paid_at'] ?? now()->toIso8601String(),
        ];

        $this->recordEvent($quotation, 'payment_recorded', $actor, $actor ? 'staff' : 'system', $safe);
    }

    public function paymentReceiptPayload(Payment $payment): array
    {
        $payment->loadMissing('printingQuotation:id,reference,currency');

        return [
            'payment_id' => $payment->id,
            'quotation_reference' => $payment->printingQuotation?->reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->payment_method instanceof \BackedEnum
                ? $payment->payment_method->value
                : (string) $payment->payment_method,
            'paid_at' => $payment->paid_at?->toIso8601String() ?? $payment->created_at?->toIso8601String(),
            'reference_number' => $payment->reference_number,
            'status' => $payment->status instanceof \BackedEnum
                ? $payment->status->value
                : (string) $payment->status,
        ];
    }

    public function paymentReceiptResponse(User $actor, PrintingQuotation $quotation, Payment $payment): Response|JsonResponse
    {
        $this->assertCanManage($actor);

        if ((int) $payment->printing_quotation_id !== (int) $quotation->id) {
            throw ValidationException::withMessages([
                'payment' => ['Payment does not belong to this quotation.'],
            ]);
        }

        $payload = $this->paymentReceiptPayload($payment);
        $format = request()->query('format', 'json');

        if ($format === 'pdf' || $format === 'html') {
            $html = $this->renderReceiptHtml($payload);

            if ($format === 'pdf' && class_exists(Pdf::class)) {
                try {
                    $pdf = app(PdfFactory::class)->loadHtml($html);

                    return $pdf->download('receipt-'.$quotation->reference.'-'.$payment->id.'.pdf');
                } catch (\Throwable) {
                    // fall through
                }
            }

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(PrintingQuotation $quotation): array
    {
        $quotation->loadMissing('printingRequest:id,product_name,quantity');
        $snapshot = is_array($quotation->snapshot) ? $quotation->snapshot : [];
        $lineItems = $snapshot['line_items'] ?? [[
            'description' => $quotation->printingRequest?->product_name,
            'quantity' => $quotation->printingRequest?->quantity,
            'unit_price' => $quotation->subtotal,
            'line_total' => $quotation->subtotal,
        ]];

        return [
            'reference' => $quotation->reference,
            'revision' => $quotation->revision,
            'status' => $this->statusOf($quotation)->value,
            'currency' => $quotation->currency,
            'subtotal' => $quotation->subtotal,
            'tax_amount' => $quotation->tax_amount,
            'discount_amount' => $quotation->discount_amount,
            'total' => $quotation->total,
            'deposit_required' => $quotation->deposit_required,
            'payment_policy' => $this->policyOf($quotation)->value,
            'valid_until' => $quotation->valid_until?->toDateString(),
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'line_items' => $lineItems,
            'product_name' => $quotation->printingRequest?->product_name,
            'quantity' => $quotation->printingRequest?->quantity,
            'accepted_at' => $quotation->accepted_at?->toIso8601String(),
            'rejected_at' => $quotation->rejected_at?->toIso8601String(),
            'payment_summary' => $this->paymentSummary($quotation),
            'paytabs_available' => $this->paymentProviders->paytabsAvailable(),
        ];
    }

    public function recordView(PrintingQuotation $quotation): PrintingQuotation
    {
        $status = $this->statusOf($quotation);

        if ($status === PrintingQuotationStatus::Viewed || $status === PrintingQuotationStatus::Accepted) {
            return $this->load($quotation);
        }

        if ($status !== PrintingQuotationStatus::Sent) {
            return $this->load($quotation);
        }

        $quotation->update([
            'status' => PrintingQuotationStatus::Viewed,
            'viewed_at' => $quotation->viewed_at ?? now(),
        ]);

        $this->recordEvent($quotation, 'viewed', null, 'customer');

        return $this->load($quotation->fresh() ?? $quotation);
    }

    /**
     * @return array{quotation: PrintingQuotation, tracking_token: string|null}
     */
    public function acceptByToken(string $rawToken): array
    {
        return DB::transaction(function () use ($rawToken): array {
            $found = PrintingQuotation::findByRawToken($rawToken);
            if ($found === null || $found->isTokenRevoked()) {
                throw ValidationException::withMessages([
                    'token' => ['عرض السعر غير موجود.'],
                ]);
            }

            /** @var PrintingQuotation $quotation */
            $quotation = PrintingQuotation::query()->whereKey($found->id)->lockForUpdate()->firstOrFail();

            if ($quotation->isTokenRevoked()) {
                throw ValidationException::withMessages([
                    'token' => ['رابط عرض السعر لم يعد صالحًا.'],
                ]);
            }

            $status = $this->statusOf($quotation);

            if ($status === PrintingQuotationStatus::Accepted) {
                return ['quotation' => $this->load($quotation), 'tracking_token' => null];
            }

            if ($quotation->isExpiredByDate() || $status === PrintingQuotationStatus::Expired) {
                $this->markExpired($quotation);
                throw ValidationException::withMessages([
                    'token' => ['انتهت صلاحية عرض السعر.'],
                ]);
            }

            if (! $status->isPubliclyActionable()) {
                throw ValidationException::withMessages([
                    'quotation' => ['لا يمكن قبول عرض السعر هذا.'],
                ]);
            }

            $trackingRaw = $this->generateRawToken();
            $snapshot = $this->buildAcceptedSnapshot($quotation);

            $quotation->update([
                'status' => PrintingQuotationStatus::Accepted,
                'accepted_at' => now(),
                'snapshot' => $snapshot,
                'tracking_token_hash' => PrintingQuotation::hashToken($trackingRaw),
                'tracking_token_hint' => substr($trackingRaw, -8),
            ]);

            $this->recordEvent($quotation, 'accepted', null, 'customer');

            $loaded = $this->load($quotation->fresh() ?? $quotation);
            $this->dispatchWorkflow(WorkflowTrigger::PrintingQuotationAccepted, $loaded, null);

            return ['quotation' => $loaded, 'tracking_token' => $trackingRaw];
        });
    }

    public function rejectByToken(string $rawToken, ?string $reason = null): PrintingQuotation
    {
        return DB::transaction(function () use ($rawToken, $reason): PrintingQuotation {
            $found = PrintingQuotation::findByRawToken($rawToken);
            if ($found === null || $found->isTokenRevoked()) {
                throw ValidationException::withMessages([
                    'token' => ['عرض السعر غير موجود.'],
                ]);
            }

            /** @var PrintingQuotation $quotation */
            $quotation = PrintingQuotation::query()->whereKey($found->id)->lockForUpdate()->firstOrFail();

            $status = $this->statusOf($quotation);

            if ($status === PrintingQuotationStatus::Rejected) {
                return $this->load($quotation);
            }

            if ($quotation->isExpiredByDate() || $status === PrintingQuotationStatus::Expired) {
                $this->markExpired($quotation);
                throw ValidationException::withMessages([
                    'token' => ['انتهت صلاحية عرض السعر.'],
                ]);
            }

            if (! $status->isPubliclyActionable()) {
                throw ValidationException::withMessages([
                    'quotation' => ['لا يمكن رفض عرض السعر هذا.'],
                ]);
            }

            $quotation->update([
                'status' => PrintingQuotationStatus::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->recordEvent($quotation, 'rejected', null, 'customer', [
                'reason' => $reason,
            ]);

            $loaded = $this->load($quotation->fresh() ?? $quotation);
            $this->dispatchWorkflow(WorkflowTrigger::PrintingQuotationRejected, $loaded, null);

            return $loaded;
        });
    }

    public function expireDue(): int
    {
        $count = 0;

        PrintingQuotation::query()
            ->whereIn('status', [
                PrintingQuotationStatus::Sent->value,
                PrintingQuotationStatus::Viewed->value,
            ])
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($quotations) use (&$count): void {
                foreach ($quotations as $quotation) {
                    $this->markExpired($quotation);
                    $count++;
                }
            });

        return $count;
    }

    public function renderHtmlForPortal(PrintingQuotation $quotation): string
    {
        return $this->renderHtml($quotation);
    }

    public function pdfResponse(User $actor, PrintingQuotation $quotation): Response
    {
        $this->assertCanManage($actor);
        $quotation = $this->load($quotation);
        $html = $this->renderHtml($quotation);
        $format = request()->query('format');

        if ($format !== 'html' && class_exists(Pdf::class)) {
            try {
                $pdf = app(PdfFactory::class)->loadHtml($html);

                return $pdf->download($quotation->reference.'-r'.$quotation->revision.'.pdf');
            } catch (\Throwable) {
                // fall through to HTML
            }
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$quotation->reference.'.html"',
        ]);
    }

    /**
     * @return array{
     *     paid: string,
     *     remaining: string,
     *     total: string,
     *     deposit_required: string|null,
     *     payment_policy: string,
     *     requirement_met: bool,
     *     amount_due_now: string
     * }
     */
    public function paymentSummary(PrintingQuotation $quotation): array
    {
        $paymentIds = $quotation->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->pluck('id');

        $gross = $this->money((string) $quotation->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount'));

        $refunded = '0.00';
        if ($paymentIds->isNotEmpty()) {
            $refunded = $this->money((string) PaymentRefund::query()
                ->whereIn('payment_id', $paymentIds)
                ->where('status', PaymentRefundStatus::Confirmed->value)
                ->sum('amount'));
        }

        $net = bcsub($gross, $refunded, 2);
        $paid = bccomp($net, '0.00', 2) === 1 ? $net : '0.00';
        $total = $this->money((string) $quotation->total);
        $remaining = bccomp($total, $paid, 2) === 1 ? bcsub($total, $paid, 2) : '0.00';
        $policy = $this->policyOf($quotation);
        $deposit = $quotation->deposit_required !== null
            ? $this->money((string) $quotation->deposit_required)
            : null;

        $requirementMet = match ($policy) {
            PrintingPaymentPolicy::None => true,
            PrintingPaymentPolicy::Deposit => $deposit !== null && bccomp($paid, $deposit, 2) >= 0,
            PrintingPaymentPolicy::Full => bccomp($paid, $total, 2) >= 0,
        };

        $amountDueNow = $remaining;
        if ($policy === PrintingPaymentPolicy::Deposit && $deposit !== null) {
            if (bccomp($paid, $deposit, 2) >= 0) {
                $amountDueNow = '0.00';
            } else {
                $needed = bcsub($deposit, $paid, 2);
                $amountDueNow = bccomp($needed, $remaining, 2) === 1 ? $remaining : $needed;
            }
        } elseif ($policy === PrintingPaymentPolicy::None) {
            $amountDueNow = '0.00';
        }

        $summary = [
            'paid' => $paid,
            'remaining' => $remaining,
            'total' => $total,
            'deposit_required' => $deposit,
            'payment_policy' => $policy->value,
            'requirement_met' => $requirementMet,
            'amount_due_now' => $amountDueNow,
        ];

        // Customer-safe refunded total only when confirmed refunds exist.
        if (bccomp($refunded, '0.00', 2) === 1) {
            $summary['refunded_total'] = $refunded;
        }

        return $summary;
    }

    public function paidAmount(PrintingQuotation $quotation): string
    {
        $paymentIds = $quotation->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->pluck('id');

        $sum = $quotation->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        $gross = $this->money((string) $sum);

        if ($paymentIds->isEmpty()) {
            return $gross;
        }

        $refunded = PaymentRefund::query()
            ->whereIn('payment_id', $paymentIds)
            ->where('status', PaymentRefundStatus::Confirmed->value)
            ->sum('amount');

        $net = bcsub($gross, $this->money((string) $refunded), 2);

        return bccomp($net, '0.00', 2) === 1 ? $net : '0.00';
    }

    public function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canReviewPrintingRequests()) {
            throw ValidationException::withMessages([
                'quotation' => ['You cannot manage printing quotations.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function recordEvent(
        PrintingQuotation $quotation,
        string $event,
        ?User $actor,
        string $actorType = 'system',
        ?array $meta = null,
    ): void {
        PrintingQuotationEvent::query()->create([
            'printing_quotation_id' => $quotation->id,
            'event' => $event,
            'actor_id' => $actor?->id,
            'actor_type' => $actorType,
            'meta' => $meta,
        ]);
    }

    public function notifyPaymentRequirementMet(PrintingQuotation $quotation, ?User $actor = null): void
    {
        $summary = $this->paymentSummary($quotation);
        if (! $summary['requirement_met']) {
            return;
        }

        $this->recordEvent($quotation, 'payment_requirement_met', $actor, $actor ? 'staff' : 'system', $summary);
        $this->dispatchWorkflow(WorkflowTrigger::PrintingPaymentRequirementMet, $quotation, $actor);

        $request = $quotation->printingRequest ?? PrintingRequest::query()->find($quotation->printing_request_id);
        if ($request === null) {
            return;
        }

        try {
            $eligible = app(PrintingExecutionEligibilityService::class)->eligible($request);
            if ($eligible['eligible']) {
                $this->dispatchWorkflow(WorkflowTrigger::PrintingExecutionEligible, $quotation, $actor);
            }
        } catch (\Throwable) {
            // Eligibility / workflow must never break payment confirmation.
        }
    }

    public function notifyPaymentConfirmed(PrintingQuotation $quotation, ?User $actor = null, ?Payment $payment = null): void
    {
        $this->dispatchWorkflow(WorkflowTrigger::PaymentConfirmed, $quotation, $actor);

        if ($payment !== null) {
            try {
                $this->communications->notifyPaymentConfirmed($quotation, $payment);
            } catch (\Throwable) {
                // Customer email must never break payment confirmation.
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{subtotal: string, tax_amount: string, discount_amount: string, total: string}
     */
    private function resolveAmounts(PrintingRequest $request, array $data): array
    {
        if (isset($data['total']) || isset($data['subtotal'])) {
            $subtotal = $this->money((string) ($data['subtotal'] ?? $data['total'] ?? '0'));
            $tax = $this->money((string) ($data['tax_amount'] ?? '0'));
            $discount = $this->money((string) ($data['discount_amount'] ?? '0'));
            $total = isset($data['total'])
                ? $this->money((string) $data['total'])
                : $this->money(bcadd(bcsub($subtotal, $discount, 2), $tax, 2));

            return compact('subtotal') + [
                'tax_amount' => $tax,
                'discount_amount' => $discount,
                'total' => $total,
            ];
        }

        $base = $request->quoted_price ?? $request->estimated_price;
        if ($base === null) {
            throw ValidationException::withMessages([
                'total' => ['Provide amounts or set a quoted/estimated price on the printing request.'],
            ]);
        }

        $subtotal = $this->money((string) $base);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'total' => $subtotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePolicy(PrintingRequest $request, array $data): PrintingPaymentPolicy
    {
        if (isset($data['payment_policy'])) {
            return PrintingPaymentPolicy::from((string) $data['payment_policy']);
        }

        if ($request->payment_policy) {
            return PrintingPaymentPolicy::from((string) $request->payment_policy);
        }

        return PrintingPaymentPolicy::Full;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDeposit(PrintingPaymentPolicy $policy, string $total, array $data): ?string
    {
        if ($policy !== PrintingPaymentPolicy::Deposit) {
            return null;
        }

        if (isset($data['deposit_required']) && $data['deposit_required'] !== null && $data['deposit_required'] !== '') {
            $deposit = $this->money((string) $data['deposit_required']);
            if (bccomp($deposit, '0', 2) < 1 || bccomp($deposit, $total, 2) === 1) {
                throw ValidationException::withMessages([
                    'deposit_required' => ['يجب أن يكون المقدم أكبر من صفر ولا يتجاوز الإجمالي.'],
                ]);
            }

            return $deposit;
        }

        return $this->money(bcmul($total, '0.50', 2));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAcceptedSnapshot(PrintingQuotation $quotation): array
    {
        $quotation->loadMissing('printingRequest:id,product_name,quantity');
        $existing = is_array($quotation->snapshot) ? $quotation->snapshot : [];

        return [
            'reference' => $quotation->reference,
            'revision' => $quotation->revision,
            'currency' => $quotation->currency,
            'subtotal' => $quotation->subtotal,
            'tax_amount' => $quotation->tax_amount,
            'discount_amount' => $quotation->discount_amount,
            'total' => $quotation->total,
            'deposit_required' => $quotation->deposit_required,
            'payment_policy' => $this->policyOf($quotation)->value,
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'product_name' => $quotation->printingRequest?->product_name,
            'quantity' => $quotation->printingRequest?->quantity,
            'line_items' => $existing['line_items'] ?? [[
                'description' => $quotation->printingRequest?->product_name,
                'quantity' => $quotation->printingRequest?->quantity,
                'unit_price' => $quotation->subtotal,
                'line_total' => $quotation->subtotal,
            ]],
            'accepted_at' => now()->toIso8601String(),
        ];
    }

    private function markExpired(PrintingQuotation $quotation): void
    {
        if ($this->statusOf($quotation) === PrintingQuotationStatus::Expired) {
            return;
        }

        $quotation->update([
            'status' => PrintingQuotationStatus::Expired,
            'expired_at' => now(),
            'token_revoked_at' => $quotation->token_revoked_at ?? now(),
        ]);

        $this->recordEvent($quotation, 'expired', null, 'system');
    }

    private function generateReference(): string
    {
        $year = now()->format('Y');
        $prefix = 'PQ-'.$year.'-';

        $latest = PrintingQuotation::query()
            ->where('reference', 'like', $prefix.'%')
            ->where('revision', 1)
            ->lockForUpdate()
            ->orderByDesc('reference')
            ->value('reference');

        $next = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    private function revisionReference(string $currentReference, int $revision): string
    {
        $base = preg_replace('/-R\d+$/', '', $currentReference) ?: $currentReference;

        if ($revision <= 1) {
            return $base;
        }

        return $base.'-R'.$revision;
    }

    private function generateRawToken(): string
    {
        return Str::random(64);
    }

    private function statusOf(PrintingQuotation $quotation): PrintingQuotationStatus
    {
        return $quotation->status instanceof PrintingQuotationStatus
            ? $quotation->status
            : PrintingQuotationStatus::from((string) $quotation->status);
    }

    private function policyOf(PrintingQuotation $quotation): PrintingPaymentPolicy
    {
        return $quotation->payment_policy instanceof PrintingPaymentPolicy
            ? $quotation->payment_policy
            : PrintingPaymentPolicy::from((string) $quotation->payment_policy);
    }

    private function assertDraft(PrintingQuotation $quotation): void
    {
        if ($this->statusOf($quotation) !== PrintingQuotationStatus::Draft) {
            throw ValidationException::withMessages([
                'quotation' => ['يمكن تحديث عروض الأسعار المسودة فقط.'],
            ]);
        }
    }

    private function money(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function dispatchWorkflow(WorkflowTrigger $trigger, PrintingQuotation $quotation, ?User $actor): void
    {
        try {
            $this->workflows->dispatch($trigger->value, [
                'source_type' => 'printing_quotation',
                'source_id' => $quotation->id,
                'actor_id' => $actor?->id,
                'title' => 'عرض طباعة '.$quotation->reference,
                'related_type' => 'printing_request',
                'related_id' => $quotation->printing_request_id,
                'printing_quotation_id' => $quotation->id,
                'printing_request_id' => $quotation->printing_request_id,
                'payload' => [
                    'printing_quotation_id' => $quotation->id,
                    'printing_request_id' => $quotation->printing_request_id,
                    'reference' => $quotation->reference,
                    'revision' => $quotation->revision,
                    'status' => $this->statusOf($quotation)->value,
                    'total' => $quotation->total,
                    'payment_policy' => $this->policyOf($quotation)->value,
                ],
            ], (string) $quotation->id);
        } catch (\Throwable) {
            // Workflow hooks must never break quotation flows.
        }
    }

    private function renderHtml(PrintingQuotation $quotation): string
    {
        $snapshot = is_array($quotation->snapshot) ? $quotation->snapshot : null;
        $useSnapshot = $this->statusOf($quotation) === PrintingQuotationStatus::Accepted && $snapshot !== null;

        $reference = $useSnapshot ? (string) ($snapshot['reference'] ?? $quotation->reference) : $quotation->reference;
        $subtotal = $useSnapshot ? (string) ($snapshot['subtotal'] ?? $quotation->subtotal) : (string) $quotation->subtotal;
        $tax = $useSnapshot ? (string) ($snapshot['tax_amount'] ?? $quotation->tax_amount) : (string) $quotation->tax_amount;
        $discount = $useSnapshot ? (string) ($snapshot['discount_amount'] ?? $quotation->discount_amount) : (string) $quotation->discount_amount;
        $total = $useSnapshot ? (string) ($snapshot['total'] ?? $quotation->total) : (string) $quotation->total;
        $currency = $useSnapshot ? (string) ($snapshot['currency'] ?? $quotation->currency) : (string) $quotation->currency;
        $notes = $useSnapshot ? (string) ($snapshot['notes'] ?? $quotation->notes) : (string) $quotation->notes;
        $terms = $useSnapshot ? (string) ($snapshot['terms'] ?? $quotation->terms) : (string) $quotation->terms;
        $lineItems = $useSnapshot
            ? ($snapshot['line_items'] ?? [])
            : (($quotation->snapshot['line_items'] ?? []) ?: [[
                'description' => $quotation->printingRequest?->product_name,
                'quantity' => $quotation->printingRequest?->quantity,
                'unit_price' => $quotation->subtotal,
                'line_total' => $quotation->subtotal,
            ]]);

        $rows = collect($lineItems)->map(function (array $item): string {
            return '<tr><td>'.e((string) ($item['description'] ?? '')).'</td><td>'.e((string) ($item['quantity'] ?? '')).'</td><td>'.e((string) ($item['unit_price'] ?? '')).'</td><td>'.e((string) ($item['line_total'] ?? '')).'</td></tr>';
        })->implode('');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.e($reference).'</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px;text-align:left}</style>
</head><body>
<h1>Printing Quotation '.e($reference).' (r'.e((string) $quotation->revision).')</h1>
<p>Customer: '.e((string) $quotation->customer?->name).'</p>
<table><thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead><tbody>'.$rows.'</tbody></table>
<p>Subtotal: '.e($subtotal).'</p>
<p>Discount: '.e($discount).'</p>
<p>Tax: '.e($tax).'</p>
<p><strong>Total: '.e($total).' '.e($currency).'</strong></p>
<p>'.nl2br(e($notes)).'</p>
<p>'.nl2br(e($terms)).'</p>
</body></html>';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicTimelineEvents(PrintingQuotation $quotation): array
    {
        $events = $quotation->relationLoaded('events')
            ? $quotation->events
            : $quotation->events()->orderBy('id')->limit(100)->get();

        return $events
            ->filter(fn (PrintingQuotationEvent $event): bool => $this->isPublicTimelineEvent($event->event))
            ->map(function (PrintingQuotationEvent $event): array {
                return [
                    'event' => $event->event,
                    'meta' => $this->publicEventMeta($event),
                    'created_at' => $event->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function isPublicTimelineEvent(string $event): bool
    {
        return in_array($event, [
            'accepted',
            'payment_recorded',
            'status_changed',
            'payment_requirement_met',
        ], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publicEventMeta(PrintingQuotationEvent $event): ?array
    {
        $meta = is_array($event->meta) ? $event->meta : [];

        return match ($event->event) {
            'status_changed' => [
                'status_key' => $meta['status_key'] ?? $meta['to_status'] ?? null,
                'status_label' => $meta['status_label']
                    ?? (isset($meta['to_status']) ? PrintingCustomerStatusMapper::label((string) $meta['to_status']) : null),
            ],
            'payment_recorded' => [
                'amount' => $meta['amount'] ?? null,
                'currency' => $meta['currency'] ?? null,
                'method' => $meta['method'] ?? null,
                'paid_at' => $meta['paid_at'] ?? null,
            ],
            'payment_requirement_met' => [
                'requirement_met' => true,
            ],
            'accepted' => null,
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customerVisibleFiles(?PrintingRequest $request): array
    {
        if ($request === null || blank($request->original_filename)) {
            return [];
        }

        return [[
            'name' => $request->original_filename,
            'kind' => 'artwork',
        ]];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderReceiptHtml(array $payload): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Receipt</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}</style></head><body>
<h1>Payment Receipt</h1>
<p>Quotation: '.e((string) ($payload['quotation_reference'] ?? '')).'</p>
<p>Amount: '.e((string) ($payload['amount'] ?? '')).' '.e((string) ($payload['currency'] ?? '')).'</p>
<p>Method: '.e((string) ($payload['method'] ?? '')).'</p>
<p>Date: '.e((string) ($payload['paid_at'] ?? '')).'</p>
<p>Reference: '.e((string) ($payload['reference_number'] ?? '')).'</p>
</body></html>';
    }
}
