<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class AdminSupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'cover_image' => $this->cover_image,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'specialties' => $this->specialties ?? [],
            'services' => $this->services ?? [],
            'location' => $this->location,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'category' => $this->category,
            'brand_colors' => $this->brand_colors,
            'brand_description' => $this->brand_description,
            'years_experience' => $this->years_experience,
            'min_order_info' => $this->min_order_info,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_published' => $this->is_published,
            'profile_status' => $this->profile_status?->value,
            'review_notes' => $this->review_notes,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'canonical_url' => $this->canonical_url,
            'robots' => $this->robots,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'content_count' => (int) ($this->portfolio_items_count ?? 0) + (int) ($this->products_count ?? 0),
            'products_count' => (int) ($this->products_count ?? 0),
            'user' => $this->when($this->relationLoaded('user') && $this->user, fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'pending_profile' => $this->when(
                $this->relationLoaded('pendingProfileVersion') && $this->pendingProfileVersion,
                fn () => [
                    'id' => $this->pendingProfileVersion?->id,
                    'status' => $this->pendingProfileVersion?->status?->value,
                    'payload' => $this->pendingProfileVersion?->payload,
                    'review_notes' => $this->pendingProfileVersion?->review_notes,
                ],
            ),
        ];
    }
}
