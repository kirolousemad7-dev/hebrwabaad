<?php

namespace App\Http\Requests\Printing;

use App\Http\Requests\ApiFormRequest;

class RequestPrintingQuoteRequest extends ApiFormRequest
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
            'pricing_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
