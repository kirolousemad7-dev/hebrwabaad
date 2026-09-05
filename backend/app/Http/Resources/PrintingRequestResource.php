<?php

namespace App\Http\Resources;

use App\Enums\PrintingPricingType;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Models\PrintingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PrintingRequest
 */
class PrintingRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviewer = $request->user()?->role instanceof UserRole
            && $request->user()->role->canReviewPrintingRequests();

        return [
            'id' => $this->id,
            'product_slug' => $this->product_slug,
            'product_name' => $this->product_name,
            'width' => $this->width,
            'height' => $this->height,
            'dimension_unit' => $this->dimension_unit->value,
            'shape' => $this->shape->value,
            'material' => $this->material,
            'quantity' => $this->quantity,
            'printing_method' => $this->printing_method->value,
            'finishing' => $this->finishing ?? [],
            'required_date' => $this->required_date?->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status instanceof PrintingRequestStatus
                ? $this->status->value
                : $this->status,
            'filename' => $this->original_filename,
            'pricing_type' => $this->pricing_type instanceof PrintingPricingType
                ? $this->pricing_type->value
                : $this->pricing_type,
            'estimated_price' => $this->estimated_price,
            'quoted_price' => $this->quoted_price,
            'pricing_notes' => $this->pricing_notes,
            'quoted_at' => $this->quoted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => $this->when($reviewer && $this->relationLoaded('user'), function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                ];
            }),
            'quoted_by' => $this->when($reviewer && $this->relationLoaded('quotedBy'), function () {
                if ($this->quotedBy === null) {
                    return null;
                }

                return [
                    'id' => $this->quotedBy->id,
                    'name' => $this->quotedBy->name,
                ];
            }),
        ];
    }
}
