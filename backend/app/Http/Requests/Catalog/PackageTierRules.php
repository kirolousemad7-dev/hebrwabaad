<?php

namespace App\Http\Requests\Catalog;

/**
 * Shared tier validation so the store and update requests cannot drift apart.
 */
class PackageTierRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'tiers' => ['sometimes', 'array', 'max:10'],
            'tiers.*.name' => ['required', 'string', 'max:120'],
            'tiers.*.slug' => ['required', 'string', 'max:32', 'distinct', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'tiers.*.description' => ['nullable', 'string', 'max:2000'],
            'tiers.*.price' => ['nullable', 'numeric', 'min:0'],
            'tiers.*.currency' => ['nullable', 'string', 'size:3'],
            'tiers.*.duration_days' => ['nullable', 'integer', 'min:0'],
            'tiers.*.revision_rounds' => ['nullable', 'integer', 'min:0', 'max:50'],
            'tiers.*.deliverables' => ['nullable', 'array', 'max:40'],
            'tiers.*.deliverables.*' => ['string', 'max:255'],
            'tiers.*.is_active' => ['nullable', 'boolean'],
            'tiers.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
