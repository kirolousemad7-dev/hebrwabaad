<?php

namespace App\Http\Requests\Content;

use App\Enums\SupplierVerificationStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class TransitionSupplierStatusRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canReviewContent() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'verification_status' => ['nullable', 'string', Rule::enum(SupplierVerificationStatus::class)],
        ];
    }
}
