<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable([
    'name',
    'slug',
    'scope',
    'color',
    'is_active',
])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasSlug;

    public const SCOPES = [
        'supplier',
        'service',
        'product',
        'portfolio',
        'project',
        'task',
        'shared',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => 'supplier',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Supplier, $this>
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)->withTimestamps();
    }

    /**
     * @return MorphToMany<SupplierProduct, $this>
     */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(SupplierProduct::class, 'taggable')->withTimestamps();
    }

    /**
     * @return MorphToMany<SupplierService, $this>
     */
    public function services(): MorphToMany
    {
        return $this->morphedByMany(SupplierService::class, 'taggable')->withTimestamps();
    }

    /**
     * @return MorphToMany<SupplierPortfolioItem, $this>
     */
    public function portfolioItems(): MorphToMany
    {
        return $this->morphedByMany(SupplierPortfolioItem::class, 'taggable')->withTimestamps();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSuppliers(Builder $query): Builder
    {
        return $query->whereIn('scope', ['supplier', 'shared']);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReusable(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
