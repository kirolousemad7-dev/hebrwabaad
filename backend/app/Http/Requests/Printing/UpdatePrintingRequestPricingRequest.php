<?php

namespace App\Http\Requests\Printing;

use App\Http\Requests\ApiFormRequest;

class UpdatePrintingRequestPricingRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role->canReviewPrintingRequests();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estimated_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'pricing_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
