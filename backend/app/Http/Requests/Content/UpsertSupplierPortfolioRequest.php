<?php

namespace App\Http\Requests\Content;

use App\Enums\SupplierVisibility;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpsertSupplierPortfolioRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:2048'],
            'gallery' => ['nullable', 'array', 'max:12'],
            'gallery.*' => ['string', 'max:2048'],
            'videos' => ['nullable', 'array', 'max:12'],
            'videos.*' => ['string', 'max:2048'],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['string', 'max:2048'],
            'category' => ['nullable', 'string', 'max:120'],
            'client_type' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:16'],
            'tags.*' => ['string', 'max:40'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'completion_date' => ['nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', 'string', Rule::enum(SupplierVisibility::class)],
        ];
    }
}
