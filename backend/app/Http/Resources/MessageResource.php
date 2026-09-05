<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->sender?->role;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'from_support' => $role instanceof UserRole && $role !== UserRole::Customer,
            'sender' => $this->sender === null ? null : [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
