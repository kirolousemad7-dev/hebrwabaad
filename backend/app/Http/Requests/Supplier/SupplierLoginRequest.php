<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\ApiFormRequest;

class SupplierLoginRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
