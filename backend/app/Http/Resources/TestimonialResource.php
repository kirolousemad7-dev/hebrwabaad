<?php

namespace App\Http\Resources;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Testimonial
 */
class TestimonialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'author_role' => $this->author_role,
            'quote' => $this->quote,
            'sort_order' => $this->sort_order,
        ];

        if ($request->user()?->role?->canManageCatalog()) {
            $payload['is_published'] = $this->is_published;
            $payload['updated_at'] = $this->updated_at?->toIso8601String();
        }

        return $payload;
    }
}
