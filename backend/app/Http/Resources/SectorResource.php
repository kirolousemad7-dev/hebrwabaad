<?php

namespace App\Http\Resources;

use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sector
 */
class SectorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'description' => $this->description,
            'needs' => $this->needs ?? [],
            'cover_image' => $this->cover_image,
            'sort_order' => $this->sort_order,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'packages' => PackageResource::collection($this->whenLoaded('packages')),
            'portfolio_items' => PortfolioItemResource::collection($this->whenLoaded('portfolioItems')),
            ...CatalogVisibility::managementFields($request, fn () => [
                'is_active' => $this->is_active,
                'is_public' => $this->is_public,
            ]),
        ];
    }
}
