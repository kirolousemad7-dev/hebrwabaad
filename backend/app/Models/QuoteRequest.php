<?php

namespace App\Models;

use App\Enums\CommercialQuotationStatus;
use App\Enums\QuoteRequestSource;
use App\Enums\QuoteRequestStatus;
use Database\Factories\QuoteRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'customer_id',
    'order_id',
    'source_type',
    'source_id',
    'title',
    'status',
    'assigned_to',
    'requested_at',
    'required_date',
    'budget_min',
    'budget_max',
    'city',
    'customer_notes',
    'internal_notes',
    'information_request',
    'payload',
    'quotation_type',
    'quotation_id',
])]
class QuoteRequest extends Model
{
    /** @use HasFactory<QuoteRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => QuoteRequestSource::class,
            'status' => QuoteRequestStatus::class,
            'requested_at' => 'datetime',
            'required_date' => 'date',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'payload' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<QuoteRequestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(QuoteRequestEvent::class)->latest('id');
    }

    /**
     * @return HasMany<CommercialQuotation, $this>
     */
    public function commercialQuotations(): HasMany
    {
        return $this->hasMany(CommercialQuotation::class);
    }

    /**
     * @return HasMany<ManagedFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ManagedFile::class, 'quote_request_id');
    }

    public function latestCommercialQuotation(): ?CommercialQuotation
    {
        return $this->commercialQuotations()
            ->whereNotIn('status', [CommercialQuotationStatus::Cancelled->value])
            ->orderByDesc('revision')
            ->first();
    }
}
