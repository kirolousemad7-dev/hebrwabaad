<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CatalogPricingMode;
use App\Enums\PackageCategory;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StorePackageRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:packages,slug'],
            'description' => ['nullable', 'string'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'deliverables' => ['nullable', 'array', 'max:40'],
            'deliverables.*' => ['string', 'max:255'],
            'category' => ['required', Rule::in(PackageCategory::values())],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'currency' => ['nullable', 'string', 'size:3'],
            'pricing_mode' => ['nullable', Rule::in(CatalogPricingMode::values())],
            'duration_days' => ['nullable', 'integer', 'min:0'],
            'revision_rounds' => ['nullable', 'integer', 'min:0', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.service_id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            ...PackageTierRules::rules(),
        ];
    }

    /**
     * A quote-mode package has no price yet, so the discount comparison runs
     * against zero instead of failing on a null price.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('price') === null) {
            $this->merge(['price' => 0]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discount_amount.lte' => 'The discount amount must not be greater than the price.',
            'items.*.service_id.distinct' => 'A service can only be added once per package.',
            'tiers.*.slug.distinct' => 'A tier identifier can only be used once per package.',
        ];
    }
}
