<?php

namespace App\Http\Resources;

use App\Models\RecommendationGoal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecommendationGoal
 */
class RecommendationGoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name_ar' => $this->name_ar,
            'explanation' => $this->explanation,
            'sort_order' => $this->sort_order,
            'items' => RecommendationGoalItemResource::collection($this->whenLoaded('items')),
            ...CatalogVisibility::managementFields($request, fn () => [
                'is_active' => $this->is_active,
            ]),
        ];
    }
}
