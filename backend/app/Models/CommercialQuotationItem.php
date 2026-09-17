<?php

namespace App\Models;

use App\Enums\QuotationLineCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'commercial_quotation_id',
    'description',
    'quantity',
    'unit_price',
    'subtotal',
    'category',
    'sort_order',
    'meta',
    'selected_supplier_quote_id',
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

    /**
     * @return HasMany<QuotationSupplierQuote, $this>
     */
    public function supplierQuotes(): HasMany
    {
        return $this->hasMany(QuotationSupplierQuote::class, 'commercial_quotation_item_id');
    }

    /**
     * @return BelongsTo<QuotationSupplierQuote, $this>
     */
    public function selectedSupplierQuote(): BelongsTo
    {
        return $this->belongsTo(QuotationSupplierQuote::class, 'selected_supplier_quote_id');
    }
}
