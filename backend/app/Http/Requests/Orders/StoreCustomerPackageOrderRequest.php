<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\ApiFormRequest;

class StoreCustomerPackageOrderRequest extends ApiFormRequest
{
    /**
     * Only catalog identifiers are accepted. Price, currency, customer and
     * status are always resolved server-side.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'package_slug' => ['required', 'string', 'max:160'],
            'package_tier_slug' => ['nullable', 'string', 'max:32'],
        ];
    }
}
