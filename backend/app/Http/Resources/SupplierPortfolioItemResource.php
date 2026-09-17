<?php

namespace App\Http\Resources;

use App\Models\SupplierPortfolioItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierPortfolioItem
 */
class SupplierPortfolioItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->canReviewContent() === true;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'category' => $this->category,
            'gallery' => $this->when($isAdmin, $this->gallery ?? []),
            'videos' => $this->when($isAdmin, $this->videos ?? []),
            'tags' => $this->when($isAdmin, $this->tags ?? []),
            'external_url' => $this->when($isAdmin, $this->external_url),
            'completion_date' => $this->when($isAdmin, $this->completion_date?->toDateString()),
            'status' => $this->when($isAdmin, $this->status?->value),
            'visibility' => $this->when($isAdmin, $this->visibility?->value ?? $this->visibility),
            'is_featured' => $this->when($isAdmin, $this->is_featured),
            'is_active' => $this->when($isAdmin, $this->is_active),
        ];
    }
}
