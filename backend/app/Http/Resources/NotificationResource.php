<?php

namespace App\Http\Resources;

use App\Services\Notifications\NotificationCategorizer;
use App\Services\Notifications\NotificationDeepLinkResolver;
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
        $categorizer = app(NotificationCategorizer::class);
        $deepLinks = app(NotificationDeepLinkResolver::class);
        $viewer = $request->user();
        $category = $categorizer->categorizeValue($this->resource);
        $href = $this->resource instanceof DatabaseNotification
            ? $deepLinks->resolve($this->resource, $viewer)
            : (is_string($payload['href'] ?? null) ? $payload['href'] : null);

        return [
            'id' => $this->id,
            'type' => is_string($payload['type'] ?? null) ? $payload['type'] : 'unknown',
            'title' => is_string($payload['title'] ?? null) ? $payload['title'] : '',
            'message' => is_string($payload['message'] ?? null)
                ? $payload['message']
                : (is_string($payload['body'] ?? null) ? $payload['body'] : ''),
            'href' => $href,
            'category' => $category,
            'is_group' => false,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'data' => array_filter([
                'order_id' => $payload['order_id'] ?? null,
                'order_reference' => $payload['order_reference'] ?? null,
                'conversation_id' => $payload['conversation_id'] ?? null,
                'conversation_reference' => $payload['conversation_reference'] ?? null,
                'review_notes' => $payload['review_notes'] ?? null,
                'work_submission_id' => $payload['work_submission_id'] ?? null,
                'supplier_id' => $payload['supplier_id'] ?? null,
                'project_id' => $payload['project_id'] ?? null,
                'payment_id' => $payload['payment_id'] ?? null,
                'calendar_item_id' => $payload['calendar_item_id'] ?? null,
                'lead_id' => $payload['lead_id'] ?? null,
                'quotation_id' => $payload['quotation_id'] ?? null,
                'task_id' => $payload['task_id'] ?? null,
            ], fn (mixed $value) => $value !== null),
        ];
    }
}
