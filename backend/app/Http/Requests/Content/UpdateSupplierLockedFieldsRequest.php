<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;
use App\Services\SupplierContentService;

class UpdateSupplierLockedFieldsRequest extends ApiFormRequest
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
            'locked_fields' => ['required', 'array'],
            'locked_fields.*' => ['string', 'in:'.implode(',', SupplierContentService::PROFILE_FIELDS)],
        ];
    }
}
