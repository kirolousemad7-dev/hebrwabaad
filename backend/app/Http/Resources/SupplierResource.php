<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
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
            'logo' => $this->logo,
            'cover_image' => $this->cover_image,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'specialties' => $this->specialties ?? [],
            'services' => $this->services ?? [],
            'location' => $this->location,
            'address' => $this->when($this->relationLoaded('publicProducts'), $this->address),
            'category' => $this->category,
            'years_experience' => $this->years_experience,
            'min_order_info' => $this->min_order_info,
            'brand_colors' => $this->brand_colors,
            'brand_description' => $this->brand_description,
            'phone' => $this->when($this->relationLoaded('publicProducts'), $this->phone),
            'email' => $this->when($this->relationLoaded('publicProducts'), $this->email),
            'website' => $this->when($this->relationLoaded('publicProducts'), $this->website),
            'featured' => $this->is_featured,
            'seo' => $this->when($this->relationLoaded('publicProducts'), fn () => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'og_title' => $this->og_title,
                'og_description' => $this->og_description,
                'og_image' => $this->og_image,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots ?? 'index,follow',
            ]),
            'portfolio_count' => $this->when(
                isset($this->public_portfolio_items_count),
                fn () => (int) $this->public_portfolio_items_count,
            ),
            'featured_products' => $this->when(
                $this->relationLoaded('featuredPublicProducts') || $this->relationLoaded('publicProducts'),
                function () {
                    $items = $this->relationLoaded('featuredPublicProducts')
                        ? $this->featuredPublicProducts
                        : $this->publicProducts->where('is_featured', true);

                    return PublicSupplierProductResource::collection($items->take(3)->values())->resolve();
                },
            ),
            'portfolio_preview' => $this->when(
                $this->relationLoaded('publicPortfolioItems'),
                fn () => SupplierPortfolioItemResource::collection($this->publicPortfolioItems->take(2)->values())->resolve(),
            ),
            'portfolio' => $this->when(
                $this->relationLoaded('publicPortfolioItems'),
                fn () => SupplierPortfolioItemResource::collection($this->publicPortfolioItems)->resolve(),
            ),
            'products' => $this->when(
                $this->relationLoaded('publicProducts'),
                fn () => PublicSupplierProductResource::collection($this->publicProducts)->resolve(),
            ),
        ];
    }
}
