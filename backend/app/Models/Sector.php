<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name_ar',
    'name_en',
    'slug',
    'description',
    'needs',
    'cover_image',
    'is_active',
    'is_public',
    'sort_order',
    'seo_title',
    'seo_description',
])]
class Sector extends Model
{
    /** @use HasFactory<SectorFactory> */
    use HasFactory, HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_public' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'needs' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'sector_service')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Package, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'sector_package')->withTimestamps();
    }

    /**
     * @return BelongsToMany<PortfolioItem, $this>
     */
    public function portfolioItems(): BelongsToMany
    {
        return $this->belongsToMany(PortfolioItem::class, 'sector_portfolio_item')->withTimestamps();
    }

    /**
     * @param  Builder<Sector>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Sector>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }
}
