<?php

namespace App\Http\Resources;

use App\Models\PackageItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PackageItem
 */
class PackageItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'quantity' => $this->quantity,
            'sort_order' => $this->sort_order,
            'notes' => $this->notes,
            'service' => ServiceResource::make($this->whenLoaded('service')),
        ];
    }
}
