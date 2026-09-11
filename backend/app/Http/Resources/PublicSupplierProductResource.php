<?php

namespace App\Http\Resources;

use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierProduct
 */
class PublicSupplierProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->when($request->route('productSlug') !== null, $this->description),
            'images' => $this->images ?? [],
            'category' => $this->category,
            'specifications' => $this->when($request->route('productSlug') !== null, $this->specifications ?? []),
            'variants' => $this->when($request->route('productSlug') !== null, $this->variants ?? []),
            'price' => $this->contact_for_price ? null : $this->price,
            'currency' => $this->currency,
            'contact_for_price' => $this->contact_for_price,
            'availability' => $this->availability,
            'featured' => $this->is_featured,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'og_title' => $this->og_title,
                'og_description' => $this->og_description,
                'og_image' => $this->og_image,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots ?? 'index,follow',
            ],
        ];
    }
}
