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
        $user = $request->user();
        $canReview = $user?->canReviewContent() ?? false;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'supplier_code' => $this->supplier_code,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'cover_image' => $this->cover_image,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'specialties' => $this->specialties ?? [],
            'services' => $this->services ?? [],
            'location' => $this->location,
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
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
            'show_public_contact' => $this->show_public_contact,
            'status' => $this->status?->value,
            'verification_status' => $this->verification_status?->value,
            'onboarding_status' => $this->onboarding_status?->value,
            'rating' => $this->rating,
            'profile_status' => $this->profile_status?->value,
            'review_notes' => $this->review_notes,
            'notes' => $this->notes,
            'internal_notes' => $this->when($canReview, $this->internal_notes),
            'locked_fields' => $this->locked_fields ?? [],
            'contact_person' => $this->contact_person,
            'owner_change_request' => $this->when($canReview || $request->user()?->role?->value === 'SUPPLIER', $this->owner_change_request),
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
            'contacts_count' => (int) ($this->contacts_count ?? 0),
            'services_count' => (int) ($this->offered_services_count ?? 0),
            'documents_count' => (int) ($this->documents_count ?? 0),
            'categories' => $this->when($this->relationLoaded('categories'), fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->all()),
            'tags' => $this->when($this->relationLoaded('tags'), fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
            ])->all()),
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
