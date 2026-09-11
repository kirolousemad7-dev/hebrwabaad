<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name_ar',
    'name_en',
    'is_active',
    'sort_order',
])]
class PrintingProductCategory extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<PrintingProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(PrintingProduct::class, 'category_id');
    }
}
