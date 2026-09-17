<?php

namespace App\Services\Quotes;

use App\Enums\ProjectStatus;
use App\Enums\SupplierQuoteStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\CommercialQuotationItem;
use App\Models\Project;
use App\Models\QuotationSupplierQuote;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationSupplierSourcingService
{
    public function __construct(
        private readonly CommercialQuotationService $quotations,
    ) {}

    public function assertOwnerCanManage(User $actor): void
    {
        $this->quotations->assertCanManage($actor);
    }

    public function assertSupplierOwns(User $actor, QuotationSupplierQuote $quote): void
    {
        $supplier = $actor->supplierProfile;
        if ($supplier === null || (int) $supplier->id !== (int) $quote->supplier_id) {
            abort(404);
        }
    }

    /**
     * @return Collection<int, QuotationSupplierQuote>
     */
    public function listForQuotation(User $actor, CommercialQuotation $quotation): Collection
    {
        $this->assertOwnerCanManage($actor);

        return QuotationSupplierQuote::query()
            ->where('commercial_quotation_id', $quotation->id)
            ->with(['supplier:id,name,display_name,slug', 'item:id,description,quantity,unit_price,subtotal'])
            ->orderBy('commercial_quotation_item_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, QuotationSupplierQuote>
     */
    public function listForSupplier(User $actor): Collection
    {
        $supplier = $this->requireSupplierProfile($actor);

        return QuotationSupplierQuote::query()
            ->where('supplier_id', $supplier->id)
            ->with([
                'item:id,description,quantity,commercial_quotation_id',
                'quotation:id,reference,revision,status,currency,valid_until',
            ])
            ->latest('id')
            ->get();
    }

    public function showForSupplier(User $actor, QuotationSupplierQuote $quote): QuotationSupplierQuote
    {
        $this->assertSupplierOwns($actor, $quote);

        return $quote->load([
            'item:id,description,quantity,commercial_quotation_id',
            'quotation:id,reference,revision,status,currency,valid_until',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestQuote(User $actor, CommercialQuotation $quotation, CommercialQuotationItem $item, array $data): QuotationSupplierQuote
    {
        $this->assertOwnerCanManage($actor);
        $this->assertItemBelongsToQuotation($quotation, $item);

        $supplierId = (int) $data['supplier_id'];
        Supplier::query()->whereKey($supplierId)->firstOrFail();

        $existing = QuotationSupplierQuote::query()
            ->where('commercial_quotation_item_id', $item->id)
            ->where('supplier_id', $supplierId)
            ->first();

        if ($existing !== null) {
            $status = $this->statusOf($existing);
            if (in_array($status, [SupplierQuoteStatus::Selected, SupplierQuoteStatus::Requested, SupplierQuoteStatus::Received, SupplierQuoteStatus::UnderReview], true)) {
                throw ValidationException::withMessages([
                    'supplier_id' => ['A sourcing request already exists for this supplier on this item.'],
                ]);
            }

            $existing->update([
                'cost' => null,
                'currency' => $data['currency'] ?? $quotation->currency ?? 'EGP',
                'valid_until' => $data['valid_until'] ?? null,
                'delivery_days' => null,
                'notes' => $data['notes'] ?? null,
                'attachments' => [],
                'status' => SupplierQuoteStatus::Requested,
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'received_at' => null,
                'reviewed_at' => null,
                'selected_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'replaced_by_id' => null,
            ]);

            $this->recordQuotationEvent($quotation, $actor, 'supplier_quote_requested', [
                'quote_id' => $existing->id,
                'supplier_id' => $supplierId,
                'item_id' => $item->id,
            ]);

            return $existing->fresh(['supplier', 'item']) ?? $existing;
        }

        $quote = QuotationSupplierQuote::query()->create([
            'commercial_quotation_id' => $quotation->id,
            'commercial_quotation_item_id' => $item->id,
            'supplier_id' => $supplierId,
            'cost' => null,
            'currency' => $data['currency'] ?? $quotation->currency ?? 'EGP',
            'valid_until' => $data['valid_until'] ?? null,
            'delivery_days' => null,
            'notes' => $data['notes'] ?? null,
            'attachments' => [],
            'status' => SupplierQuoteStatus::Requested,
            'requested_by' => $actor->id,
            'requested_at' => now(),
        ]);

        $this->recordQuotationEvent($quotation, $actor, 'supplier_quote_requested', [
            'quote_id' => $quote->id,
            'supplier_id' => $supplierId,
            'item_id' => $item->id,
        ]);

        return $quote->load(['supplier', 'item']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $files
     */
    public function submitSupplierResponse(User $actor, QuotationSupplierQuote $quote, array $data, array $files = []): QuotationSupplierQuote
    {
        $this->assertSupplierOwns($actor, $quote);

        $status = $this->statusOf($quote);
        if (! in_array($status, [SupplierQuoteStatus::Requested, SupplierQuoteStatus::Received, SupplierQuoteStatus::UnderReview], true)) {
            throw ValidationException::withMessages([
                'quote' => ['This sourcing request cannot be updated.'],
            ]);
        }

        if ($quote->isExpiredByDate()) {
            $quote->update(['status' => SupplierQuoteStatus::Expired]);
            throw ValidationException::withMessages([
                'quote' => ['This sourcing request has expired.'],
            ]);
        }

        $attachments = is_array($quote->attachments) ? $quote->attachments : [];
        foreach ($files as $file) {
            $attachments[] = $this->storeAttachment($quote, $file);
        }

        $quote->update([
            'cost' => $data['cost'],
            'currency' => $data['currency'] ?? $quote->currency,
            'valid_until' => $data['valid_until'] ?? $quote->valid_until,
            'delivery_days' => $data['delivery_days'] ?? $quote->delivery_days,
            'notes' => $data['notes'] ?? $quote->notes,
            'attachments' => $attachments,
            'status' => SupplierQuoteStatus::Received,
            'received_at' => $quote->received_at ?? now(),
        ]);

        return $quote->fresh(['item', 'quotation']) ?? $quote;
    }

    public function markUnderReview(User $actor, QuotationSupplierQuote $quote): QuotationSupplierQuote
    {
        $this->assertOwnerCanManage($actor);
        $status = $this->statusOf($quote);

        if ($status !== SupplierQuoteStatus::Received) {
            throw ValidationException::withMessages([
                'quote' => ['Only received quotes can move to under review.'],
            ]);
        }

        $quote->update([
            'status' => SupplierQuoteStatus::UnderReview,
            'reviewed_at' => now(),
        ]);

        return $quote->fresh(['supplier', 'item']) ?? $quote;
    }

    public function select(User $actor, QuotationSupplierQuote $quote): QuotationSupplierQuote
    {
        $this->assertOwnerCanManage($actor);

        return DB::transaction(function () use ($actor, $quote): QuotationSupplierQuote {
            /** @var QuotationSupplierQuote $locked */
            $locked = QuotationSupplierQuote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $status = $this->statusOf($locked);

            if (! $status->isSelectable() && $status !== SupplierQuoteStatus::UnderReview) {
                throw ValidationException::withMessages([
                    'quote' => ['This supplier quote cannot be selected.'],
                ]);
            }

            if ($locked->cost === null) {
                throw ValidationException::withMessages([
                    'cost' => ['Supplier cost is required before selection.'],
                ]);
            }

            QuotationSupplierQuote::query()
                ->where('commercial_quotation_item_id', $locked->commercial_quotation_item_id)
                ->where('id', '!=', $locked->id)
                ->where('status', SupplierQuoteStatus::Selected->value)
                ->update([
                    'status' => SupplierQuoteStatus::Rejected->value,
                    'rejected_at' => now(),
                    'rejection_reason' => 'Replaced by another selected supplier',
                ]);

            $locked->update([
                'status' => SupplierQuoteStatus::Selected,
                'selected_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            CommercialQuotationItem::query()
                ->whereKey($locked->commercial_quotation_item_id)
                ->update(['selected_supplier_quote_id' => $locked->id]);

            $quotation = CommercialQuotation::query()->findOrFail($locked->commercial_quotation_id);
            $this->recordQuotationEvent($quotation, $actor, 'supplier_quote_selected', [
                'quote_id' => $locked->id,
                'supplier_id' => $locked->supplier_id,
                'item_id' => $locked->commercial_quotation_item_id,
                'cost' => $locked->cost,
            ]);

            return $locked->fresh(['supplier', 'item']) ?? $locked;
        });
    }

    public function reject(User $actor, QuotationSupplierQuote $quote, ?string $reason = null): QuotationSupplierQuote
    {
        $this->assertOwnerCanManage($actor);

        return DB::transaction(function () use ($actor, $quote, $reason): QuotationSupplierQuote {
            /** @var QuotationSupplierQuote $locked */
            $locked = QuotationSupplierQuote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if ($this->statusOf($locked) === SupplierQuoteStatus::Selected) {
                CommercialQuotationItem::query()
                    ->whereKey($locked->commercial_quotation_item_id)
                    ->where('selected_supplier_quote_id', $locked->id)
                    ->update(['selected_supplier_quote_id' => null]);
            }

            $locked->update([
                'status' => SupplierQuoteStatus::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'selected_at' => null,
            ]);

            $quotation = CommercialQuotation::query()->findOrFail($locked->commercial_quotation_id);
            $this->recordQuotationEvent($quotation, $actor, 'supplier_quote_rejected', [
                'quote_id' => $locked->id,
                'supplier_id' => $locked->supplier_id,
                'reason' => $reason,
            ]);

            return $locked->fresh(['supplier', 'item']) ?? $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function replace(User $actor, QuotationSupplierQuote $quote, array $data): QuotationSupplierQuote
    {
        $this->assertOwnerCanManage($actor);

        return DB::transaction(function () use ($actor, $quote, $data): QuotationSupplierQuote {
            $this->reject($actor, $quote, $data['rejection_reason'] ?? 'Replaced with another supplier');

            $quotation = CommercialQuotation::query()->findOrFail($quote->commercial_quotation_id);
            $item = CommercialQuotationItem::query()->findOrFail($quote->commercial_quotation_item_id);

            $replacement = $this->requestQuote($actor, $quotation, $item, $data);
            $quote->update(['replaced_by_id' => $replacement->id]);

            return $replacement;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function compareForItem(User $actor, CommercialQuotationItem $item): array
    {
        $this->assertOwnerCanManage($actor);
        $item->loadMissing('quotation');

        $customerPrice = (string) $item->subtotal;
        $quotes = QuotationSupplierQuote::query()
            ->where('commercial_quotation_item_id', $item->id)
            ->with('supplier:id,name,display_name')
            ->orderBy('id')
            ->get();

        return $quotes->map(function (QuotationSupplierQuote $quote) use ($customerPrice): array {
            return $this->ownerQuotePayload($quote, $customerPrice);
        })->values()->all();
    }

    /**
     * Staff-facing sourcing payload for a full quotation (margins included).
     *
     * @return array{items: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function sourcingPayload(CommercialQuotation $quotation): array
    {
        $quotation->loadMissing(['items.supplierQuotes.supplier']);

        $items = [];
        $totalCustomer = '0.00';
        $totalSupplier = '0.00';

        foreach ($quotation->items as $item) {
            $customerPrice = (string) $item->subtotal;
            $totalCustomer = bcadd($totalCustomer, $customerPrice, 2);
            $selectedCost = null;

            $options = $item->supplierQuotes->map(function (QuotationSupplierQuote $quote) use ($customerPrice, &$selectedCost): array {
                $payload = $this->ownerQuotePayload($quote, $customerPrice);
                if ($this->statusOf($quote) === SupplierQuoteStatus::Selected && $quote->cost !== null) {
                    $selectedCost = (string) $quote->cost;
                }

                return $payload;
            })->values()->all();

            if ($selectedCost !== null) {
                $totalSupplier = bcadd($totalSupplier, $selectedCost, 2);
            }

            $items[] = [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'customer_price' => $customerPrice,
                'selected_supplier_quote_id' => $item->selected_supplier_quote_id,
                'supplier_options' => $options,
                'margin' => $selectedCost !== null ? $this->marginFields($customerPrice, $selectedCost) : null,
            ];
        }

        $gross = bcsub($totalCustomer, $totalSupplier, 2);

        return [
            'items' => $items,
            'totals' => [
                'customer_total' => $totalCustomer,
                'selected_supplier_cost' => $totalSupplier,
                'gross_margin' => $gross,
                'margin_percentage' => $this->marginPercentage($totalCustomer, $gross),
            ],
        ];
    }

    /**
     * Attach selected suppliers to an execution project/tasks after customer accepts.
     */
    public function attachSelectedSuppliersOnAccept(CommercialQuotation $quotation, User $actor): ?Project
    {
        $selected = QuotationSupplierQuote::query()
            ->where('commercial_quotation_id', $quotation->id)
            ->where('status', SupplierQuoteStatus::Selected->value)
            ->with('item')
            ->get();

        if ($selected->isEmpty()) {
            return $quotation->execution_project_id
                ? Project::query()->find($quotation->execution_project_id)
                : null;
        }

        return DB::transaction(function () use ($quotation, $actor, $selected): Project {
            $project = $quotation->execution_project_id
                ? Project::query()->find($quotation->execution_project_id)
                : null;

            if ($project === null) {
                $project = Project::query()->create([
                    'title' => 'تنفيذ '.$quotation->reference,
                    'description' => 'مشروع تنفيذ داخلي لعرض السعر '.$quotation->reference,
                    'customer_id' => $quotation->customer_id,
                    'account_manager_id' => $actor->id,
                    'status' => ProjectStatus::Planning,
                    'started_at' => now()->toDateString(),
                    'deadline' => null,
                ]);
                $quotation->update(['execution_project_id' => $project->id]);
            }

            foreach ($selected as $quote) {
                $exists = Task::query()
                    ->where('quotation_supplier_quote_id', $quote->id)
                    ->exists();
                if ($exists) {
                    continue;
                }

                Task::query()->create([
                    'title' => $quote->item?->description ?? ('تنفيذ بند #'.$quote->commercial_quotation_item_id),
                    'description' => 'مهمة تنفيذ داخلية — المورد غير ظاهر للعميل.',
                    'project_id' => $project->id,
                    'assigned_to' => null,
                    'created_by' => $actor->id,
                    'priority' => TaskPriority::Medium->value,
                    'status' => TaskStatus::Todo->value,
                    'source' => 'COMMERCIAL_QUOTATION_SOURCING',
                    'supplier_id' => $quote->supplier_id,
                    'quotation_supplier_quote_id' => $quote->id,
                    'commercial_quotation_item_id' => $quote->commercial_quotation_item_id,
                    'deadline' => $quote->delivery_days
                        ? now()->addDays((int) $quote->delivery_days)->toDateString()
                        : null,
                ]);
            }

            return $project;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function ownerQuotePayload(QuotationSupplierQuote $quote, ?string $customerPrice = null): array
    {
        $quote->loadMissing(['supplier:id,name,display_name,slug', 'item:id,description,subtotal']);
        $customer = $customerPrice ?? (string) ($quote->item?->subtotal ?? '0');
        $cost = $quote->cost !== null ? (string) $quote->cost : null;
        $status = $this->statusOf($quote);

        return [
            'id' => $quote->id,
            'commercial_quotation_id' => $quote->commercial_quotation_id,
            'commercial_quotation_item_id' => $quote->commercial_quotation_item_id,
            'supplier' => $quote->supplier ? [
                'id' => $quote->supplier->id,
                'name' => $quote->supplier->display_name ?: $quote->supplier->name,
                'slug' => $quote->supplier->slug,
            ] : null,
            'item' => $quote->item ? [
                'id' => $quote->item->id,
                'description' => $quote->item->description,
            ] : null,
            'cost' => $cost,
            'currency' => $quote->currency,
            'valid_until' => $quote->valid_until?->toDateString(),
            'delivery_days' => $quote->delivery_days,
            'notes' => $quote->notes,
            'attachments' => $quote->attachments ?? [],
            'status' => $status->value,
            'status_label_ar' => $status->labelAr(),
            'customer_price' => $customer,
            'margin' => $cost !== null ? $this->marginFields($customer, $cost) : null,
            'requested_at' => $quote->requested_at?->toIso8601String(),
            'received_at' => $quote->received_at?->toIso8601String(),
            'selected_at' => $quote->selected_at?->toIso8601String(),
            'rejected_at' => $quote->rejected_at?->toIso8601String(),
            'rejection_reason' => $quote->rejection_reason,
        ];
    }

    /**
     * Supplier-facing payload — never includes customer price or margins.
     *
     * @return array<string, mixed>
     */
    public function supplierQuotePayload(QuotationSupplierQuote $quote): array
    {
        $quote->loadMissing([
            'item:id,description,quantity',
            'quotation:id,reference,revision,status,currency,valid_until',
        ]);
        $status = $this->statusOf($quote);

        return [
            'id' => $quote->id,
            'status' => $status->value,
            'status_label_ar' => $status->labelAr(),
            'quotation' => $quote->quotation ? [
                'id' => $quote->quotation->id,
                'reference' => $quote->quotation->reference,
                'revision' => $quote->quotation->revision,
                'status' => $quote->quotation->status instanceof \BackedEnum
                    ? $quote->quotation->status->value
                    : (string) $quote->quotation->status,
            ] : null,
            'item' => $quote->item ? [
                'id' => $quote->item->id,
                'description' => $quote->item->description,
                'quantity' => $quote->item->quantity,
            ] : null,
            'cost' => $quote->cost,
            'currency' => $quote->currency,
            'valid_until' => $quote->valid_until?->toDateString(),
            'delivery_days' => $quote->delivery_days,
            'notes' => $quote->notes,
            'attachments' => $quote->attachments ?? [],
            'requested_at' => $quote->requested_at?->toIso8601String(),
            'received_at' => $quote->received_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{gross_margin: string, margin_percentage: float|null}
     */
    private function marginFields(string $customerPrice, string $supplierCost): array
    {
        $gross = bcsub($customerPrice, $supplierCost, 2);

        return [
            'gross_margin' => $gross,
            'margin_percentage' => $this->marginPercentage($customerPrice, $gross),
        ];
    }

    private function marginPercentage(string $customerPrice, string $gross): ?float
    {
        if (bccomp($customerPrice, '0', 2) !== 1) {
            return null;
        }

        return round((float) bcmul(bcdiv($gross, $customerPrice, 6), '100', 4), 2);
    }

    private function statusOf(QuotationSupplierQuote $quote): SupplierQuoteStatus
    {
        return $quote->status instanceof SupplierQuoteStatus
            ? $quote->status
            : SupplierQuoteStatus::from((string) $quote->status);
    }

    private function assertItemBelongsToQuotation(CommercialQuotation $quotation, CommercialQuotationItem $item): void
    {
        if ((int) $item->commercial_quotation_id !== (int) $quotation->id) {
            throw ValidationException::withMessages([
                'item' => ['Item does not belong to this quotation.'],
            ]);
        }
    }

    private function requireSupplierProfile(User $actor): Supplier
    {
        if (! ($actor->role instanceof UserRole) || $actor->role !== UserRole::Supplier) {
            abort(403);
        }

        $supplier = $actor->supplierProfile;
        if ($supplier === null) {
            abort(404);
        }

        return $supplier;
    }

    /**
     * @return array{path: string, original_name: string, mime_type: string|null, size: int}
     */
    private function storeAttachment(QuotationSupplierQuote $quote, UploadedFile $file): array
    {
        $path = $file->store('quotation-sourcing/'.$quote->id, 'local');

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function recordQuotationEvent(CommercialQuotation $quotation, ?User $actor, string $type, array $meta = []): void
    {
        $quotation->events()->create([
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'staff' : 'system',
            'event_type' => $type,
            'meta' => $meta,
        ]);
    }
}
