<?php

namespace App\Models;

use App\Enums\SupplierQuoteStatus;
use Database\Factories\QuotationSupplierQuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'commercial_quotation_id',
    'commercial_quotation_item_id',
    'supplier_id',
    'cost',
    'currency',
    'valid_until',
    'delivery_days',
    'notes',
    'attachments',
    'status',
    'requested_by',
    'requested_at',
    'received_at',
    'reviewed_at',
    'selected_at',
    'rejected_at',
    'rejection_reason',
    'replaced_by_id',
])]
class QuotationSupplierQuote extends Model
{
    /** @use HasFactory<QuotationSupplierQuoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'valid_until' => 'date',
            'delivery_days' => 'integer',
            'attachments' => 'array',
            'status' => SupplierQuoteStatus::class,
            'requested_at' => 'datetime',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'selected_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function isExpiredByDate(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    /**
     * @return BelongsTo<CommercialQuotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(CommercialQuotation::class, 'commercial_quotation_id');
    }

    /**
     * @return BelongsTo<CommercialQuotationItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(CommercialQuotationItem::class, 'commercial_quotation_item_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<QuotationSupplierQuote, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }
}
