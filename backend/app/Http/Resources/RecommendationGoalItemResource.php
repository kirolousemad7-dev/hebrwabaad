<?php

namespace App\Http\Resources;

use App\Models\RecommendationGoalItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecommendationGoalItem
 */
class RecommendationGoalItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'item_slug' => $this->item_slug,
            'priority' => $this->priority,
            'reason_ar' => $this->reason_ar,
            'quantity' => $this->quantity,
            'is_active' => $this->is_active,
        ];
    }
}
