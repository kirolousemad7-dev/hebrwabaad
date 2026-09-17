<?php

namespace App\Models;

use App\Enums\SupplierPricingModel;
use App\Enums\SupplierVisibility;
use Database\Factories\SupplierServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'supplier_id',
    'name',
    'category',
    'description',
    'pricing_model',
    'minimum_price',
    'maximum_price',
    'currency',
    'delivery_time',
    'service_area',
    'notes',
    'attachments',
    'is_active',
    'visibility',
    'sort_order',
])]
class SupplierService extends Model
{
    /** @use HasFactory<SupplierServiceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'pricing_model' => SupplierPricingModel::CustomQuote->value,
        'currency' => 'SAR',
        'is_active' => true,
        'visibility' => SupplierVisibility::Internal->value,
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
            'attachments' => 'array',
            'is_active' => 'boolean',
            'visibility' => SupplierVisibility::class,
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

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }
}
