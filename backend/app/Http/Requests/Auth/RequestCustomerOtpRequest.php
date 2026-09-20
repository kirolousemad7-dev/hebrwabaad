<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class RequestCustomerOtpRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}
