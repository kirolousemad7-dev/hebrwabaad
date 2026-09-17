<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;

class StoreSupplierContactRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->canReviewContent() || $user->role?->value === 'SUPPLIER');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
