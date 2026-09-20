<?php

namespace App\Services\Quotes;

use App\Enums\CatalogPricingMode;
use App\Enums\CommercialQuotationStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\QuotationLineCategory;
use App\Enums\QuoteRequestSource;
use App\Enums\QuoteRequestStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\CommercialQuotation;
use App\Models\CommercialQuotationEvent;
use App\Models\CommercialQuotationItem;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Notifications\QuoteNotification;
use App\Services\Payments\CardPaymentGateway;
use App\Services\Pdf\PdfFactory;
use App\Services\Workflow\WorkflowAutomationEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommercialQuotationService
{
    public function __construct(
        private readonly WorkflowAutomationEngine $workflows,
        private readonly QuoteRequestService $quoteRequests,
        private readonly CardPaymentGateway $cards,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CommercialQuotation>
     */
    public function paginateForStaff(User $actor, array $filters = []): LengthAwarePaginator
    {
        $this->assertCanManage($actor);

        $query = CommercialQuotation::query()->with([
            'quoteRequest:id,reference,title,status,customer_id',
            'customer:id,name,email',
            'creator:id,name,email',
        ]);

        if (isset($filters['quote_request_id'])) {
            $query->where('quote_request_id', (int) $filters['quote_request_id']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (is_string($filters['status'] ?? null) && in_array($filters['status'], CommercialQuotationStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (is_string($filters['q'] ?? null) && trim($filters['q']) !== '') {
            $term = '%'.trim($filters['q']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('reference', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        return $query->latest('id')->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 50)));
    }

    public function load(CommercialQuotation $quotation, bool $includeInternal = true): CommercialQuotation
    {
        $quotation->load([
            'quoteRequest:id,reference,title,status,customer_id,source_type,payload',
            'customer:id,name,email',
            'creator:id,name,email',
            'items',
            'payments',
            'supersedes:id,reference,revision,status',
            'events' => fn ($q) => $q->with('actor:id,name')->limit(50),
        ]);

        if (! $includeInternal) {
            $quotation->makeHidden(['internal_notes']);
        }

        return $quotation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromQuoteRequest(User $actor, QuoteRequest $request, array $data = []): CommercialQuotation
    {
        $this->assertCanCreate($actor);

        $source = $request->source_type instanceof QuoteRequestSource
            ? $request->source_type
            : QuoteRequestSource::from((string) $request->source_type);

        if ($source->usesPrintingQuotation()) {
            throw ValidationException::withMessages([
                'quote_request' => ['Printing quote requests must use printing quotations.'],
            ]);
        }

        $items = $this->resolvePrefillItems($request, $data);

        return DB::transaction(function () use ($actor, $request, $data, $items): CommercialQuotation {
            $policy = isset($data['payment_policy'])
                ? PrintingPaymentPolicy::from((string) $data['payment_policy'])
                : PrintingPaymentPolicy::None;

            $totals = $this->recalculate($items, [
                'discount_amount' => $data['discount_amount'] ?? '0',
                'tax_amount' => $data['tax_amount'] ?? '0',
                'shipping_amount' => $data['shipping_amount'] ?? '0',
                'rental_amount' => $data['rental_amount'] ?? '0',
            ]);

            $deposit = $this->resolveDeposit($policy, $totals['total'], $data);

            $quotation = CommercialQuotation::query()->create([
                'reference' => $this->generateReference(),
                'revision' => 1,
                'quote_request_id' => $request->id,
                'customer_id' => $request->customer_id,
                'order_id' => $request->order_id,
                'created_by' => $actor->id,
                'status' => CommercialQuotationStatus::Draft,
                'currency' => $data['currency'] ?? 'SAR',
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_amount' => $totals['shipping_amount'],
                'rental_amount' => $totals['rental_amount'],
                'total' => $totals['total'],
                'deposit_required' => $deposit,
                'payment_policy' => $policy,
                'valid_until' => $data['valid_until'] ?? now()->addDays(14)->toDateString(),
                'execution_duration' => $data['execution_duration'] ?? null,
                'revision_count' => $data['revision_count'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'delivery_terms' => $data['delivery_terms'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
            ]);

            $this->syncItems($quotation, $items);

            $request->update([
                'quotation_type' => 'COMMERCIAL',
                'quotation_id' => $quotation->id,
            ]);

            $status = $request->status instanceof QuoteRequestStatus
                ? $request->status
                : QuoteRequestStatus::from((string) $request->status);

            if (in_array($status, [QuoteRequestStatus::New, QuoteRequestStatus::ReadyToPrice], true)) {
                $request->update(['status' => QuoteRequestStatus::UnderReview]);
            }

            $this->recordEvent($quotation, 'created', $actor, 'staff');

            return $this->load($quotation->fresh() ?? $quotation);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(User $actor, CommercialQuotation $quotation, array $data): CommercialQuotation
    {
        $this->assertCanManage($actor);
        $this->assertDraft($quotation);

        return DB::transaction(function () use ($actor, $quotation, $data): CommercialQuotation {
            $items = array_key_exists('items', $data) && is_array($data['items'])
                ? $this->normalizeItems($data['items'])
                : $quotation->items->map(fn (CommercialQuotationItem $item): array => [
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'category' => $item->category instanceof QuotationLineCategory
                        ? $item->category->value
                        : ($item->category !== null ? (string) $item->category : null),
                    'meta' => is_array($item->meta) ? $item->meta : null,
                ])->all();

            $totals = $this->recalculate($items, [
                'discount_amount' => $data['discount_amount'] ?? $quotation->discount_amount,
                'tax_amount' => $data['tax_amount'] ?? $quotation->tax_amount,
                'shipping_amount' => $data['shipping_amount'] ?? $quotation->shipping_amount,
                'rental_amount' => $data['rental_amount'] ?? $quotation->rental_amount,
            ]);

            $policy = array_key_exists('payment_policy', $data)
                ? PrintingPaymentPolicy::from((string) $data['payment_policy'])
                : $this->policyOf($quotation);

            $deposit = $this->resolveDeposit($policy, $totals['total'], array_merge([
                'deposit_required' => $quotation->deposit_required,
            ], $data));

            $quotation->update([
                'currency' => $data['currency'] ?? $quotation->currency,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_amount' => $totals['shipping_amount'],
                'rental_amount' => $totals['rental_amount'],
                'total' => $totals['total'],
                'deposit_required' => $deposit,
                'payment_policy' => $policy,
                'valid_until' => array_key_exists('valid_until', $data) ? $data['valid_until'] : $quotation->valid_until,
                'execution_duration' => array_key_exists('execution_duration', $data) ? $data['execution_duration'] : $quotation->execution_duration,
                'revision_count' => array_key_exists('revision_count', $data) ? $data['revision_count'] : $quotation->revision_count,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $quotation->notes,
                'terms' => array_key_exists('terms', $data) ? $data['terms'] : $quotation->terms,
                'delivery_terms' => array_key_exists('delivery_terms', $data) ? $data['delivery_terms'] : $quotation->delivery_terms,
                'internal_notes' => array_key_exists('internal_notes', $data) ? $data['internal_notes'] : $quotation->internal_notes,
            ]);

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $this->syncItems($quotation, $items);
            }

            $this->recordEvent($quotation, 'updated', $actor, 'staff');

            return $this->load($quotation->fresh() ?? $quotation);
        });
    }

    /**
     * @return array{quotation: CommercialQuotation, public_token: string}
     */
    public function send(User $actor, CommercialQuotation $quotation): array
    {
        $this->assertCanManage($actor);

        $status = $this->statusOf($quotation);
        if (! in_array($status, [CommercialQuotationStatus::Draft, CommercialQuotationStatus::Sent], true)) {
            throw ValidationException::withMessages([
                'quotation' => ['Only draft quotations can be sent.'],
            ]);
        }

        $quotation->loadMissing(['items', 'customer', 'quoteRequest']);

        if ($quotation->customer === null) {
            throw ValidationException::withMessages([
                'customer' => ['Customer is required before sending.'],
            ]);
        }

        $usableItems = $quotation->items->filter(function (CommercialQuotationItem $item): bool {
            return bccomp((string) $item->quantity, '0', 2) === 1
                && bccomp((string) $item->unit_price, '0', 2) >= 0;
        });

        if ($usableItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['At least one line item with quantity > 0 and unit price >= 0 is required.'],
            ]);
        }

        if (blank($quotation->currency)) {
            throw ValidationException::withMessages([
                'currency' => ['Currency is required.'],
            ]);
        }

        if ($quotation->valid_until === null) {
            throw ValidationException::withMessages([
                'valid_until' => ['Valid until date is required.'],
            ]);
        }

        $policy = $this->policyOf($quotation);
        if ($policy === PrintingPaymentPolicy::Deposit) {
            $deposit = $this->money((string) ($quotation->deposit_required ?? '0'));
            if (bccomp($deposit, '0', 2) < 1) {
                throw ValidationException::withMessages([
                    'deposit_required' => ['Deposit is required for deposit payment policy.'],
                ]);
            }
        }

        $items = $quotation->items->map(fn (CommercialQuotationItem $item): array => [
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'category' => $item->category instanceof QuotationLineCategory
                ? $item->category->value
                : ($item->category !== null ? (string) $item->category : null),
            'meta' => is_array($item->meta) ? $item->meta : null,
        ])->all();

        $totals = $this->recalculate($items, [
            'discount_amount' => $quotation->discount_amount,
            'tax_amount' => $quotation->tax_amount,
            'shipping_amount' => $quotation->shipping_amount,
            'rental_amount' => $quotation->rental_amount,
        ]);

        if (bccomp($totals['total'], '0', 2) < 0) {
            throw ValidationException::withMessages([
                'total' => ['Quotation total is invalid.'],
            ]);
        }

        if ($policy !== PrintingPaymentPolicy::None && bccomp($totals['total'], '0', 2) < 1) {
            throw ValidationException::withMessages([
                'total' => ['Quotation total must be greater than zero.'],
            ]);
        }

        $rawToken = Str::random(64);

        $quotation = DB::transaction(function () use ($actor, $quotation, $rawToken, $totals, $items): CommercialQuotation {
            $this->syncItems($quotation, $items);

            $quotation->update([
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_amount' => $totals['shipping_amount'],
                'rental_amount' => $totals['rental_amount'],
                'total' => $totals['total'],
                'status' => CommercialQuotationStatus::Sent,
                'sent_at' => $quotation->sent_at ?? now(),
                'public_token_hash' => CommercialQuotation::hashToken($rawToken),
                'public_token_hint' => substr($rawToken, -8),
                'token_revoked_at' => null,
                'viewed_at' => null,
            ]);

            if ($quotation->quoteRequest !== null) {
                $this->quoteRequests->markQuoted($quotation->quoteRequest, 'COMMERCIAL', (int) $quotation->id);
            }

            $this->recordEvent($quotation, 'sent', $actor, 'staff', [
                'token_hint' => substr($rawToken, -8),
            ]);

            return $this->load($quotation->fresh() ?? $quotation);
        });

        $this->notifyCustomer(
            $quotation,
            'تم إرسال عرض السعر',
            "تم إرسال عرض السعر الخاص بطلبك {$quotation->quoteRequest?->reference}.",
            '/cq/'.$rawToken,
            'commercial_quotation_sent',
        );

        $this->dispatchWorkflow(WorkflowTrigger::QuotationSent, $quotation, $actor);

        return ['quotation' => $quotation, 'public_token' => $rawToken];
    }

    public function revise(User $actor, CommercialQuotation $quotation): CommercialQuotation
    {
        $this->assertCanManage($actor);

        $status = $this->statusOf($quotation);
        if (! in_array($status, [
            CommercialQuotationStatus::Sent,
            CommercialQuotationStatus::Viewed,
            CommercialQuotationStatus::Rejected,
        ], true)) {
            $requestStatus = null;
            $quotation->loadMissing('quoteRequest');
            if ($quotation->quoteRequest !== null) {
                $requestStatus = $quotation->quoteRequest->status instanceof QuoteRequestStatus
                    ? $quotation->quoteRequest->status
                    : QuoteRequestStatus::from((string) $quotation->quoteRequest->status);
            }

            if ($requestStatus !== QuoteRequestStatus::RevisionRequested) {
                throw ValidationException::withMessages([
                    'quotation' => ['Only sent, viewed, or revision-requested quotations can be revised.'],
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $quotation): CommercialQuotation {
            $quotation->loadMissing(['items', 'quoteRequest']);

            $quotation->update([
                'token_revoked_at' => now(),
                'status' => CommercialQuotationStatus::Cancelled,
            ]);
            $this->recordEvent($quotation, 'superseded', $actor, 'staff');

            $nextRevision = ((int) $quotation->revision) + 1;

            $next = CommercialQuotation::query()->create([
                'reference' => $this->revisionReference($quotation->reference, $nextRevision),
                'revision' => $nextRevision,
                'quote_request_id' => $quotation->quote_request_id,
                'customer_id' => $quotation->customer_id,
                'order_id' => $quotation->order_id,
                'created_by' => $actor->id,
                'status' => CommercialQuotationStatus::Draft,
                'currency' => $quotation->currency,
                'subtotal' => $quotation->subtotal,
                'discount_amount' => $quotation->discount_amount,
                'tax_amount' => $quotation->tax_amount,
                'shipping_amount' => $quotation->shipping_amount,
                'rental_amount' => $quotation->rental_amount,
                'total' => $quotation->total,
                'deposit_required' => $quotation->deposit_required,
                'payment_policy' => $quotation->payment_policy,
                'valid_until' => $quotation->valid_until,
                'execution_duration' => $quotation->execution_duration,
                'revision_count' => $quotation->revision_count,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'delivery_terms' => $quotation->delivery_terms,
                'internal_notes' => $quotation->internal_notes,
                'supersedes_id' => $quotation->id,
            ]);

            $copied = $quotation->items->map(fn (CommercialQuotationItem $item): array => [
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'subtotal' => (string) $item->subtotal,
                'category' => $item->category instanceof QuotationLineCategory
                    ? $item->category->value
                    : ($item->category !== null ? (string) $item->category : null),
                'meta' => is_array($item->meta) ? $item->meta : null,
            ])->all();

            $this->syncItems($next, $copied);

            if ($quotation->quoteRequest !== null) {
                $requestStatus = $quotation->quoteRequest->status instanceof QuoteRequestStatus
                    ? $quotation->quoteRequest->status
                    : QuoteRequestStatus::from((string) $quotation->quoteRequest->status);

                if ($requestStatus !== QuoteRequestStatus::RevisionRequested) {
                    $quotation->quoteRequest->update(['status' => QuoteRequestStatus::UnderReview]);
                }

                $quotation->quoteRequest->update([
                    'quotation_type' => 'COMMERCIAL',
                    'quotation_id' => $next->id,
                ]);
            }

            $this->recordEvent($next, 'revised', $actor, 'staff', [
                'supersedes_id' => $quotation->id,
            ]);

            return $this->load($next);
        });
    }

    public function findUsableByRawToken(string $rawToken): CommercialQuotation
    {
        $quotation = CommercialQuotation::findByRawToken($rawToken);

        if ($quotation === null) {
            throw ValidationException::withMessages([
                'token' => ['Quotation not found.'],
            ]);
        }

        if ($quotation->isTokenRevoked()) {
            throw ValidationException::withMessages([
                'token' => ['This quotation link is no longer valid.'],
            ]);
        }

        $status = $this->statusOf($quotation);
        if (in_array($status, [CommercialQuotationStatus::Expired, CommercialQuotationStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'token' => ['This quotation is no longer available.'],
            ]);
        }

        if ($quotation->isExpiredByDate() && $status->isPubliclyActionable()) {
            $this->markExpired($quotation);
            throw ValidationException::withMessages([
                'token' => ['This quotation has expired.'],
            ]);
        }

        return $this->load($quotation, includeInternal: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(CommercialQuotation $quotation): array
    {
        $quotation->loadMissing([
            'items',
            'customer:id,name,email',
            'quoteRequest:id,reference,title,source_type,source_id,order_id,status',
            'payments',
        ]);

        $status = $this->statusOf($quotation);
        $summary = $this->paymentSummary($quotation);
        $expiresInDays = $this->expiresInDays($quotation);
        $canAction = $status->isPubliclyActionable() && ! $quotation->isExpiredByDate();
        $paytabsConfigured = $this->cards->isConfigured();
        $policy = $this->policyOf($quotation);
        $paymentRequired = $status === CommercialQuotationStatus::Accepted
            && in_array($policy, [PrintingPaymentPolicy::Deposit, PrintingPaymentPolicy::Full], true)
            && ! ($summary['requirement_met'] ?? false)
            && bccomp((string) ($summary['amount_due_now'] ?? '0'), '0', 2) === 1;

        $latestPayment = $quotation->payments->sortByDesc('id')->first();
        $latestPaymentStatus = $latestPayment?->status instanceof PaymentStatus
            ? $latestPayment->status->value
            : ($latestPayment !== null ? (string) $latestPayment->status : null);

        $processing = $latestPaymentStatus === PaymentStatus::Processing->value;
        $canCheckout = $paymentRequired
            && $paytabsConfigured
            && ! $processing;

        return [
            'id' => $quotation->id,
            'reference' => $quotation->reference,
            'revision' => $quotation->revision,
            'quote_request_id' => $quotation->quote_request_id,
            'status' => $status->value,
            'status_label_ar' => $status->labelAr(),
            'quote_request_reference' => $quotation->quoteRequest?->reference,
            'quote_request_title' => $quotation->quoteRequest?->title,
            'customer' => $quotation->customer ? [
                'id' => $quotation->customer->id,
                'name' => $quotation->customer->name,
                'email' => $quotation->customer->email,
            ] : null,
            'currency' => $quotation->currency,
            'subtotal' => $quotation->subtotal,
            'discount_amount' => $quotation->discount_amount,
            'tax_amount' => $quotation->tax_amount,
            'shipping_amount' => $quotation->shipping_amount,
            'rental_amount' => $quotation->rental_amount,
            'total' => $quotation->total,
            'deposit_required' => $quotation->deposit_required,
            'payment_policy' => $policy->value,
            'valid_until' => $quotation->valid_until?->toDateString(),
            'expires_in_days' => $expiresInDays,
            'execution_duration' => $quotation->execution_duration,
            'revision_count' => $quotation->revision_count,
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'delivery_terms' => $quotation->delivery_terms,
            'sent_at' => $quotation->sent_at?->toIso8601String(),
            'items' => $quotation->items->map(fn (CommercialQuotationItem $item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'category' => $item->category instanceof QuotationLineCategory
                    ? $item->category->value
                    : $item->category,
            ])->values()->all(),
            'accepted_at' => $quotation->accepted_at?->toIso8601String(),
            'rejected_at' => $quotation->rejected_at?->toIso8601String(),
            'payment_summary' => $summary,
            'latest_payment_status' => $latestPaymentStatus,
            'can_accept' => $canAction,
            'can_request_revision' => $canAction,
            'can_reject' => $canAction,
            'can_checkout' => $canCheckout,
            'payment_required' => $paymentRequired,
            'paytabs_configured' => $paytabsConfigured,
            'payment_cta_label' => $this->paymentCtaLabel($summary),
            'card_unavailable_message' => ($paymentRequired && ! $paytabsConfigured)
                ? 'الدفع الإلكتروني غير متاح حالياً. تواصل مع فريق حبر وأبعاد لإتمام الدفع.'
                : null,
            'revisions' => $this->revisionHistory($quotation),
            'revision_diff' => $this->revisionDiff($quotation),
            'linkages' => $this->linkages($quotation),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staffPayload(CommercialQuotation $quotation): array
    {
        $loaded = $this->load($quotation, includeInternal: true);
        $public = $this->publicPayload($loaded);
        $public['internal_notes'] = $loaded->internal_notes;
        $public['execution_project_id'] = $loaded->execution_project_id;
        $public['items'] = $loaded->items->map(fn (CommercialQuotationItem $item): array => [
            'id' => $item->id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'subtotal' => $item->subtotal,
            'category' => $item->category instanceof QuotationLineCategory
                ? $item->category->value
                : $item->category,
            'meta' => $item->meta,
            'selected_supplier_quote_id' => $item->selected_supplier_quote_id,
        ])->values()->all();
        $public['events'] = $loaded->events->map(fn (CommercialQuotationEvent $event): array => [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'label_ar' => $this->eventLabelAr($event->event_type),
            'actor_type' => $event->actor_type,
            'actor' => $event->actor ? ['id' => $event->actor->id, 'name' => $event->actor->name] : null,
            'meta' => $event->meta,
            'created_at' => $event->created_at?->toIso8601String(),
        ])->values()->all();
        $public['payments'] = $loaded->payments->map(fn (Payment $payment): array => [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status instanceof PaymentStatus
                ? $payment->status->value
                : (string) $payment->status,
            'created_at' => $payment->created_at?->toIso8601String(),
        ])->values()->all();
        $public['sourcing'] = app(QuotationSupplierSourcingService::class)->sourcingPayload($loaded);

        return $public;
    }

    public function pdfResponseForStaff(User $actor, CommercialQuotation $quotation): Response
    {
        $this->assertCanManage($actor);

        return $this->pdfResponse($quotation);
    }

    public function pdfResponseForCustomer(User $customer, CommercialQuotation $quotation): Response
    {
        if ((int) $quotation->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $status = $this->statusOf($quotation);
        if (in_array($status, [CommercialQuotationStatus::Draft, CommercialQuotationStatus::Cancelled], true)) {
            abort(404);
        }

        return $this->pdfResponse($quotation);
    }

    public function pdfResponseByToken(string $token): Response
    {
        $quotation = $this->findUsableByRawToken($token);

        return $this->pdfResponse($quotation);
    }

    public function pdfResponse(CommercialQuotation $quotation): Response
    {
        $html = $this->renderHtml($quotation);
        $filename = $quotation->reference.'-r'.$quotation->revision.'.pdf';
        $format = request()->query('format');

        if ($format !== 'html' && class_exists(Pdf::class)) {
            try {
                $pdf = app(PdfFactory::class)->loadHtml($html);

                return $pdf->download($filename);
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
     * Render PDF binary for local QA / tooling without going through HTTP download headers.
     */
    public function pdfBinary(CommercialQuotation $quotation): string
    {
        return app(PdfFactory::class)->loadHtml($this->renderHtml($quotation))->output();
    }

    public function recordView(CommercialQuotation $quotation): CommercialQuotation
    {
        $status = $this->statusOf($quotation);

        if ($status === CommercialQuotationStatus::Viewed || $status === CommercialQuotationStatus::Accepted) {
            return $this->load($quotation, includeInternal: false);
        }

        if ($status !== CommercialQuotationStatus::Sent) {
            return $this->load($quotation, includeInternal: false);
        }

        $quotation->update([
            'status' => CommercialQuotationStatus::Viewed,
            'viewed_at' => $quotation->viewed_at ?? now(),
        ]);

        $this->recordEvent($quotation, 'viewed', null, 'customer');

        return $this->load($quotation->fresh() ?? $quotation, includeInternal: false);
    }

    /**
     * @return array{quotation: CommercialQuotation, tracking_token: string|null}
     */
    public function acceptByToken(string $token): array
    {
        return DB::transaction(function () use ($token): array {
            $found = CommercialQuotation::findByRawToken($token);
            if ($found === null || $found->isTokenRevoked()) {
                throw ValidationException::withMessages([
                    'token' => ['Quotation not found.'],
                ]);
            }

            /** @var CommercialQuotation $quotation */
            $quotation = CommercialQuotation::query()->whereKey($found->id)->lockForUpdate()->firstOrFail();

            if ($quotation->isTokenRevoked()) {
                throw ValidationException::withMessages([
                    'token' => ['This quotation link is no longer valid.'],
                ]);
            }

            $status = $this->statusOf($quotation);

            if ($status === CommercialQuotationStatus::Accepted) {
                return ['quotation' => $this->load($quotation, includeInternal: false), 'tracking_token' => null];
            }

            if ($quotation->isExpiredByDate() || $status === CommercialQuotationStatus::Expired) {
                $this->markExpired($quotation);
                throw ValidationException::withMessages([
                    'token' => ['This quotation has expired.'],
                ]);
            }

            if (! $status->isPubliclyActionable()) {
                throw ValidationException::withMessages([
                    'quotation' => ['This quotation cannot be accepted.'],
                ]);
            }

            $this->assertLatestAcceptableRevision($quotation);

            $trackingRaw = Str::random(64);
            $snapshot = $this->buildAcceptedSnapshot($quotation);

            $quotation->update([
                'status' => CommercialQuotationStatus::Accepted,
                'accepted_at' => now(),
                'snapshot' => $snapshot,
                'tracking_token_hash' => CommercialQuotation::hashToken($trackingRaw),
                'tracking_token_hint' => substr($trackingRaw, -8),
            ]);

            $quotation->loadMissing('quoteRequest');
            if ($quotation->quoteRequest !== null) {
                $this->quoteRequests->markAccepted($quotation->quoteRequest);
            }

            $this->recordEvent($quotation, 'accepted', null, 'customer');

            $loaded = $this->load($quotation->fresh() ?? $quotation, includeInternal: false);

            $ownerActor = $loaded->creator ?? User::query()->find($loaded->created_by);
            if ($ownerActor !== null) {
                app(QuotationSupplierSourcingService::class)
                    ->attachSelectedSuppliersOnAccept($loaded, $ownerActor);
                $loaded = $this->load($loaded->fresh() ?? $loaded, includeInternal: false);
            }

            $this->notifyStaff(
                $loaded,
                'تم قبول عرض السعر',
                "قبل العميل عرض السعر {$loaded->reference}.",
                'commercial_quotation_accepted',
            );

            $this->dispatchWorkflow(WorkflowTrigger::QuotationAccepted, $loaded, null);

            return ['quotation' => $loaded, 'tracking_token' => $trackingRaw];
        });
    }

    public function rejectByToken(string $token, ?string $reason = null): CommercialQuotation
    {
        return DB::transaction(function () use ($token, $reason): CommercialQuotation {
            $found = CommercialQuotation::findByRawToken($token);
            if ($found === null || $found->isTokenRevoked()) {
                throw ValidationException::withMessages([
                    'token' => ['Quotation not found.'],
                ]);
            }

            /** @var CommercialQuotation $quotation */
            $quotation = CommercialQuotation::query()->whereKey($found->id)->lockForUpdate()->firstOrFail();

            $status = $this->statusOf($quotation);

            if ($status === CommercialQuotationStatus::Rejected) {
                return $this->load($quotation, includeInternal: false);
            }

            if ($quotation->isExpiredByDate() || $status === CommercialQuotationStatus::Expired) {
                $this->markExpired($quotation);
                throw ValidationException::withMessages([
                    'token' => ['This quotation has expired.'],
                ]);
            }

            if (! $status->isPubliclyActionable()) {
                throw ValidationException::withMessages([
                    'quotation' => ['This quotation cannot be rejected.'],
                ]);
            }

            $this->assertLatestAcceptableRevision($quotation);

            $quotation->update([
                'status' => CommercialQuotationStatus::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $quotation->loadMissing('quoteRequest');
            if ($quotation->quoteRequest !== null) {
                $this->quoteRequests->markRejected($quotation->quoteRequest, $reason);
            }

            $this->recordEvent($quotation, 'rejected', null, 'customer', [
                'reason' => $reason,
            ]);

            $loaded = $this->load($quotation->fresh() ?? $quotation, includeInternal: false);

            $this->notifyStaff(
                $loaded,
                'تم رفض عرض السعر',
                "رفض العميل عرض السعر {$loaded->reference}.",
                'commercial_quotation_rejected',
            );

            $this->dispatchWorkflow(WorkflowTrigger::QuotationRejected, $loaded, null);

            return $loaded;
        });
    }

    public function requestRevisionByToken(string $token, ?string $reason = null, ?string $reasonCode = null): CommercialQuotation
    {
        return DB::transaction(function () use ($token, $reason, $reasonCode): CommercialQuotation {
            $found = CommercialQuotation::findByRawToken($token);
            if ($found === null || $found->isTokenRevoked()) {
                throw ValidationException::withMessages([
                    'token' => ['Quotation not found.'],
                ]);
            }

            /** @var CommercialQuotation $quotation */
            $quotation = CommercialQuotation::query()->whereKey($found->id)->lockForUpdate()->firstOrFail();

            $status = $this->statusOf($quotation);

            if ($quotation->isExpiredByDate() || $status === CommercialQuotationStatus::Expired) {
                $this->markExpired($quotation);
                throw ValidationException::withMessages([
                    'token' => ['This quotation has expired.'],
                ]);
            }

            if (! $status->isPubliclyActionable()) {
                throw ValidationException::withMessages([
                    'quotation' => ['This quotation cannot request a revision.'],
                ]);
            }

            $this->assertLatestAcceptableRevision($quotation);

            $quotation->update([
                'revision_reason' => $reason,
            ]);

            $quotation->loadMissing('quoteRequest');
            if ($quotation->quoteRequest !== null) {
                $this->quoteRequests->markRevisionRequested($quotation->quoteRequest, $reason);
            }

            $this->recordEvent($quotation, 'revision_requested', null, 'customer', [
                'reason' => $reason,
                'reason_code' => $reasonCode,
            ]);

            $loaded = $this->load($quotation->fresh() ?? $quotation, includeInternal: false);

            $this->notifyStaff(
                $loaded,
                'طلب تعديل على العرض',
                "طلب العميل تعديلاً على عرض السعر {$loaded->reference}.",
                'commercial_quotation_revision_requested',
            );

            $this->dispatchWorkflow(WorkflowTrigger::QuotationRevisionRequested, $loaded, null);

            return $loaded;
        });
    }

    /**
     * @return array{
     *     required: string,
     *     paid: string,
     *     outstanding: string,
     *     remaining: string,
     *     total: string,
     *     deposit_required: string|null,
     *     payment_policy: string,
     *     requirement_met: bool,
     *     amount_due_now: string,
     *     refunded_total?: string
     * }
     */
    public function paymentSummary(CommercialQuotation $quotation): array
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

        $required = match ($policy) {
            PrintingPaymentPolicy::None => '0.00',
            PrintingPaymentPolicy::Deposit => $deposit ?? '0.00',
            PrintingPaymentPolicy::Full => $total,
        };

        $outstanding = bccomp($required, $paid, 2) === 1 ? bcsub($required, $paid, 2) : '0.00';

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
            'required' => $required,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'remaining' => $remaining,
            'total' => $total,
            'deposit_required' => $deposit,
            'payment_policy' => $policy->value,
            'requirement_met' => $requirementMet,
            'amount_due_now' => $amountDueNow,
        ];

        if (bccomp($refunded, '0.00', 2) === 1) {
            $summary['refunded_total'] = $refunded;
        }

        return $summary;
    }

    public function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canManageQuoteRequests()) {
            throw ValidationException::withMessages([
                'quotation' => ['You cannot manage commercial quotations.'],
            ]);
        }
    }

    public function assertCanCreate(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canCreateQuotations()) {
            throw ValidationException::withMessages([
                'quotation' => ['You cannot create commercial quotations.'],
            ]);
        }
    }

    private function generateReference(): string
    {
        $year = now()->format('Y');
        $prefix = 'CQ-'.$year.'-';

        $latest = CommercialQuotation::query()
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

    private function money(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $data
     * @return array{
     *     subtotal: string,
     *     discount_amount: string,
     *     tax_amount: string,
     *     shipping_amount: string,
     *     rental_amount: string,
     *     total: string,
     *     items: list<array<string, mixed>>
     * }
     */
    private function recalculate(array $items, array $data): array
    {
        $normalized = [];
        $subtotal = '0.00';

        foreach ($items as $item) {
            $qty = $this->money((string) ($item['quantity'] ?? '0'));
            $unit = $this->money((string) ($item['unit_price'] ?? '0'));
            $line = $this->money(bcmul($qty, $unit, 4));
            $subtotal = bcadd($subtotal, $line, 2);

            $normalized[] = array_merge($item, [
                'quantity' => $qty,
                'unit_price' => $unit,
                'subtotal' => $line,
            ]);
        }

        $discount = $this->money((string) ($data['discount_amount'] ?? '0'));
        $tax = $this->money((string) ($data['tax_amount'] ?? '0'));
        $shipping = $this->money((string) ($data['shipping_amount'] ?? '0'));
        $rental = $this->money((string) ($data['rental_amount'] ?? '0'));

        $total = $this->money(
            bcadd(
                bcadd(
                    bcsub($subtotal, $discount, 2),
                    $tax,
                    2
                ),
                bcadd($shipping, $rental, 2),
                2
            )
        );

        return [
            'subtotal' => $this->money($subtotal),
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'shipping_amount' => $shipping,
            'rental_amount' => $rental,
            'total' => $total,
            'items' => $normalized,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(CommercialQuotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        $totals = $this->recalculate($items, [
            'discount_amount' => '0',
            'tax_amount' => '0',
            'shipping_amount' => '0',
            'rental_amount' => '0',
        ]);

        foreach ($totals['items'] as $index => $item) {
            $category = $item['category'] ?? null;
            if (is_string($category) && $category !== '' && in_array($category, QuotationLineCategory::values(), true)) {
                $category = QuotationLineCategory::from($category);
            } elseif (! $category instanceof QuotationLineCategory) {
                $category = QuotationLineCategory::Other;
            }

            CommercialQuotationItem::query()->create([
                'commercial_quotation_id' => $quotation->id,
                'description' => (string) ($item['description'] ?? 'Item'),
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
                'category' => $category,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'meta' => is_array($item['meta'] ?? null) ? $item['meta'] : null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function recordEvent(
        CommercialQuotation $quotation,
        string $event,
        ?User $actor,
        string $actorType = 'system',
        ?array $meta = null,
    ): void {
        CommercialQuotationEvent::query()->create([
            'commercial_quotation_id' => $quotation->id,
            'event_type' => $event,
            'actor_id' => $actor?->id,
            'actor_type' => $actorType,
            'meta' => $meta,
        ]);
    }

    private function statusOf(CommercialQuotation $quotation): CommercialQuotationStatus
    {
        return $quotation->status instanceof CommercialQuotationStatus
            ? $quotation->status
            : CommercialQuotationStatus::from((string) $quotation->status);
    }

    private function policyOf(CommercialQuotation $quotation): PrintingPaymentPolicy
    {
        return $quotation->payment_policy instanceof PrintingPaymentPolicy
            ? $quotation->payment_policy
            : PrintingPaymentPolicy::from((string) ($quotation->payment_policy ?: PrintingPaymentPolicy::None->value));
    }

    private function dispatchWorkflow(WorkflowTrigger $trigger, CommercialQuotation $quotation, ?User $actor): void
    {
        try {
            $this->workflows->dispatch($trigger->value, [
                'source_type' => 'commercial_quotation',
                'source_id' => $quotation->id,
                'actor_id' => $actor?->id,
                'title' => 'عرض سعر '.$quotation->reference,
                'related_type' => 'quote_request',
                'related_id' => $quotation->quote_request_id,
                'commercial_quotation_id' => $quotation->id,
                'quote_request_id' => $quotation->quote_request_id,
                'payload' => [
                    'commercial_quotation_id' => $quotation->id,
                    'quote_request_id' => $quotation->quote_request_id,
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

    private function notifyCustomer(
        CommercialQuotation $quotation,
        string $title,
        string $body,
        string $actionUrl,
        string $type,
    ): void {
        $quotation->loadMissing('customer');
        if ($quotation->customer === null) {
            return;
        }

        $quotation->customer->notify(new QuoteNotification([
            'title' => $title,
            'message' => $body,
            'body' => $body,
            'href' => $actionUrl,
            'action_url' => $actionUrl,
            'type' => $type,
            'meta' => [
                'commercial_quotation_id' => $quotation->id,
                'quote_request_id' => $quotation->quote_request_id,
                'reference' => $quotation->reference,
            ],
        ]));
    }

    private function notifyStaff(
        CommercialQuotation $quotation,
        string $title,
        string $body,
        string $type,
    ): void {
        $recipients = User::query()
            ->whereIn('role', [
                UserRole::Owner->value,
                UserRole::AdminManager->value,
                UserRole::AccountManager->value,
                UserRole::SalesManager->value,
            ])
            ->where('is_active', true)
            ->get();

        $href = '/owner/quote-requests/'.($quotation->quote_request_id ?? '');
        Notification::send($recipients, new QuoteNotification([
            'title' => $title,
            'message' => $body,
            'body' => $body,
            'href' => $href,
            'action_url' => $href,
            'type' => $type,
            'meta' => [
                'commercial_quotation_id' => $quotation->id,
                'quote_request_id' => $quotation->quote_request_id,
                'reference' => $quotation->reference,
            ],
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function resolvePrefillItems(QuoteRequest $request, array $data): array
    {
        if (isset($data['items']) && is_array($data['items']) && $data['items'] !== []) {
            return $this->normalizeItems($data['items']);
        }

        $payload = is_array($request->payload) ? $request->payload : [];

        if (isset($payload['line_items']) && is_array($payload['line_items']) && $payload['line_items'] !== []) {
            return $this->normalizeItems($payload['line_items']);
        }

        $services = [];
        if (isset($payload['services']) && is_array($payload['services'])) {
            $services = $payload['services'];
        } elseif (isset($payload['selected_services']) && is_array($payload['selected_services'])) {
            $services = $payload['selected_services'];
        }

        if ($services === []) {
            return [[
                'description' => $request->title !== '' ? $request->title : 'خدمة',
                'quantity' => '1.00',
                'unit_price' => '0.00',
                'category' => QuotationLineCategory::Other->value,
            ]];
        }

        $items = [];
        foreach ($services as $service) {
            if (! is_array($service)) {
                continue;
            }

            $pricingMode = strtoupper((string) ($service['pricing_mode'] ?? $service['pricingMode'] ?? ''));
            $unitPrice = $service['unit_price'] ?? $service['price'] ?? $service['amount'] ?? '0';
            if ($pricingMode === CatalogPricingMode::Quote->value) {
                $unitPrice = '0';
            }

            $items[] = [
                'description' => (string) ($service['description'] ?? $service['name'] ?? $service['title'] ?? 'خدمة'),
                'quantity' => (string) ($service['quantity'] ?? $service['qty'] ?? '1'),
                'unit_price' => (string) $unitPrice,
                'category' => $service['category'] ?? QuotationLineCategory::Other->value,
                'meta' => array_filter([
                    'service_id' => $service['id'] ?? $service['service_id'] ?? null,
                    'pricing_mode' => $pricingMode !== '' ? $pricingMode : null,
                ], fn ($v) => $v !== null),
            ];
        }

        return $this->normalizeItems($items !== [] ? $items : [[
            'description' => $request->title !== '' ? $request->title : 'خدمة',
            'quantity' => '1.00',
            'unit_price' => '0.00',
            'category' => QuotationLineCategory::Other->value,
        ]]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'description' => (string) ($item['description'] ?? 'Item'),
                'quantity' => $this->money((string) ($item['quantity'] ?? $item['qty'] ?? '1')),
                'unit_price' => $this->money((string) ($item['unit_price'] ?? '0')),
                'category' => $item['category'] ?? QuotationLineCategory::Other->value,
                'sort_order' => $item['sort_order'] ?? null,
                'meta' => is_array($item['meta'] ?? null) ? $item['meta'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDeposit(PrintingPaymentPolicy $policy, string $total, array $data): string
    {
        if ($policy !== PrintingPaymentPolicy::Deposit) {
            return '0.00';
        }

        if (isset($data['deposit_required']) && $data['deposit_required'] !== null && $data['deposit_required'] !== '') {
            $deposit = $this->money((string) $data['deposit_required']);
            if (bccomp($deposit, '0', 2) < 1 || bccomp($deposit, $total, 2) === 1) {
                throw ValidationException::withMessages([
                    'deposit_required' => ['Deposit must be greater than zero and not exceed the total.'],
                ]);
            }

            return $deposit;
        }

        if (bccomp($total, '0', 2) < 1) {
            return '0.00';
        }

        return $this->money(bcmul($total, '0.50', 2));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAcceptedSnapshot(CommercialQuotation $quotation): array
    {
        $quotation->loadMissing('items');

        return [
            'reference' => $quotation->reference,
            'revision' => $quotation->revision,
            'currency' => $quotation->currency,
            'subtotal' => $quotation->subtotal,
            'discount_amount' => $quotation->discount_amount,
            'tax_amount' => $quotation->tax_amount,
            'shipping_amount' => $quotation->shipping_amount,
            'rental_amount' => $quotation->rental_amount,
            'total' => $quotation->total,
            'deposit_required' => $quotation->deposit_required,
            'payment_policy' => $this->policyOf($quotation)->value,
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'delivery_terms' => $quotation->delivery_terms,
            'execution_duration' => $quotation->execution_duration,
            'revision_count' => $quotation->revision_count,
            'items' => $quotation->items->map(fn (CommercialQuotationItem $item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'category' => $item->category instanceof QuotationLineCategory
                    ? $item->category->value
                    : $item->category,
            ])->values()->all(),
            'accepted_at' => now()->toIso8601String(),
        ];
    }

    private function assertLatestAcceptableRevision(CommercialQuotation $quotation): void
    {
        $latestId = CommercialQuotation::query()
            ->where('quote_request_id', $quotation->quote_request_id)
            ->where('status', '!=', CommercialQuotationStatus::Cancelled->value)
            ->orderByDesc('revision')
            ->value('id');

        if ((int) $latestId !== (int) $quotation->id) {
            throw ValidationException::withMessages([
                'quotation' => ['Only the latest quotation revision can be actioned.'],
            ]);
        }
    }

    private function assertDraft(CommercialQuotation $quotation): void
    {
        if ($this->statusOf($quotation) !== CommercialQuotationStatus::Draft) {
            throw ValidationException::withMessages([
                'quotation' => ['Only draft quotations can be updated.'],
            ]);
        }
    }

    public function expireDue(): int
    {
        $count = 0;

        CommercialQuotation::query()
            ->whereIn('status', [
                CommercialQuotationStatus::Sent->value,
                CommercialQuotationStatus::Viewed->value,
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

    private function markExpired(CommercialQuotation $quotation): void
    {
        if ($this->statusOf($quotation) === CommercialQuotationStatus::Expired) {
            return;
        }

        $quotation->update([
            'status' => CommercialQuotationStatus::Expired,
            'expired_at' => now(),
            'token_revoked_at' => $quotation->token_revoked_at ?? now(),
        ]);

        $this->recordEvent($quotation, 'expired', null, 'system');
    }

    private function expiresInDays(CommercialQuotation $quotation): ?int
    {
        if ($quotation->valid_until === null) {
            return null;
        }

        $status = $this->statusOf($quotation);
        if (! $status->isPubliclyActionable()) {
            return null;
        }

        $end = $quotation->valid_until->copy()->endOfDay();
        if ($end->isPast()) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($end->startOfDay());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function revisionHistory(CommercialQuotation $quotation): array
    {
        $family = CommercialQuotation::query()
            ->where('quote_request_id', $quotation->quote_request_id)
            ->orderByDesc('revision')
            ->get(['id', 'reference', 'revision', 'status', 'total', 'currency', 'sent_at', 'supersedes_id']);

        $latestActive = $family->first(function (CommercialQuotation $row): bool {
            return $this->statusOf($row) !== CommercialQuotationStatus::Cancelled;
        });

        return $family->map(function (CommercialQuotation $row) use ($latestActive): array {
            $status = $this->statusOf($row);
            $isCurrent = $latestActive !== null && (int) $row->id === (int) $latestActive->id;

            return [
                'id' => $row->id,
                'reference' => $row->reference,
                'revision' => $row->revision,
                'status' => $status->value,
                'label_ar' => $isCurrent
                    ? 'الإصدار '.$row->revision.' — الإصدار الحالي'
                    : 'الإصدار '.$row->revision.' — تم استبداله',
                'is_current' => $isCurrent,
                'total' => $row->total,
                'currency' => $row->currency,
                'sent_at' => $row->sent_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function revisionDiff(CommercialQuotation $quotation): ?array
    {
        if ($quotation->supersedes_id === null) {
            return null;
        }

        $previous = CommercialQuotation::query()
            ->with('items')
            ->find($quotation->supersedes_id);

        if ($previous === null) {
            return null;
        }

        $quotation->loadMissing('items');

        $prevMap = $previous->items->keyBy(fn (CommercialQuotationItem $item): string => mb_strtolower(trim($item->description)));
        $currMap = $quotation->items->keyBy(fn (CommercialQuotationItem $item): string => mb_strtolower(trim($item->description)));

        $added = [];
        $removed = [];
        $qtyChanged = [];

        foreach ($currMap as $key => $item) {
            if (! $prevMap->has($key)) {
                $added[] = $item->description;
            } elseif (bccomp((string) $prevMap[$key]->quantity, (string) $item->quantity, 2) !== 0) {
                $qtyChanged[] = [
                    'description' => $item->description,
                    'from' => $prevMap[$key]->quantity,
                    'to' => $item->quantity,
                ];
            }
        }

        foreach ($prevMap as $key => $item) {
            if (! $currMap->has($key)) {
                $removed[] = $item->description;
            }
        }

        return [
            'previous_total' => $previous->total,
            'new_total' => $quotation->total,
            'currency' => $quotation->currency,
            'items_added' => $added,
            'items_removed' => $removed,
            'quantity_changed' => $qtyChanged,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linkages(CommercialQuotation $quotation): array
    {
        $quotation->loadMissing('quoteRequest');
        $request = $quotation->quoteRequest;
        $source = $request?->source_type instanceof QuoteRequestSource
            ? $request->source_type
            : ($request !== null ? QuoteRequestSource::tryFrom((string) $request->source_type) : null);

        return [
            'order_id' => $quotation->order_id ?? $request?->order_id,
            'quote_request_id' => $quotation->quote_request_id,
            'printing_request_id' => $source === QuoteRequestSource::PrintingRequest ? $request?->source_id : null,
            'event_request_id' => $source === QuoteRequestSource::EventRequest ? $request?->source_id : null,
            'project_id' => $quotation->execution_project_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function paymentCtaLabel(array $summary): string
    {
        $policy = (string) ($summary['payment_policy'] ?? 'NONE');

        return match ($policy) {
            'NONE' => 'لا توجد دفعة مطلوبة',
            'DEPOSIT' => 'مطلوب دفع مقدم: '.($summary['amount_due_now'] ?? $summary['required'] ?? '0').' SAR',
            'FULL' => 'مطلوب دفع كامل: '.($summary['amount_due_now'] ?? $summary['required'] ?? '0').' SAR',
            default => 'حالة الدفع',
        };
    }

    public function eventLabelAr(string $event): string
    {
        return match ($event) {
            'created' => 'تم إنشاء عرض السعر',
            'updated' => 'تم تحديث عرض السعر',
            'sent' => 'تم إرسال عرض السعر',
            'viewed' => 'اطّلع العميل على عرض السعر',
            'accepted' => 'تم قبول العرض',
            'rejected' => 'تم رفض العرض',
            'revision_requested' => 'طلب العميل تعديلاً',
            'revised', 'superseded' => 'تم إنشاء إصدار جديد',
            'expired' => 'انتهت صلاحية العرض',
            'checkout_created' => 'تم بدء عملية الدفع',
            'payment_recorded' => 'تم استلام الدفعة',
            'supplier_quote_requested' => 'تم طلب عرض سعر من مورد',
            'supplier_quote_selected' => 'تم اختيار مورد للتنفيذ',
            'supplier_quote_rejected' => 'تم رفض عرض مورد',
            default => $event,
        };
    }

    private function renderHtml(CommercialQuotation $quotation): string
    {
        $quotation->loadMissing(['items', 'customer', 'quoteRequest']);

        $snapshot = is_array($quotation->snapshot) ? $quotation->snapshot : null;
        $useSnapshot = $this->statusOf($quotation) === CommercialQuotationStatus::Accepted && $snapshot !== null;

        $reference = $useSnapshot ? (string) ($snapshot['reference'] ?? $quotation->reference) : $quotation->reference;
        $revision = $useSnapshot ? (int) ($snapshot['revision'] ?? $quotation->revision) : (int) $quotation->revision;
        $currency = $useSnapshot ? (string) ($snapshot['currency'] ?? $quotation->currency) : (string) $quotation->currency;
        $subtotal = $useSnapshot ? (string) ($snapshot['subtotal'] ?? $quotation->subtotal) : (string) $quotation->subtotal;
        $discount = $useSnapshot ? (string) ($snapshot['discount_amount'] ?? $quotation->discount_amount) : (string) $quotation->discount_amount;
        $tax = $useSnapshot ? (string) ($snapshot['tax_amount'] ?? $quotation->tax_amount) : (string) $quotation->tax_amount;
        $shipping = $useSnapshot ? (string) ($snapshot['shipping_amount'] ?? $quotation->shipping_amount) : (string) $quotation->shipping_amount;
        $rental = $useSnapshot ? (string) ($snapshot['rental_amount'] ?? $quotation->rental_amount) : (string) $quotation->rental_amount;
        $total = $useSnapshot ? (string) ($snapshot['total'] ?? $quotation->total) : (string) $quotation->total;
        $notes = $useSnapshot ? (string) ($snapshot['notes'] ?? $quotation->notes) : (string) ($quotation->notes ?? '');
        $terms = $useSnapshot ? (string) ($snapshot['terms'] ?? $quotation->terms) : (string) ($quotation->terms ?? '');
        $duration = $useSnapshot ? (string) ($snapshot['execution_duration'] ?? $quotation->execution_duration) : (string) ($quotation->execution_duration ?? '');
        $revisionCount = $useSnapshot ? ($snapshot['revision_count'] ?? $quotation->revision_count) : $quotation->revision_count;
        $policy = $useSnapshot
            ? (string) ($snapshot['payment_policy'] ?? $this->policyOf($quotation)->value)
            : $this->policyOf($quotation)->value;
        $deposit = $useSnapshot
            ? (string) ($snapshot['deposit_required'] ?? $quotation->deposit_required)
            : (string) ($quotation->deposit_required ?? '0.00');

        $items = $useSnapshot && isset($snapshot['items']) && is_array($snapshot['items'])
            ? $snapshot['items']
            : $quotation->items->map(fn (CommercialQuotationItem $item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ])->all();

        $rows = collect($items)->map(function (array $item): string {
            return '<tr>'
                .'<td style="padding:8px;border-bottom:1px solid #ddd;text-align:right">'.e((string) ($item['description'] ?? '')).'</td>'
                .'<td style="padding:8px;border-bottom:1px solid #ddd;text-align:center" dir="ltr">'.e((string) ($item['quantity'] ?? '')).'</td>'
                .'<td style="padding:8px;border-bottom:1px solid #ddd;text-align:left" dir="ltr">'.e((string) ($item['unit_price'] ?? '')).'</td>'
                .'<td style="padding:8px;border-bottom:1px solid #ddd;text-align:left" dir="ltr">'.e((string) ($item['subtotal'] ?? '')).'</td>'
                .'</tr>';
        })->implode('');

        $rfq = e((string) ($quotation->quoteRequest?->reference ?? ''));
        $customerName = e((string) ($quotation->customer?->name ?? ''));
        $customerEmail = e((string) ($quotation->customer?->email ?? ''));
        $issued = e((string) ($quotation->sent_at?->toDateString() ?? $quotation->created_at?->toDateString() ?? ''));
        $validUntil = e((string) ($quotation->valid_until?->toDateString() ?? ''));

        return '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>'.e($reference).'</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#111318;background:#F7F5EF;margin:0;padding:24px;direction:rtl;text-align:right}
.card{background:#fff;border:1px solid #e5e7eb;padding:24px}
.brand{color:#315CFF;font-size:20px;font-weight:bold;margin:0 0 4px}
h1{margin:0 0 16px;font-size:18px;color:#111318}
table{width:100%;border-collapse:collapse;margin:16px 0}
th{background:#F7F5EF;padding:8px;border-bottom:2px solid #315CFF;text-align:right}
.meta{margin:0 0 8px;color:#333}
.totals td{padding:6px 8px}
.total{font-size:14px;font-weight:bold;color:#315CFF}
.section{margin-top:18px}
.ltr{direction:ltr;unicode-bidi:embed;text-align:left;display:inline-block}
</style></head><body><div class="card">
<p class="brand">حبر وأبعاد</p>
<h1>عرض سعر</h1>
<p class="meta">رقم العرض: <strong class="ltr" dir="ltr">'.e($reference).'</strong> (الإصدار <span class="ltr" dir="ltr">'.$revision.'</span>)</p>
<p class="meta">رقم طلب التسعير: <strong class="ltr" dir="ltr">'.$rfq.'</strong></p>
<p class="meta">العميل: <strong>'.$customerName.'</strong>'.($customerEmail !== '' ? ' — <span class="ltr" dir="ltr">'.$customerEmail.'</span>' : '').'</p>
<p class="meta">تاريخ الإصدار: <span class="ltr" dir="ltr">'.$issued.'</span> | صالح حتى: <span class="ltr" dir="ltr">'.$validUntil.'</span></p>
<table><thead><tr><th>الوصف</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead><tbody>'.$rows.'</tbody></table>
<table class="totals" style="width:50%;margin-right:auto">
<tr><td>الإجمالي الفرعي</td><td class="ltr" dir="ltr">'.e($subtotal).' '.e($currency).'</td></tr>
<tr><td>الخصم</td><td class="ltr" dir="ltr">'.e($discount).' '.e($currency).'</td></tr>
<tr><td>الضريبة</td><td class="ltr" dir="ltr">'.e($tax).' '.e($currency).'</td></tr>
<tr><td>الشحن</td><td class="ltr" dir="ltr">'.e($shipping).' '.e($currency).'</td></tr>
<tr><td>التأجير</td><td class="ltr" dir="ltr">'.e($rental).' '.e($currency).'</td></tr>
<tr><td class="total">الإجمالي النهائي</td><td class="total ltr" dir="ltr">'.e($total).' '.e($currency).'</td></tr>
</table>
<div class="section">
<p class="meta">مدة التنفيذ: '.e($duration !== '' ? $duration : '—').'</p>
<p class="meta">عدد التعديلات: <span class="ltr" dir="ltr">'.e($revisionCount !== null ? (string) $revisionCount : '—').'</span></p>
<p class="meta">سياسة الدفع: '.e($policy).($policy === 'DEPOSIT' ? ' (مقدم <span class="ltr" dir="ltr">'.e($deposit).'</span>)' : '').'</p>
</div>
'.($notes !== '' ? '<div class="section"><strong>ملاحظات</strong><p>'.nl2br(e($notes)).'</p></div>' : '').'
'.($terms !== '' ? '<div class="section"><strong>الشروط</strong><p>'.nl2br(e($terms)).'</p></div>' : '').'
</div></body></html>';
    }
}
