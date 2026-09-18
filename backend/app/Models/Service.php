<?php

namespace App\Models;

use App\Enums\CatalogPricingMode;
use App\Enums\ServiceCategory;
use App\Models\Concerns\HasMedia;
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
    'subcategory',
    'hero_image',
    'gallery',
    'features',
    'process_steps',
    'faq',
    'tags',
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
    'seo_title',
    'seo_description',
    'og_title',
    'og_description',
    'og_image',
    'canonical_url',
    'robots',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasMedia, HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'SAR',
        'pricing_mode' => CatalogPricingMode::Fixed->value,
        'is_active' => true,
        'is_featured' => false,
        'is_public' => true,
        'sort_order' => 0,
        'robots' => 'index,follow',
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
            'gallery' => 'array',
            'features' => 'array',
            'process_steps' => 'array',
            'faq' => 'array',
            'tags' => 'array',
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

    /**
     * @return BelongsToMany<PortfolioItem, $this>
     */
    public function portfolioItems(): BelongsToMany
    {
        return $this->belongsToMany(PortfolioItem::class, 'portfolio_item_service')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Supplier, $this>
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'service_supplier')->withTimestamps();
    }

    /**
     * @return BelongsToMany<SupplierProduct, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(SupplierProduct::class, 'service_supplier_product')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_service')->withTimestamps();
    }

    /**
     * @return BelongsToMany<CommercialQuotation, $this>
     */
    public function quotations(): BelongsToMany
    {
        return $this->belongsToMany(CommercialQuotation::class, 'commercial_quotation_service')->withTimestamps();
    }

    public function pricingMode(): CatalogPricingMode
    {
        return $this->pricing_mode instanceof CatalogPricingMode
            ? $this->pricing_mode
            : CatalogPricingMode::from((string) ($this->pricing_mode ?: CatalogPricingMode::Fixed->value));
    }

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
