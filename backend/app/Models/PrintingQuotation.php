<?php

namespace App\Models;

use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingQuotationStatus;
use Database\Factories\PrintingQuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'revision',
    'printing_request_id',
    'customer_id',
    'created_by',
    'status',
    'currency',
    'subtotal',
    'tax_amount',
    'discount_amount',
    'total',
    'deposit_required',
    'payment_policy',
    'valid_until',
    'notes',
    'terms',
    'rejection_reason',
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
class PrintingQuotation extends Model
{
    /** @use HasFactory<PrintingQuotationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'status' => PrintingQuotationStatus::class,
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'deposit_required' => 'decimal:2',
            'payment_policy' => PrintingPaymentPolicy::class,
            'valid_until' => 'date',
            'token_revoked_at' => 'datetime',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PrintingRequest, $this>
     */
    public function printingRequest(): BelongsTo
    {
        return $this->belongsTo(PrintingRequest::class);
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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<PrintingQuotation, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * @return HasMany<PrintingQuotation, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    /**
     * @return HasMany<PrintingQuotationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PrintingQuotationEvent::class)->orderByDesc('id');
    }

    public static function hashToken(string $rawToken): string
    {
        return hash_hmac('sha256', $rawToken, (string) config('app.key'));
    }

    public static function findByRawToken(string $rawToken): ?self
    {
        if ($rawToken === '') {
            return null;
        }

        return self::query()
            ->where('public_token_hash', self::hashToken($rawToken))
            ->first();
    }

    public static function findByTrackingToken(string $rawToken): ?self
    {
        if ($rawToken === '') {
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
}
