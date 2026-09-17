<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterSupplierRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'password' => ['required_without:passwordless', 'nullable', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required_with:password'],
            'passwordless' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:supplier_categories,id'],
            'services' => ['nullable', 'array', 'max:30'],
            'services.*' => ['string', 'max:80'],
            'short_description' => ['required', 'string', 'max:500'],
        ];
    }
}
