<?php

namespace App\Models;

use App\Enums\QuotationLineCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'commercial_quotation_id',
    'description',
    'quantity',
    'unit_price',
    'subtotal',
    'category',
    'sort_order',
    'meta',
])]
class CommercialQuotationItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'category' => QuotationLineCategory::class,
            'sort_order' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CommercialQuotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(CommercialQuotation::class, 'commercial_quotation_id');
    }
}
