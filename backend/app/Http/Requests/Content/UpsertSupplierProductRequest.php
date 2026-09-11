<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;
use App\Support\SeoPages;

class UpsertSupplierProductRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:8000'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['string', 'max:2048'],
            'category' => ['nullable', 'string', 'max:120'],
            'specifications' => ['nullable', 'array'],
            'variants' => ['nullable', 'array'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'contact_for_price' => ['sometimes', 'boolean'],
            'availability' => ['nullable', 'string', 'in:IN_STOCK,MADE_TO_ORDER,UNAVAILABLE,CONTACT'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'og_title' => ['nullable', 'string', 'max:70'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'canonical_url' => ['nullable', 'string', 'max:2048'],
            'robots' => ['nullable', 'string', 'in:'.implode(',', SeoPages::robotsOptions())],
        ];
    }
}
