<?php

namespace App\Http\Requests\Content;

use App\Enums\SupplierVisibility;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierDocumentRequest extends ApiFormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'disk' => ['nullable', 'string', 'max:40'],
            'path' => ['required', 'string', 'max:2048'],
            'original_name' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:120'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
            'visibility' => ['sometimes', 'string', Rule::enum(SupplierVisibility::class)],
            'metadata' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
