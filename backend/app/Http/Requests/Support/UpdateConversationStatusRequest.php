<?php

namespace App\Http\Requests\Support;

use App\Enums\ConversationStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationStatusRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(ConversationStatus::class)],
        ];
    }
}
