<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerCustomPackageOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'integer', 'distinct', Rule::exists('services', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.addon_slugs' => ['sometimes', 'array'],
            'items.*.addon_slugs.*' => ['string', 'max:120', Rule::exists('catalog_addons', 'slug')],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'package_addon_slugs' => ['sometimes', 'array'],
            'package_addon_slugs.*' => ['string', 'max:120', Rule::exists('catalog_addons', 'slug')],
        ];
    }
}
