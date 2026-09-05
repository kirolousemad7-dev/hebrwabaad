<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CatalogPricingMode;
use App\Enums\PackageCategory;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $package = $this->route('package');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('packages', 'slug')->ignore($package?->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'audience' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'deliverables' => ['sometimes', 'nullable', 'array', 'max:40'],
            'deliverables.*' => ['string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::in(PackageCategory::values())],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'lte:price'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'pricing_mode' => ['sometimes', Rule::in(CatalogPricingMode::values())],
            'duration_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'revision_rounds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.service_id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            ...PackageTierRules::rules(),
        ];
    }

    /**
     * The discount can only be compared against the stored price when the
     * request does not send a new one. A quote-mode package sends no price,
     * so the comparison falls back to zero.
     */
    protected function prepareForValidation(): void
    {
        $package = $this->route('package');

        if ($package !== null && ! $this->has('price') && $this->has('discount_amount')) {
            $this->merge(['price' => $package->price]);
        }

        if ($this->has('price') && $this->input('price') === null) {
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
