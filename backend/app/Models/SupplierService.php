<?php

namespace App\Models;

use App\Enums\SupplierPricingModel;
use Database\Factories\SupplierServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_id',
    'name',
    'description',
    'pricing_model',
    'minimum_price',
    'maximum_price',
    'currency',
    'delivery_time',
    'notes',
    'is_active',
    'sort_order',
])]
class SupplierService extends Model
{
    /** @use HasFactory<SupplierServiceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'pricing_model' => SupplierPricingModel::CustomQuote->value,
        'currency' => 'SAR',
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_model' => SupplierPricingModel::class,
            'minimum_price' => 'decimal:2',
            'maximum_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
