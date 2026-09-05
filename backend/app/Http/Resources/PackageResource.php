<?php

namespace App\Http\Resources;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Package
 */
class PackageResource extends JsonResource
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
            'description' => $this->description,
            'audience' => $this->audience,
            'deliverables' => $this->deliverables ?? [],
            'category' => $this->category->value,
            'price' => $this->price,
            'discount_amount' => $this->discount_amount,
            'final_price' => $this->finalPrice(),
            'currency' => $this->currency,
            'pricing_mode' => $this->pricingMode()->value,
            'pricing_label' => $this->pricingMode()->label(),
            'is_chargeable' => $this->isChargeable(),
            'duration_days' => $this->duration_days,
            'revision_rounds' => $this->revision_rounds,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'items' => PackageItemResource::collection($this->whenLoaded('items')),
            'tiers' => PackageTierResource::collection($this->whenLoaded('tiers')),
            ...CatalogVisibility::managementFields($request, fn () => [
                'is_active' => $this->is_active,
            ]),
        ];
    }
}
