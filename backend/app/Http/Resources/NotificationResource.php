<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'type' => is_string($payload['type'] ?? null) ? $payload['type'] : 'unknown',
            'title' => is_string($payload['title'] ?? null) ? $payload['title'] : '',
            'message' => is_string($payload['message'] ?? null) ? $payload['message'] : '',
            'href' => is_string($payload['href'] ?? null) ? $payload['href'] : null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'data' => array_filter([
                'order_id' => $payload['order_id'] ?? null,
                'order_reference' => $payload['order_reference'] ?? null,
                'conversation_id' => $payload['conversation_id'] ?? null,
                'conversation_reference' => $payload['conversation_reference'] ?? null,
                'task_id' => $payload['task_id'] ?? null,
                'project_id' => $payload['project_id'] ?? null,
                'payment_id' => $payload['payment_id'] ?? null,
            ], fn (mixed $value) => $value !== null),
        ];
    }
}
