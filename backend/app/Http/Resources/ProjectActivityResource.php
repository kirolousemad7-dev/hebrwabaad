<?php

namespace App\Http\Resources;

use App\Models\ProjectActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectActivity
 */
class ProjectActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'actor' => $this->actorPayload(),
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'metadata' => $this->metadata ?? new \stdClass,
            'is_client_visible' => (bool) $this->is_client_visible,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{type: string, id: int, name: string}|null
     */
    private function actorPayload(): ?array
    {
        if ($this->actor_user_id !== null) {
            return [
                'type' => 'user',
                'id' => (int) $this->actor_user_id,
                'name' => $this->actorUser?->name ?? 'Staff',
            ];
        }

        if ($this->actor_customer_id !== null) {
            return [
                'type' => 'customer',
                'id' => (int) $this->actor_customer_id,
                'name' => $this->actorCustomer?->name ?? 'Customer',
            ];
        }

        return null;
    }
}
