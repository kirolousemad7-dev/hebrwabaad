<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;

class StoreTagRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canReviewContent() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:tags,slug'],
            'color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
