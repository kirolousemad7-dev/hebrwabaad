<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\ApiFormRequest;
use App\Models\EventRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequestRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['sometimes', 'required', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'city' => ['nullable', 'string', 'max:255'],
            'attendance' => ['nullable', 'integer', 'min:1'],
            'venue' => ['nullable', 'string', 'max:255'],
            'budget_range' => ['nullable', 'string', 'max:255'],
            'buy_or_rent' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([
                EventRequest::STATUS_PENDING,
                EventRequest::STATUS_IN_REVIEW,
                EventRequest::STATUS_CONVERTED,
            ])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ];
    }
}
