<?php

namespace App\Http\Resources;

use App\Models\EventRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventRequest
 */
class EventRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'event_type' => $this->event_type,
            'event_date' => $this->event_date?->toDateString(),
            'city' => $this->city,
            'attendance' => $this->attendance,
            'venue' => $this->venue,
            'budget_range' => $this->budget_range,
            'buy_or_rent' => $this->buy_or_rent,
            'notes' => $this->notes,
            'status' => $this->status,
            'project_id' => $this->project_id,
            'consultation_id' => $this->consultation_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project?->id,
                'title' => $this->project?->title,
                'status' => $this->project?->status?->value,
            ]),
        ];
    }
}
