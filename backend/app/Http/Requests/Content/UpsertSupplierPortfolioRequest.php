<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;

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
            'image' => ['required', 'string', 'max:2048'],
            'gallery' => ['nullable', 'array', 'max:12'],
            'gallery.*' => ['string', 'max:2048'],
            'category' => ['required', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:16'],
            'tags.*' => ['string', 'max:40'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
