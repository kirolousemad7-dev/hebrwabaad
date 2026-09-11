<?php

namespace App\Models;

use App\Enums\CatalogPricingMode;
use App\Enums\ServiceCategory;
use App\Models\Concerns\HasSlug;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'summary',
    'description',
    'scope',
    'deliverables',
    'category',
    'base_price',
    'currency',
    'pricing_mode',
    'duration_days',
    'revision_rounds',
    'is_active',
    'is_featured',
    'is_public',
    'sort_order',
    'department_id',
    'task_title_template',
    'default_task_priority',
    'requires_review',
    'requires_customer_approval',
    'checklist_template',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasSlug;

    /**
     * Mirrors the database defaults so freshly created models report them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'SAR',
        'pricing_mode' => CatalogPricingMode::Fixed->value,
        'is_active' => true,
        'is_featured' => false,
        'is_public' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'pricing_mode' => CatalogPricingMode::class,
            'deliverables' => 'array',
            'base_price' => 'decimal:2',
            'duration_days' => 'integer',
            'revision_rounds' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
            'requires_review' => 'boolean',
            'requires_customer_approval' => 'boolean',
            'checklist_template' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<PackageItem, $this>
     */
    public function packageItems(): HasMany
    {
        return $this->hasMany(PackageItem::class);
    }

    /**
     * @return BelongsToMany<Package, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_items')
            ->withPivot(['quantity', 'sort_order', 'notes'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Sector, $this>
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'sector_service')->withTimestamps();
    }

    /**
     * @return BelongsToMany<CatalogAddon, $this>
     */
    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(CatalogAddon::class, 'addon_service')->withTimestamps();
    }

    public function pricingMode(): CatalogPricingMode
    {
        return $this->pricing_mode instanceof CatalogPricingMode
            ? $this->pricing_mode
            : CatalogPricingMode::from((string) ($this->pricing_mode ?: CatalogPricingMode::Fixed->value));
    }

    /**
     * A service can only be charged directly when the owner published a fixed price.
     */
    public function isChargeable(): bool
    {
        return $this->pricingMode()->isChargeable() && (float) $this->base_price > 0;
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }
}
