<?php

namespace App\Http\Resources;

use App\Enums\ConversationStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    public function __construct($resource, private readonly bool $forCustomer = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof ConversationStatus
            ? $this->status
            : ConversationStatus::from((string) $this->status);

        $latest = $this->whenLoaded('latestMessage', fn () => $this->latestMessage);

        $payload = [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'status' => $status->value,
            'status_label' => $status->label(),
            'can_reply' => $status->allowsMessages(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_message' => $latest ? [
                'id' => $latest->id,
                'body' => $latest->body,
                'created_at' => $latest->created_at?->toIso8601String(),
                'from_support' => $latest->sender?->role instanceof UserRole
                    ? $latest->sender->role !== UserRole::Customer
                    : false,
            ] : null,
            'order' => $this->whenLoaded('order', fn () => $this->order === null ? null : [
                'id' => $this->order->id,
                'reference' => $this->order->reference,
                'title' => $this->order->title,
            ]),
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->id,
                'title' => $this->project->title,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
        ];

        if (! $this->forCustomer) {
            $payload['allowed_transitions'] = array_map(
                fn (ConversationStatus $next) => [
                    'status' => $next->value,
                    'label' => $next->label(),
                ],
                $status->allowedTransitions(),
            );
            $payload['customer'] = $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ]);
        }

        return $payload;
    }
}
