<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasMedia;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Commercial payment row. Amount is immutable after creation — never reduced for refunds.
 *
 * Extension point: dispute_status / dispute_metadata may hold chargeback / dispute workflow
 * state once that surface is implemented (Phase 8G). Do not overload these columns for refunds;
 * use payment_refunds instead.
 */
#[Fillable([
    'customer_id',
    'order_id',
    'printing_quotation_id',
    'commercial_quotation_id',
    'invoice_id',
    'amount',
    'currency',
    'payment_method',
    'status',
    'provider',
    'provider_status',
    'provider_transaction_id',
    'checkout_session_id',
    'reference_number',
    'payer_name',
    'notes',
    'failure_reason',
    'paid_at',
    'verified_at',
    'verified_by',
    'last_reconciled_at',
    'reconciliation_note',
    'dispute_status',
    'dispute_metadata',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_reconciled_at' => 'datetime',
            'dispute_metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PrintingQuotation, $this>
     */
    public function printingQuotation(): BelongsTo
    {
        return $this->belongsTo(PrintingQuotation::class);
    }

    /**
     * @return BelongsTo<CommercialQuotation, $this>
     */
    public function commercialQuotation(): BelongsTo
    {
        return $this->belongsTo(CommercialQuotation::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return HasMany<PaymentAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /**
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function belongsToCustomer(User $user): bool
    {
        return $this->customer_id === $user->id;
    }
}
