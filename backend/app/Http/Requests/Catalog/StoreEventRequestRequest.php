<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\ApiFormRequest;

class StoreEventRequestRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'city' => ['nullable', 'string', 'max:255'],
            'attendance' => ['nullable', 'integer', 'min:1'],
            'venue' => ['nullable', 'string', 'max:255'],
            'budget_range' => ['nullable', 'string', 'max:255'],
            'buy_or_rent' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'create_project' => ['nullable', 'boolean'],
        ];
    }
}
