<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'type',
    'slug',
    'name_ar',
    'name_en',
    'is_active',
    'sort_order',
])]
class PrintingProductOption extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<PrintingProduct, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            PrintingProduct::class,
            'printing_product_option',
            'printing_product_option_id',
            'printing_product_id',
        )->withTimestamps();
    }
}
