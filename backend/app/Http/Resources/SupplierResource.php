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
            'short_description' => $this->short_description,
            'description' => $this->description,
            'specialties' => $this->specialties ?? [],
            'services' => $this->services ?? [],
            'location' => $this->location,
            'featured' => $this->is_featured,
            'portfolio_count' => $this->when(
                isset($this->public_portfolio_items_count),
                fn () => (int) $this->public_portfolio_items_count,
            ),
            'portfolio_preview' => $this->when(
                $this->relationLoaded('publicPortfolioItems'),
                fn () => SupplierPortfolioItemResource::collection($this->publicPortfolioItems->take(2)->values())->resolve(),
            ),
            'portfolio' => $this->when(
                $this->relationLoaded('publicPortfolioItems'),
                fn () => SupplierPortfolioItemResource::collection($this->publicPortfolioItems)->resolve(),
            ),
        ];
    }
}
