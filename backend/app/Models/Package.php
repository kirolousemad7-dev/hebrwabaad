<?php

namespace App\Models;

use App\Enums\CatalogPricingMode;
use App\Enums\PackageCategory;
use App\Models\Concerns\HasSlug;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'audience',
    'deliverables',
    'category',
    'price',
    'discount_amount',
    'currency',
    'pricing_mode',
    'duration_days',
    'revision_rounds',
    'is_active',
    'is_featured',
    'sort_order',
])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory, HasSlug;

    /**
     * Mirrors the database defaults so freshly created models report them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'SAR',
        'discount_amount' => 0,
        'pricing_mode' => CatalogPricingMode::Fixed->value,
        'is_active' => true,
        'is_featured' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PackageCategory::class,
            'pricing_mode' => CatalogPricingMode::class,
            'deliverables' => 'array',
            'price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'duration_days' => 'integer',
            'revision_rounds' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PackageItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<PackageTier, $this>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(PackageTier::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'package_items')
            ->withPivot(['quantity', 'sort_order', 'notes'])
            ->withTimestamps();
    }

    /**
     * Displayed price after the package discount. Computed, never stored.
     */
    public function finalPrice(): string
    {
        return number_format(max(0, (float) $this->price - (float) $this->discount_amount), 2, '.', '');
    }

    public function pricingMode(): CatalogPricingMode
    {
        return $this->pricing_mode instanceof CatalogPricingMode
            ? $this->pricing_mode
            : CatalogPricingMode::from((string) ($this->pricing_mode ?: CatalogPricingMode::Fixed->value));
    }

    /**
     * A package can only be charged directly when the owner published a fixed price.
     */
    public function isChargeable(): bool
    {
        return $this->pricingMode()->isChargeable() && (float) $this->finalPrice() > 0;
    }

    /**
     * @param  Builder<Package>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
