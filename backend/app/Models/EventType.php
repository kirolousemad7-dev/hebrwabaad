<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug',
    'name_ar',
    'name_en',
    'description',
    'is_active',
    'is_public',
    'sort_order',
])]
class EventType extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
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
}
