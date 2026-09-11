<?php

namespace App\Models;

use App\Enums\CatalogPricingMode;
use App\Models\Concerns\HasSlug;
use Database\Factories\CatalogAddonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'slug',
    'name',
    'summary',
    'description',
    'pricing_mode',
    'price',
    'percentage_bps',
    'currency',
    'min_qty',
    'max_qty',
    'is_urgent',
    'requires_capacity',
    'capacity_available',
    'is_active',
    'is_public',
    'sort_order',
])]
class CatalogAddon extends Model
{
    /** @use HasFactory<CatalogAddonFactory> */
    use HasFactory, HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'pricing_mode' => CatalogPricingMode::Quote->value,
        'currency' => 'SAR',
        'is_urgent' => false,
        'requires_capacity' => false,
        'capacity_available' => false,
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
            'pricing_mode' => CatalogPricingMode::class,
            'price' => 'decimal:2',
            'percentage_bps' => 'integer',
            'min_qty' => 'integer',
            'max_qty' => 'integer',
            'is_urgent' => 'boolean',
            'requires_capacity' => 'boolean',
            'capacity_available' => 'boolean',
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
        return $this->belongsToMany(Service::class, 'addon_service')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Package, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'addon_package')->withTimestamps();
    }

    public function pricingMode(): CatalogPricingMode
    {
        return $this->pricing_mode instanceof CatalogPricingMode
            ? $this->pricing_mode
            : CatalogPricingMode::from((string) ($this->pricing_mode ?: CatalogPricingMode::Quote->value));
    }

    /**
     * Urgent add-ons are only selectable when capacity is flagged available.
     */
    public function isAvailable(): bool
    {
        if (! $this->is_active || ! $this->is_public) {
            return false;
        }

        if ($this->requires_capacity && ! $this->capacity_available) {
            return false;
        }

        return true;
    }

    public function isChargeable(): bool
    {
        return $this->pricingMode()->isChargeable() && $this->price !== null && (float) $this->price > 0;
    }

    /**
     * @param  Builder<CatalogAddon>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<CatalogAddon>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }
}
