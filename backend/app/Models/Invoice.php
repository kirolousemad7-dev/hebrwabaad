<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\HasMedia;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Customer-facing invoice. Supplier identity, cost, and internal notes never belong in
 * any payload derived from this model for a customer — scrub through InvoiceService.
 */
#[Fillable([
    'number',
    'customer_id',
    'crm_company_id',
    'commercial_quotation_id',
    'order_id',
    'project_id',
    'created_by',
    'status',
    'currency',
    'issue_date',
    'due_date',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'total',
    'amount_paid',
    'amount_due',
    'notes',
    'terms',
    'internal_notes',
    'public_token_hash',
    'public_token_hint',
    'issued_at',
    'sent_at',
    'cancelled_at',
    'voided_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasMedia, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public function statusEnum(): InvoiceStatus
    {
        return $this->status instanceof InvoiceStatus
            ? $this->status
            : InvoiceStatus::from((string) $this->status);
    }

    public function belongsToCustomer(User $user): bool
    {
        return (int) $this->customer_id === (int) $user->id;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<CrmCompany, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    /**
     * @return BelongsTo<CommercialQuotation, $this>
     */
    public function commercialQuotation(): BelongsTo
    {
        return $this->belongsTo(CommercialQuotation::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<InvoiceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class)->latest('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
