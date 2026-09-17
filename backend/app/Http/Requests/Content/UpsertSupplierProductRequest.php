<?php

namespace App\Http\Requests\Content;

use App\Enums\SupplierVisibility;
use App\Http\Requests\ApiFormRequest;
use App\Support\SeoPages;
use Illuminate\Validation\Rule;

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
            'sku' => ['nullable', 'string', 'max:64'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:8000'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['string', 'max:2048'],
            'category' => ['nullable', 'string', 'max:120'],
            'specifications' => ['nullable', 'array'],
            'variants' => ['nullable', 'array'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'unit' => ['nullable', 'string', 'max:40'],
            'minimum_quantity' => ['nullable', 'integer', 'min:0'],
            'contact_for_price' => ['sometimes', 'boolean'],
            'availability' => ['nullable', 'string', 'in:IN_STOCK,MADE_TO_ORDER,UNAVAILABLE,CONTACT'],
            'lead_time' => ['nullable', 'string', 'max:120'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'visibility' => ['sometimes', 'string', Rule::enum(SupplierVisibility::class)],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
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
