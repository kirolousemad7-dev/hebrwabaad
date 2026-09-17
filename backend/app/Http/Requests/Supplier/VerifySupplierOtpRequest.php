<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\ApiFormRequest;

class VerifySupplierOtpRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
