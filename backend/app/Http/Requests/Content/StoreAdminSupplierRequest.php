<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;

class StoreAdminSupplierRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:suppliers,slug'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'short_description' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:8000'],
            'specialties' => ['nullable', 'array'],
            'specialties.*' => ['string', 'max:80'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:80'],
            'location' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:2048'],
            'category' => ['nullable', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'account_password' => ['required_with:account_email', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
