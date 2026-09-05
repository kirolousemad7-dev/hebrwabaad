<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\ApiFormRequest;
use App\Services\ConversationService;

class StoreConversationRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [];

        if (is_string($this->input('subject'))) {
            $payload['subject'] = trim($this->input('subject'));
        }

        if (is_string($this->input('message'))) {
            $payload['message'] = trim($this->input('message'));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:'.ConversationService::MESSAGE_MAX_LENGTH],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $subject = trim((string) $this->input('subject', ''));
            $message = trim(strip_tags((string) $this->input('message', '')));
            $hasContext = $this->filled('order_id') || $this->filled('project_id');

            if (! $hasContext && $subject === '') {
                $validator->errors()->add('subject', 'Subject is required.');
            }

            if (! $hasContext && $message === '') {
                $validator->errors()->add('message', 'Message is required.');
            }
        });
    }
}
