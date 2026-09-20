<?php

namespace App\Http\Resources;

use App\Models\ProjectActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-safe activity representation — no raw metadata or internal details.
 *
 * @mixin ProjectActivity
 */
class CustomerProjectActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->customerSafeDescription(),
            'actor' => $this->actorPayload(),
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function customerSafeDescription(): string
    {
        return match ($this->action) {
            'project.created' => 'Project created',
            'project.status_changed' => 'Project status updated',
            'milestone.created' => 'Milestone added',
            'milestone.completed' => 'Milestone completed',
            'file.uploaded', 'customer.file_uploaded' => 'A file was shared with you',
            'file.client_visibility_changed' => 'A file was shared with you',
            'customer.project_action' => 'Project update',
            'customer.milestone_action' => 'Milestone update',
            'calendar_item.completed' => 'A calendar item was completed',
            default => 'Project update',
        };
    }

    /**
     * @return array{type: string, id: int|null, name: string}|null
     */
    private function actorPayload(): ?array
    {
        if ($this->actor_customer_id !== null) {
            $customer = $this->relationLoaded('actorCustomer') ? $this->actorCustomer : null;

            return [
                'type' => 'customer',
                'id' => (int) $this->actor_customer_id,
                'name' => $customer?->name ?? 'Customer',
            ];
        }

        if ($this->actor_user_id !== null) {
            return [
                'type' => 'staff',
                'id' => null,
                'name' => 'Hebr & Ab3ad team',
            ];
        }

        return null;
    }
}
