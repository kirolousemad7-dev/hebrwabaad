<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;
use App\Support\SeoPages;

class UpsertSupplierProfileRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'logo' => ['sometimes', 'string', 'max:2048'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'short_description' => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:8000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:2048'],
            'location' => ['sometimes', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'services' => ['nullable', 'array', 'max:24'],
            'services.*' => ['string', 'max:80'],
            'specialties' => ['nullable', 'array', 'max:24'],
            'specialties.*' => ['string', 'max:80'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'min_order_info' => ['nullable', 'string', 'max:255'],
            'brand_colors' => ['nullable', 'array', 'max:8'],
            'brand_colors.*' => ['string', 'max:32'],
            'brand_description' => ['nullable', 'string', 'max:2000'],
            'availability' => ['nullable', 'string', 'max:40'],
            'delivery_time' => ['nullable', 'string', 'max:120'],
            'service_areas' => ['nullable', 'array', 'max:40'],
            'service_areas.*' => ['string', 'max:120'],
            'certifications' => ['nullable', 'array', 'max:40'],
            'certifications.*' => ['string', 'max:255'],
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
