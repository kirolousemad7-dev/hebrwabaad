<?php

namespace App\Http\Resources;

use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierProduct
 */
class SupplierProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'images' => $this->images ?? [],
            'category' => $this->category,
            'specifications' => $this->specifications,
            'variants' => $this->variants,
            'price' => $this->price,
            'currency' => $this->currency,
            'unit' => $this->unit,
            'minimum_quantity' => $this->minimum_quantity,
            'contact_for_price' => $this->contact_for_price,
            'availability' => $this->availability,
            'lead_time' => $this->lead_time,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'status' => $this->status?->value,
            'visibility' => $this->visibility?->value ?? $this->visibility,
            'review_notes' => $this->review_notes,
            'internal_notes' => $this->when(
                $request->user()?->canReviewContent() === true,
                $this->internal_notes,
            ),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'canonical_url' => $this->canonical_url,
            'robots' => $this->robots,
            'tags' => $this->when($this->relationLoaded('tags'), fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
            ])->all()),
        ];
    }
}
