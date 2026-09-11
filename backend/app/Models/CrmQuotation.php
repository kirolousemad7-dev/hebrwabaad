<?php

namespace App\Models;

use App\Enums\CrmQuotationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'lead_id',
    'opportunity_id',
    'customer_id',
    'created_by',
    'status',
    'subtotal',
    'discount_amount',
    'discount_percent',
    'tax_amount',
    'total',
    'currency',
    'valid_until',
    'notes',
    'terms',
    'discount_approved_by',
    'sent_at',
    'public_token',
    'delivery_time',
    'approved_at',
    'rejected_at',
    'accepted_at',
    'rejection_notes',
])]
class CrmQuotation extends Model
{
    protected function casts(): array
    {
        return [
            'status' => CrmQuotationStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CrmLead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    /**
     * @return BelongsTo<CrmOpportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return HasMany<CrmQuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CrmQuotationItem::class, 'quotation_id')->orderBy('sort_order');
    }
}
