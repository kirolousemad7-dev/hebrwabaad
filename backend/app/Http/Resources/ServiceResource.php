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
            'description' => $this->description,
            'category' => $this->category->value,
            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'pricing_mode' => $this->pricingMode()->value,
            'pricing_label' => $this->pricingMode()->label(),
            'is_chargeable' => $this->isChargeable(),
            'duration_days' => $this->duration_days,
            'is_featured' => $this->is_featured,
            ...CatalogVisibility::managementFields($request, fn () => [
                'is_active' => $this->is_active,
                'packages_count' => $this->whenCounted('packageItems'),
            ]),
        ];
    }
}
