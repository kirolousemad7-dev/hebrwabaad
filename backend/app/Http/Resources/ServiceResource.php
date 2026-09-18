<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'short_description' => $this->summary,
            'description' => $this->description,
            'scope' => $this->scope,
            'deliverables' => $this->deliverables ?? [],
            'features' => $this->features ?? [],
            'process_steps' => $this->process_steps ?? [],
            'faq' => $this->faq ?? [],
            'tags' => $this->tags ?? [],
            'gallery' => $this->gallery ?? [],
            'hero_image' => $this->hero_image,
            'category' => $this->category->value,
            'subcategory' => $this->subcategory,
            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'pricing_mode' => $this->pricingMode()->value,
            'pricing_label' => $this->pricingMode()->label(),
            'is_chargeable' => $this->isChargeable(),
            'duration_days' => $this->duration_days,
            'revision_rounds' => $this->revision_rounds,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'og_title' => $this->og_title,
                'og_description' => $this->og_description,
                'og_image' => $this->og_image,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots ?? 'index,follow',
            ],
            'packages' => $this->whenLoaded('packages', fn () => $this->packages->map(fn ($package) => [
                'id' => $package->id,
                'name' => $package->name,
                'slug' => $package->slug,
                'price' => $package->price,
                'currency' => $package->currency,
                'pricing_mode' => $package->pricing_mode instanceof \BackedEnum
                    ? $package->pricing_mode->value
                    : $package->pricing_mode,
                'summary' => $package->description,
            ])->values()),
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($addon) => [
                'id' => $addon->id,
                'name' => $addon->name,
                'slug' => $addon->slug,
                'summary' => $addon->summary,
                'price' => $addon->price,
                'currency' => $addon->currency,
                'pricing_mode' => $addon->pricing_mode instanceof \BackedEnum
                    ? $addon->pricing_mode->value
                    : $addon->pricing_mode,
            ])->values()),
            'portfolio' => $this->whenLoaded('portfolioItems', fn () => $this->portfolioItems->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'description' => $item->short_description ?? $item->description,
                'image_url' => $item->image_url,
            ])->values()),
            'suppliers' => $this->whenLoaded('suppliers', fn () => $this->suppliers->map(fn ($supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->display_name ?: $supplier->name,
                'slug' => $supplier->slug,
            ])->values()),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'supplier_id' => $product->supplier_id,
            ])->values()),
            ...CatalogVisibility::managementFields($request, fn () => [
                'is_active' => $this->is_active,
                'is_public' => $this->is_public,
                'department_id' => $this->department_id,
                'task_title_template' => $this->task_title_template,
                'default_task_priority' => $this->default_task_priority,
                'requires_review' => (bool) $this->requires_review,
                'requires_customer_approval' => (bool) $this->requires_customer_approval,
                'checklist_template' => $this->checklist_template ?? [],
                'packages_count' => $this->whenCounted('packageItems'),
                'addon_ids' => $this->whenLoaded('addons', fn () => $this->addons->pluck('id')->values()),
                'sector_ids' => $this->whenLoaded('sectors', fn () => $this->sectors->pluck('id')->values()),
                'portfolio_item_ids' => $this->whenLoaded('portfolioItems', fn () => $this->portfolioItems->pluck('id')->values()),
                'supplier_ids' => $this->whenLoaded('suppliers', fn () => $this->suppliers->pluck('id')->values()),
                'product_ids' => $this->whenLoaded('products', fn () => $this->products->pluck('id')->values()),
                'project_ids' => $this->whenLoaded('projects', fn () => $this->projects->pluck('id')->values()),
                'quotation_ids' => $this->whenLoaded('quotations', fn () => $this->quotations->pluck('id')->values()),
                'seo_title' => $this->seo_title,
                'seo_description' => $this->seo_description,
                'og_title' => $this->og_title,
                'og_description' => $this->og_description,
                'og_image' => $this->og_image,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots,
            ]),
        ];
    }
}
