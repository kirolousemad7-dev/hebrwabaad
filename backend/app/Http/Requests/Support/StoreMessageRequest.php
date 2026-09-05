<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\ApiFormRequest;
use App\Services\ConversationService;

class StoreMessageRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge([
                'message' => trim($this->input('message')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.ConversationService::MESSAGE_MAX_LENGTH],
        ];
    }
}
