<?php

namespace App\Http\Resources;

use App\Models\PackageTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PackageTier
 */
class PackageTierResource extends JsonResource
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
            'price' => $this->price === null ? null : number_format((float) $this->price, 2, '.', ''),
            'currency' => $this->currency,
            'duration_days' => $this->duration_days,
            'revision_rounds' => $this->revision_rounds,
            'deliverables' => $this->deliverables ?? [],
            'sort_order' => $this->sort_order,
            'is_priced' => $this->isPriced(),
            ...CatalogVisibility::managementFields($request, fn () => [
                'is_active' => $this->is_active,
            ]),
        ];
    }
}
