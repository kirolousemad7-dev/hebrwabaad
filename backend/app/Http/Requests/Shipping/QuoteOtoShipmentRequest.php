<?php

namespace App\Http\Requests\Shipping;

use Illuminate\Foundation\Http\FormRequest;

class QuoteOtoShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:120'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'size:2'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
        ];
    }
}
