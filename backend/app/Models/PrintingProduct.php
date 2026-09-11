<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'category_id',
    'slug',
    'name_ar',
    'name_en',
    'short_description',
    'description',
    'image_path',
    'pricing_mode',
    'starting_price',
    'currency',
    'is_active',
    'is_public',
    'is_featured',
    'allows_design_and_print',
    'sort_order',
])]
class PrintingProduct extends Model
{
    protected function casts(): array
    {
        return [
            'starting_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'allows_design_and_print' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_public', true);
    }

    /**
     * @return BelongsTo<PrintingProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PrintingProductCategory::class, 'category_id');
    }

    /**
     * @return BelongsToMany<PrintingProductOption, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(
            PrintingProductOption::class,
            'printing_product_option',
            'printing_product_id',
            'printing_product_option_id',
        )->withTimestamps();
    }
}
