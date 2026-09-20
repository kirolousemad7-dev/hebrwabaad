<?php

namespace App\Services\Crm;

use App\Enums\CrmQuotationStatus;
use App\Enums\UserRole;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Models\CrmQuotationItem;
use App\Models\User;
use App\Services\Pdf\PdfFactory;
use App\Services\PlatformNotifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrmQuotationService
{
    public function __construct(
        private readonly CrmLeadService $leads,
        private readonly CrmSettingsService $settings,
        private readonly PlatformNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CrmQuotation>
     */
    public function paginateFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = CrmQuotation::query()->with([
            'lead:id,reference,full_name,assigned_to',
            'creator:id,name,email',
            'items',
        ]);

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where(function ($inner) use ($actor): void {
                $inner->where('created_by', $actor->id)
                    ->orWhereHas('lead', fn ($leads) => $leads->where('assigned_to', $actor->id));
            });
        }

        if (is_string($filters['status'] ?? null) && in_array($filters['status'], CrmQuotationStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 50)));
    }

    public function load(CrmQuotation $quotation): CrmQuotation
    {
        return $quotation->load(['lead', 'creator:id,name,email', 'items', 'customer:id,name,email']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, CrmLead $lead, array $data): CrmQuotation
    {
        $this->leads->assertVisible($actor, $lead);

        return DB::transaction(function () use ($actor, $lead, $data): CrmQuotation {
            $items = $data['items'] ?? [];
            if (! is_array($items) || $items === []) {
                throw ValidationException::withMessages([
                    'items' => ['At least one quotation item is required.'],
                ]);
            }

            $discountPercent = (float) ($data['discount_percent'] ?? 0);
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);

            $totals = $this->calculateTotals($items, $discountAmount, $taxAmount, $discountPercent);

            $maxDiscount = $this->settings->discountMaxPercent();
            $effectivePercent = $totals['discount_percent'];
            $needsApproval = $effectivePercent > $maxDiscount;

            $status = $needsApproval
                ? CrmQuotationStatus::PendingApproval->value
                : CrmQuotationStatus::Draft->value;

            $quotation = CrmQuotation::query()->create([
                'number' => $this->generateNumber(),
                'lead_id' => $lead->id,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? $lead->customer_id,
                'created_by' => $actor->id,
                'status' => $status,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'discount_percent' => $totals['discount_percent'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'currency' => $data['currency'] ?? 'EGP',
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'delivery_time' => $data['delivery_time'] ?? null,
            ]);

            foreach ($items as $index => $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $discount = (float) ($item['discount_amount'] ?? 0);
                $lineTotal = max(0, ($quantity * $unitPrice) - $discount);

                CrmQuotationItem::query()->create([
                    'quotation_id' => $quotation->id,
                    'service_id' => $item['service_id'] ?? null,
                    'package_id' => $item['package_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'line_total' => $lineTotal,
                    'sort_order' => $item['sort_order'] ?? $index,
                ]);
            }

            if ($needsApproval) {
                $this->notifier->crmQuotationApprovalNeeded($this->load($quotation));
            }

            return $this->load($quotation);
        });
    }

    public function transition(User $actor, CrmQuotation $quotation, CrmQuotationStatus $next): CrmQuotation
    {
        $this->assertVisible($actor, $quotation);

        $current = $quotation->status instanceof CrmQuotationStatus
            ? $quotation->status
            : CrmQuotationStatus::from((string) $quotation->status);

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ['This quotation cannot move to the requested status.'],
            ]);
        }

        $attributes = ['status' => $next->value];

        if ($next === CrmQuotationStatus::Sent && $quotation->sent_at === null) {
            $attributes['sent_at'] = now();
            if ($quotation->public_token === null) {
                $attributes['public_token'] = $this->generatePublicToken();
            }
        }

        if ($next === CrmQuotationStatus::Approved) {
            $attributes['discount_approved_by'] = $actor->id;
            $attributes['approved_at'] = now();
        }

        if ($next === CrmQuotationStatus::Rejected) {
            $attributes['rejected_at'] = now();
        }

        if ($next === CrmQuotationStatus::Accepted) {
            $attributes['accepted_at'] = now();
        }

        $quotation->update($attributes);

        return $this->load($quotation->fresh() ?? $quotation);
    }

    public function approve(User $actor, CrmQuotation $quotation): CrmQuotation
    {
        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            throw ValidationException::withMessages([
                'quotation' => ['Only CRM managers can approve quotations.'],
            ]);
        }

        return $this->transition($actor, $quotation, CrmQuotationStatus::Approved);
    }

    public function reject(User $actor, CrmQuotation $quotation, ?string $notes = null): CrmQuotation
    {
        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            throw ValidationException::withMessages([
                'quotation' => ['Only CRM managers can reject quotations.'],
            ]);
        }

        $this->assertVisible($actor, $quotation);
        $quotation->update([
            'status' => CrmQuotationStatus::Rejected->value,
            'rejected_at' => now(),
            'rejection_notes' => $notes,
        ]);

        return $this->load($quotation->fresh() ?? $quotation);
    }

    public function send(User $actor, CrmQuotation $quotation): CrmQuotation
    {
        $this->assertVisible($actor, $quotation);

        $current = $quotation->status instanceof CrmQuotationStatus
            ? $quotation->status
            : CrmQuotationStatus::from((string) $quotation->status);

        if ($current === CrmQuotationStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'quotation' => ['Quotation requires approval before sending.'],
            ]);
        }

        if (! in_array($current, [CrmQuotationStatus::Draft, CrmQuotationStatus::Approved, CrmQuotationStatus::Sent], true)) {
            throw ValidationException::withMessages([
                'quotation' => ['Quotation cannot be sent from its current status.'],
            ]);
        }

        $quotation->update([
            'status' => CrmQuotationStatus::Sent->value,
            'sent_at' => $quotation->sent_at ?? now(),
            'public_token' => $quotation->public_token ?? $this->generatePublicToken(),
        ]);

        return $this->load($quotation->fresh() ?? $quotation);
    }

    public function findByPublicToken(string $token): CrmQuotation
    {
        $quotation = CrmQuotation::query()->where('public_token', $token)->first();
        if ($quotation === null) {
            throw ValidationException::withMessages([
                'token' => ['Quotation not found.'],
            ]);
        }

        return $this->load($quotation);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(CrmQuotation $quotation): array
    {
        return [
            'number' => $quotation->number,
            'status' => $quotation->status instanceof \BackedEnum ? $quotation->status->value : $quotation->status,
            'subtotal' => $quotation->subtotal,
            'discount_amount' => $quotation->discount_amount,
            'discount_percent' => $quotation->discount_percent,
            'tax_amount' => $quotation->tax_amount,
            'total' => $quotation->total,
            'currency' => $quotation->currency,
            'valid_until' => $quotation->valid_until?->toDateString(),
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'delivery_time' => $quotation->delivery_time,
            'items' => $quotation->items->map(fn (CrmQuotationItem $item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'line_total' => $item->line_total,
            ])->values()->all(),
        ];
    }

    public function acceptByToken(string $token): CrmQuotation
    {
        $quotation = $this->findByPublicToken($token);
        $current = $quotation->status instanceof CrmQuotationStatus
            ? $quotation->status
            : CrmQuotationStatus::from((string) $quotation->status);

        if (! in_array($current, [CrmQuotationStatus::Sent, CrmQuotationStatus::Viewed], true)) {
            throw ValidationException::withMessages([
                'quotation' => ['This quotation cannot be accepted.'],
            ]);
        }

        $quotation->update([
            'status' => CrmQuotationStatus::Accepted->value,
            'accepted_at' => now(),
        ]);

        return $this->load($quotation->fresh() ?? $quotation);
    }

    public function rejectByToken(string $token, ?string $notes = null): CrmQuotation
    {
        $quotation = $this->findByPublicToken($token);
        $current = $quotation->status instanceof CrmQuotationStatus
            ? $quotation->status
            : CrmQuotationStatus::from((string) $quotation->status);

        if (! in_array($current, [CrmQuotationStatus::Sent, CrmQuotationStatus::Viewed], true)) {
            throw ValidationException::withMessages([
                'quotation' => ['This quotation cannot be rejected.'],
            ]);
        }

        $quotation->update([
            'status' => CrmQuotationStatus::Rejected->value,
            'rejected_at' => now(),
            'rejection_notes' => $notes,
        ]);

        return $this->load($quotation->fresh() ?? $quotation);
    }

    public function pdfResponse(User $actor, CrmQuotation $quotation): Response
    {
        $this->assertVisible($actor, $quotation);
        $quotation = $this->load($quotation);
        $html = $this->renderHtml($quotation);

        if (class_exists(Pdf::class)) {
            try {
                $pdf = app(PdfFactory::class)->loadHtml($html);

                return $pdf->download($quotation->number.'.pdf');
            } catch (\Throwable) {
                // fall through to HTML
            }
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$quotation->number.'.html"',
        ]);
    }

    public function assertVisible(User $actor, CrmQuotation $quotation): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        if ((int) $quotation->created_by === (int) $actor->id) {
            return;
        }

        $quotation->loadMissing('lead');
        if ($quotation->lead && (int) $quotation->lead->assigned_to === (int) $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'quotation' => ['You do not have access to this quotation.'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{subtotal: float, discount_amount: float, discount_percent: float, tax_amount: float, total: float}
     */
    public function calculateTotals(array $items, float $discountAmount = 0, float $taxAmount = 0, float $discountPercent = 0): array
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $lineDiscount = (float) ($item['discount_amount'] ?? 0);
            $subtotal += max(0, ($quantity * $unitPrice) - $lineDiscount);
        }

        if ($discountPercent > 0 && $discountAmount <= 0) {
            $discountAmount = round($subtotal * ($discountPercent / 100), 2);
        }

        $effectivePercent = $subtotal > 0 ? round(($discountAmount / $subtotal) * 100, 2) : $discountPercent;
        $total = max(0, $subtotal - $discountAmount + $taxAmount);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'discount_percent' => $effectivePercent,
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2),
        ];
    }

    public function generateNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'QT-'.$year.'-';

        $latest = CrmQuotation::query()
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $next = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    public function generatePublicToken(): string
    {
        do {
            $token = Str::random(48);
        } while (CrmQuotation::query()->where('public_token', $token)->exists());

        return $token;
    }

    private function renderHtml(CrmQuotation $quotation): string
    {
        $rows = $quotation->items->map(function (CrmQuotationItem $item): string {
            return '<tr><td>'.e($item->description).'</td><td>'.e((string) $item->quantity).'</td><td>'.e((string) $item->unit_price).'</td><td>'.e((string) $item->line_total).'</td></tr>';
        })->implode('');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.e($quotation->number).'</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px;text-align:left}</style>
</head><body>
<h1>Quotation '.e($quotation->number).'</h1>
<p>Lead: '.e($quotation->lead?->full_name ?? '').'</p>
<p>Delivery: '.e((string) $quotation->delivery_time).'</p>
<table><thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead><tbody>'.$rows.'</tbody></table>
<p>Subtotal: '.e((string) $quotation->subtotal).'</p>
<p>Discount: '.e((string) $quotation->discount_amount).' ('.e((string) $quotation->discount_percent).'%)</p>
<p>Tax: '.e((string) $quotation->tax_amount).'</p>
<p><strong>Total: '.e((string) $quotation->total).' '.e((string) $quotation->currency).'</strong></p>
<p>'.nl2br(e((string) $quotation->notes)).'</p>
<p>'.nl2br(e((string) $quotation->terms)).'</p>
</body></html>';
    }
}
