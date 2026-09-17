<?php

namespace App\Models;

use App\Enums\CommercialQuotationStatus;
use App\Enums\PrintingPaymentPolicy;
use Database\Factories\CommercialQuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'revision',
    'quote_request_id',
    'customer_id',
    'order_id',
    'execution_project_id',
    'created_by',
    'status',
    'currency',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'shipping_amount',
    'rental_amount',
    'total',
    'deposit_required',
    'payment_policy',
    'valid_until',
    'execution_duration',
    'revision_count',
    'notes',
    'terms',
    'delivery_terms',
    'internal_notes',
    'rejection_reason',
    'revision_reason',
    'public_token_hash',
    'public_token_hint',
    'token_revoked_at',
    'sent_at',
    'viewed_at',
    'accepted_at',
    'rejected_at',
    'expired_at',
    'supersedes_id',
    'snapshot',
    'tracking_token_hash',
    'tracking_token_hint',
])]
class CommercialQuotation extends Model
{
    /** @use HasFactory<CommercialQuotationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'status' => CommercialQuotationStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'rental_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'deposit_required' => 'decimal:2',
            'payment_policy' => PrintingPaymentPolicy::class,
            'valid_until' => 'date',
            'revision_count' => 'integer',
            'token_revoked_at' => 'datetime',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function findByRawToken(string $rawToken): ?self
    {
        if ($rawToken === '' || strlen($rawToken) < 32) {
            return null;
        }

        return self::query()
            ->where('public_token_hash', self::hashToken($rawToken))
            ->first();
    }

    public static function findByTrackingToken(string $rawToken): ?self
    {
        if ($rawToken === '' || strlen($rawToken) < 32) {
            return null;
        }

        return self::query()
            ->where('tracking_token_hash', self::hashToken($rawToken))
            ->first();
    }

    public function isTokenRevoked(): bool
    {
        return $this->token_revoked_at !== null;
    }

    public function isExpiredByDate(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    /**
     * @return BelongsTo<QuoteRequest, $this>
     */
    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function executionProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'execution_project_id');
    }

    /**
     * @return HasMany<CommercialQuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CommercialQuotationItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<QuotationSupplierQuote, $this>
     */
    public function supplierQuotes(): HasMany
    {
        return $this->hasMany(QuotationSupplierQuote::class);
    }

    /**
     * @return HasMany<CommercialQuotationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CommercialQuotationEvent::class)->latest('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<CommercialQuotation, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * @return HasMany<CommercialQuotation, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }
}
