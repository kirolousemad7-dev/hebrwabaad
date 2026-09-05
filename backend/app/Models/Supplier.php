<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'logo',
    'short_description',
    'description',
    'specialties',
    'services',
    'location',
    'is_active',
    'is_featured',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_featured' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'services' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SupplierPortfolioItem, $this>
     */
    public function portfolioItems(): HasMany
    {
        return $this->hasMany(SupplierPortfolioItem::class);
    }

    /**
     * @param  Builder<Supplier>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<SupplierPortfolioItem, $this>
     */
    public function publicPortfolioItems(): HasMany
    {
        return $this->portfolioItems()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
