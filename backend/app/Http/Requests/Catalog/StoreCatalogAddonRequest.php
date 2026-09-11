<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CatalogPricingMode;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogAddonRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:catalog_addons,slug'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pricing_mode' => ['nullable', Rule::in(CatalogPricingMode::values())],
            'price' => ['nullable', 'numeric', 'min:0'],
            'percentage_bps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'min_qty' => ['nullable', 'integer', 'min:1'],
            'max_qty' => ['nullable', 'integer', 'min:1'],
            'is_urgent' => ['nullable', 'boolean'],
            'requires_capacity' => ['nullable', 'boolean'],
            'capacity_available' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', 'exists:packages,id'],
        ];
    }
}
