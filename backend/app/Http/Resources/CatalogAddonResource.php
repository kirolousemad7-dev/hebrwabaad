<?php

namespace App\Http\Resources;

use App\Models\CatalogAddon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CatalogAddon
 */
class CatalogAddonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'pricing_mode' => $this->pricingMode()->value,
            'pricing_label' => $this->pricingMode()->label(),
            'price' => $this->price,
            'percentage_bps' => $this->percentage_bps,
            'currency' => $this->currency,
            'min_qty' => $this->min_qty,
            'max_qty' => $this->max_qty,
            'is_urgent' => $this->is_urgent,
            'requires_capacity' => $this->requires_capacity,
            'capacity_available' => $this->capacity_available,
            'is_available' => $this->isAvailable(),
            'is_chargeable' => $this->isChargeable(),
            'sort_order' => $this->sort_order,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'packages' => PackageResource::collection($this->whenLoaded('packages')),
            ...CatalogVisibility::managementFields($request, fn () => [
                'is_active' => $this->is_active,
                'is_public' => $this->is_public,
            ]),
        ];
    }
}
