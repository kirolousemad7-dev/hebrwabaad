<?php

namespace App\Services\Quotes;

use App\Enums\CommercialQuotationStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\QuoteRequestSource;
use App\Enums\QuoteRequestStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\CommercialQuotation;
use App\Models\ManagedFile;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestEvent;
use App\Models\User;
use App\Notifications\QuoteNotification;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class QuoteRequestService
{
    public function __construct(
        private readonly WorkflowAutomationEngine $workflows,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, QuoteRequest>
     */
    public function paginateForCustomer(User $customer, array $filters = []): LengthAwarePaginator
    {
        $query = QuoteRequest::query()
            ->with(['assignee:id,name,email', 'commercialQuotations' => fn ($q) => $q->orderByDesc('revision')->limit(1)])
            ->where('customer_id', $customer->id);

        if (is_string($filters['status'] ?? null) && in_array($filters['status'], QuoteRequestStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('id')->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 50)));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, QuoteRequest>
     */
    public function paginateForStaff(User $actor, array $filters = []): LengthAwarePaginator
    {
        $this->assertCanView($actor);

        $query = QuoteRequest::query()->with([
            'customer:id,name,email,phone',
            'assignee:id,name,email',
            'order:id,reference',
        ]);

        if (is_string($filters['status'] ?? null) && in_array($filters['status'], QuoteRequestStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (is_string($filters['source'] ?? null) && in_array($filters['source'], QuoteRequestSource::values(), true)) {
            $query->where('source_type', $filters['source']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (is_string($filters['from'] ?? null)) {
            $query->whereDate('requested_at', '>=', $filters['from']);
        }

        if (is_string($filters['to'] ?? null)) {
            $query->whereDate('requested_at', '<=', $filters['to']);
        }

        if (is_string($filters['required_from'] ?? null)) {
            $query->whereDate('required_date', '>=', $filters['required_from']);
        }

        if (is_string($filters['required_to'] ?? null)) {
            $query->whereDate('required_date', '<=', $filters['required_to']);
        }

        if (is_string($filters['q'] ?? null) && trim($filters['q']) !== '') {
            $term = '%'.trim($filters['q']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('reference', 'like', $term)
                    ->orWhere('title', 'like', $term);
            });
        }

        return $query->latest('id')->paginate(max(1, min((int) ($filters['per_page'] ?? 20), 50)));
    }

    /**
     * @return array<string, int>
     */
    public function summaryCounts(User $actor): array
    {
        $this->assertCanView($actor);

        $rows = QuoteRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'NEW' => (int) ($rows[QuoteRequestStatus::New->value] ?? 0),
            'UNDER_REVIEW' => (int) ($rows[QuoteRequestStatus::UnderReview->value] ?? 0),
            'NEEDS_INFORMATION' => (int) ($rows[QuoteRequestStatus::NeedsInformation->value] ?? 0),
            'QUOTED' => (int) ($rows[QuoteRequestStatus::Quoted->value] ?? 0),
            'REVISION_REQUESTED' => (int) ($rows[QuoteRequestStatus::RevisionRequested->value] ?? 0),
            'ACCEPTED' => (int) ($rows[QuoteRequestStatus::Accepted->value] ?? 0),
            'waiting_customer' => (int) ($rows[QuoteRequestStatus::NeedsInformation->value] ?? 0)
                + (int) ($rows[QuoteRequestStatus::Quoted->value] ?? 0),
            'awaiting_payment' => CommercialQuotation::query()
                ->where('status', CommercialQuotationStatus::Accepted)
                ->whereIn('payment_policy', [
                    PrintingPaymentPolicy::Deposit->value,
                    PrintingPaymentPolicy::Full->value,
                ])
                ->whereDoesntHave('payments', function ($q): void {
                    $q->where('status', PaymentStatus::Paid->value);
                })
                ->count(),
        ];
    }

    public function loadForCustomer(User $customer, QuoteRequest $request): QuoteRequest
    {
        if ((int) $request->customer_id !== (int) $customer->id) {
            abort(404);
        }

        return $this->load($request, includeInternal: false);
    }

    public function loadForStaff(User $actor, QuoteRequest $request): QuoteRequest
    {
        $this->assertCanView($actor);

        return $this->load($request, includeInternal: true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $customer, array $data): QuoteRequest
    {
        if ($customer->role !== UserRole::Customer) {
            throw ValidationException::withMessages([
                'customer' => ['Only customers can submit quote requests.'],
            ]);
        }

        $source = QuoteRequestSource::from((string) $data['source_type']);
        $sourceId = isset($data['source_id']) ? (int) $data['source_id'] : null;

        $this->assertNoDuplicateOpen($customer, $source, $sourceId, $data);

        $request = DB::transaction(function () use ($customer, $data, $source, $sourceId): QuoteRequest {
            $request = QuoteRequest::query()->create([
                'reference' => $this->generateReference(),
                'customer_id' => $customer->id,
                'order_id' => $data['order_id'] ?? null,
                'source_type' => $source,
                'source_id' => $sourceId,
                'title' => (string) $data['title'],
                'status' => QuoteRequestStatus::New,
                'requested_at' => now(),
                'required_date' => $data['required_date'] ?? null,
                'budget_min' => $data['budget_min'] ?? null,
                'budget_max' => $data['budget_max'] ?? null,
                'city' => $data['city'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'payload' => $data['payload'] ?? [],
                'quotation_type' => $source->usesPrintingQuotation() ? 'PRINTING' : 'COMMERCIAL',
            ]);

            $fileIds = array_values(array_filter(array_map('intval', $data['file_ids'] ?? [])));
            if ($fileIds !== []) {
                ManagedFile::query()
                    ->where('uploaded_by', $customer->id)
                    ->whereIn('id', $fileIds)
                    ->whereNull('quote_request_id')
                    ->update(['quote_request_id' => $request->id]);
            }

            $this->recordEvent($request, 'created', $customer, 'customer');

            return $this->load($request, includeInternal: false);
        });

        $this->notifyOwnersNewRequest($request);
        $this->notifyCustomer($request, 'تم استلام طلبك', "تم استلام طلب التسعير {$request->reference}. سيقوم فريق حبر وأبعاد بمراجعته.");
        $this->dispatchWorkflow(WorkflowTrigger::QuoteRequestCreated, $request, $customer);

        return $request;
    }

    public function startReview(User $actor, QuoteRequest $request): QuoteRequest
    {
        $this->assertCanManage($actor);
        $this->assertStatus($request, [QuoteRequestStatus::New, QuoteRequestStatus::ReadyToPrice]);

        $request->update([
            'status' => QuoteRequestStatus::UnderReview,
            'assigned_to' => $request->assigned_to ?? $actor->id,
        ]);
        $this->recordEvent($request, 'review_started', $actor, 'staff');

        return $this->load($request);
    }

    public function assign(User $actor, QuoteRequest $request, int $assigneeId): QuoteRequest
    {
        $this->assertCanManage($actor);

        $assignee = User::query()->findOrFail($assigneeId);
        if (! $assignee->role?->isStaff()) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Assignee must be a staff user.'],
            ]);
        }

        $request->update(['assigned_to' => $assignee->id]);
        $this->recordEvent($request, 'assigned', $actor, 'staff', ['assigned_to' => $assignee->id]);

        return $this->load($request);
    }

    public function requestInformation(User $actor, QuoteRequest $request, string $message): QuoteRequest
    {
        $this->assertCanManage($actor);
        $this->assertStatus($request, [
            QuoteRequestStatus::New,
            QuoteRequestStatus::UnderReview,
            QuoteRequestStatus::ReadyToPrice,
        ]);

        $request->update([
            'status' => QuoteRequestStatus::NeedsInformation,
            'information_request' => $message,
        ]);
        $this->recordEvent($request, 'information_requested', $actor, 'staff', ['message' => $message]);

        $this->notifyCustomer(
            $request,
            'نحتاج معلومات إضافية',
            "نحتاج معلومات إضافية قبل التسعير لطلبك {$request->reference}: {$message}",
        );
        $this->dispatchWorkflow(WorkflowTrigger::QuoteRequestInformationRequested, $request, $actor);

        return $this->load($request);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function customerRespond(User $customer, QuoteRequest $request, array $data): QuoteRequest
    {
        $loaded = $this->loadForCustomer($customer, $request);
        $this->assertStatus($loaded, [QuoteRequestStatus::NeedsInformation]);

        DB::transaction(function () use ($customer, $loaded, $data): void {
            $payload = is_array($loaded->payload) ? $loaded->payload : [];
            $responses = is_array($payload['customer_responses'] ?? null) ? $payload['customer_responses'] : [];
            $responses[] = [
                'message' => $data['message'] ?? null,
                'at' => now()->toIso8601String(),
            ];
            $payload['customer_responses'] = $responses;

            $notes = trim((string) ($loaded->customer_notes ?? ''));
            $extra = trim((string) ($data['message'] ?? ''));
            if ($extra !== '') {
                $notes = $notes === '' ? $extra : $notes."\n\n---\n".$extra;
            }

            $loaded->update([
                'status' => QuoteRequestStatus::UnderReview,
                'customer_notes' => $notes !== '' ? $notes : $loaded->customer_notes,
                'payload' => $payload,
                'information_request' => null,
            ]);

            $fileIds = array_values(array_filter(array_map('intval', $data['file_ids'] ?? [])));
            if ($fileIds !== []) {
                ManagedFile::query()
                    ->where('uploaded_by', $customer->id)
                    ->whereIn('id', $fileIds)
                    ->update(['quote_request_id' => $loaded->id]);
            }

            $this->recordEvent($loaded, 'customer_responded', $customer, 'customer', [
                'message' => $data['message'] ?? null,
            ]);
        });

        $fresh = $this->load($loaded->fresh() ?? $loaded, includeInternal: false);
        $this->notifyStaff(
            $fresh,
            'عميل أضاف معلومات',
            "أضاف العميل معلومات إضافية على طلب التسعير {$fresh->reference}.",
        );
        $this->dispatchWorkflow(WorkflowTrigger::QuoteRequestCustomerResponded, $fresh, $customer);

        return $fresh;
    }

    public function cancel(User $actor, QuoteRequest $request, ?string $reason = null): QuoteRequest
    {
        $this->assertCanManage($actor);

        if (! ($request->status instanceof QuoteRequestStatus ? $request->status : QuoteRequestStatus::from((string) $request->status))->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['This quote request can no longer be cancelled.'],
            ]);
        }

        $request->update([
            'status' => QuoteRequestStatus::Cancelled,
            'internal_notes' => $reason
                ? trim(((string) ($request->internal_notes ?? ''))."\nCancelled: {$reason}")
                : $request->internal_notes,
        ]);
        $this->recordEvent($request, 'cancelled', $actor, 'staff', ['reason' => $reason]);

        return $this->load($request);
    }

    public function updateInternalNotes(User $actor, QuoteRequest $request, ?string $notes): QuoteRequest
    {
        $this->assertCanManage($actor);
        $request->update(['internal_notes' => $notes]);
        $this->recordEvent($request, 'internal_notes_updated', $actor, 'staff');

        return $this->load($request);
    }

    public function markQuoted(QuoteRequest $request, string $quotationType, int $quotationId): void
    {
        $request->update([
            'status' => QuoteRequestStatus::Quoted,
            'quotation_type' => $quotationType,
            'quotation_id' => $quotationId,
        ]);
        $this->recordEvent($request, 'quotation_sent', null, 'system', [
            'quotation_type' => $quotationType,
            'quotation_id' => $quotationId,
        ]);
    }

    public function markRevisionRequested(QuoteRequest $request, ?string $reason = null): void
    {
        $request->update(['status' => QuoteRequestStatus::RevisionRequested]);
        $this->recordEvent($request, 'revision_requested', $request->customer, 'customer', [
            'reason' => $reason,
        ]);
    }

    public function markAccepted(QuoteRequest $request): void
    {
        $request->update(['status' => QuoteRequestStatus::Accepted]);
        $this->recordEvent($request, 'accepted', $request->customer, 'customer');
    }

    public function markRejected(QuoteRequest $request, ?string $reason = null): void
    {
        $request->update(['status' => QuoteRequestStatus::Rejected]);
        $this->recordEvent($request, 'rejected', $request->customer, 'customer', [
            'reason' => $reason,
        ]);
    }

    /**
     * @return list<array{type: string, label: string, count: int, href: string}>
     */
    public function attentionItems(): array
    {
        $new = QuoteRequest::query()->where('status', QuoteRequestStatus::New)->count();
        $review = QuoteRequest::query()->whereIn('status', [
            QuoteRequestStatus::UnderReview,
            QuoteRequestStatus::ReadyToPrice,
        ])->count();
        $needsInfo = QuoteRequest::query()->where('status', QuoteRequestStatus::NeedsInformation)->count();
        $revisions = QuoteRequest::query()->where('status', QuoteRequestStatus::RevisionRequested)->count();
        $expiring = CommercialQuotation::query()
            ->whereIn('status', [CommercialQuotationStatus::Sent, CommercialQuotationStatus::Viewed])
            ->whereDate('valid_until', '<=', now()->addDays(3)->toDateString())
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->count();
        $awaitingPayment = CommercialQuotation::query()
            ->where('status', CommercialQuotationStatus::Accepted)
            ->whereIn('payment_policy', [
                PrintingPaymentPolicy::Deposit->value,
                PrintingPaymentPolicy::Full->value,
            ])
            ->whereDoesntHave('payments', function ($q): void {
                $q->where('status', PaymentStatus::Paid->value);
            })
            ->count();

        $items = [];
        if ($new > 0) {
            $items[] = [
                'type' => 'quote_request_new',
                'label' => 'طلبات تسعير جديدة',
                'count' => $new,
                'href' => '/owner/quote-requests?status=NEW',
            ];
        }
        if ($needsInfo > 0) {
            $items[] = [
                'type' => 'quote_request_needs_information',
                'label' => 'طلبات بانتظار معلومات',
                'count' => $needsInfo,
                'href' => '/owner/quote-requests?status=NEEDS_INFORMATION',
            ];
        }
        if ($review > 0) {
            $items[] = [
                'type' => 'quote_request_review',
                'label' => 'طلبات تنتظر المراجعة',
                'count' => $review,
                'href' => '/owner/quote-requests?status=UNDER_REVIEW',
            ];
        }
        if ($revisions > 0) {
            $items[] = [
                'type' => 'quote_revision_requested',
                'label' => 'عميل طلب تعديل',
                'count' => $revisions,
                'href' => '/owner/quote-requests?status=REVISION_REQUESTED',
            ];
        }
        if ($expiring > 0) {
            $items[] = [
                'type' => 'quotation_expiring',
                'label' => 'عروض قرب انتهاء صلاحيتها',
                'count' => $expiring,
                'href' => '/owner/quote-requests',
            ];
        }
        if ($awaitingPayment > 0) {
            $items[] = [
                'type' => 'quotation_awaiting_payment',
                'label' => 'عروض مقبولة تنتظر دفع',
                'count' => $awaitingPayment,
                'href' => '/owner/quote-requests?status=ACCEPTED',
            ];
        }

        return $items;
    }

    private function load(QuoteRequest $request, bool $includeInternal = true): QuoteRequest
    {
        $request->load([
            'customer:id,name,email,phone',
            'assignee:id,name,email',
            'order:id,reference,status',
            'files',
            'events' => fn ($q) => $q->with('actor:id,name')->limit(80),
            'commercialQuotations' => fn ($q) => $q->with('items')->orderByDesc('revision'),
        ]);

        if (! $includeInternal) {
            $request->makeHidden(['internal_notes']);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertNoDuplicateOpen(User $customer, QuoteRequestSource $source, ?int $sourceId, array $data): void
    {
        if ($sourceId === null && empty($data['idempotency_key'])) {
            return;
        }

        $query = QuoteRequest::query()
            ->where('customer_id', $customer->id)
            ->where('source_type', $source)
            ->whereIn('status', [
                QuoteRequestStatus::New,
                QuoteRequestStatus::UnderReview,
                QuoteRequestStatus::NeedsInformation,
                QuoteRequestStatus::ReadyToPrice,
                QuoteRequestStatus::Quoted,
                QuoteRequestStatus::RevisionRequested,
            ]);

        if ($sourceId !== null) {
            $query->where('source_id', $sourceId);
        }

        if (! empty($data['idempotency_key'])) {
            $query->where('payload->idempotency_key', $data['idempotency_key']);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'source_id' => ['لديك طلب تسعير مفتوح بالفعل لهذا العنصر.'],
            ]);
        }
    }

    private function generateReference(): string
    {
        $year = now()->format('Y');
        $prefix = "QR-{$year}-";

        return DB::transaction(function () use ($prefix): string {
            $latest = QuoteRequest::query()
                ->where('reference', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('reference');

            $next = 1;
            if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m) === 1) {
                $next = ((int) $m[1]) + 1;
            }

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * @param  list<QuoteRequestStatus>  $allowed
     */
    private function assertStatus(QuoteRequest $request, array $allowed): void
    {
        $status = $request->status instanceof QuoteRequestStatus
            ? $request->status
            : QuoteRequestStatus::from((string) $request->status);

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ['This action is not allowed for the current request status.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function recordEvent(
        QuoteRequest $request,
        string $type,
        ?User $actor,
        string $actorType,
        ?array $meta = null,
    ): void {
        QuoteRequestEvent::query()->create([
            'quote_request_id' => $request->id,
            'actor_id' => $actor?->id,
            'actor_type' => $actorType,
            'event_type' => $type,
            'meta' => $meta,
        ]);
    }

    private function notifyCustomer(QuoteRequest $request, string $title, string $body): void
    {
        $request->loadMissing('customer');
        if ($request->customer === null) {
            return;
        }

        $href = '/dashboard/quote-requests/'.$request->id;
        $request->customer->notify(new QuoteNotification([
            'title' => $title,
            'message' => $body,
            'body' => $body,
            'href' => $href,
            'action_url' => $href,
            'type' => 'quote_request',
            'meta' => ['quote_request_id' => $request->id, 'reference' => $request->reference],
        ]));
    }

    private function notifyOwnersNewRequest(QuoteRequest $request): void
    {
        $this->notifyStaff(
            $request,
            'طلب تسعير جديد',
            "تم استلام طلب تسعير جديد {$request->reference}: {$request->title}",
        );
    }

    private function notifyStaff(QuoteRequest $request, string $title, string $body): void
    {
        $recipients = User::query()
            ->whereIn('role', [UserRole::Owner->value, UserRole::AdminManager->value, UserRole::AccountManager->value, UserRole::SalesManager->value])
            ->where('is_active', true)
            ->get();

        $href = '/owner/quote-requests/'.$request->id;
        Notification::send($recipients, new QuoteNotification([
            'title' => $title,
            'message' => $body,
            'body' => $body,
            'href' => $href,
            'action_url' => $href,
            'type' => 'quote_request',
            'meta' => ['quote_request_id' => $request->id, 'reference' => $request->reference],
        ]));
    }

    private function dispatchWorkflow(WorkflowTrigger $trigger, QuoteRequest $request, ?User $actor): void
    {
        try {
            $this->workflows->dispatch($trigger->value, [
                'quote_request_id' => $request->id,
                'reference' => $request->reference,
                'status' => $request->status instanceof QuoteRequestStatus
                    ? $request->status->value
                    : (string) $request->status,
                'source_type' => $request->source_type instanceof QuoteRequestSource
                    ? $request->source_type->value
                    : (string) $request->source_type,
                'actor_id' => $actor?->id,
            ], (string) $request->id);
        } catch (\Throwable) {
            // Workflow hooks must never break quote request flows.
        }
    }

    private function assertCanView(User $actor): void
    {
        if (! $actor->role?->canViewQuoteRequests()) {
            abort(403);
        }
    }

    private function assertCanManage(User $actor): void
    {
        if (! $actor->role?->canManageQuoteRequests()) {
            abort(403);
        }
    }
}
