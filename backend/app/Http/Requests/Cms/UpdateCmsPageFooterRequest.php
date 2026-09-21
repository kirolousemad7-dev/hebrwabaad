<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\ApiFormRequest;

class UpdateCmsPageFooterRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'show_in_footer' => ['required', 'boolean'],
            'footer_group' => ['nullable', 'string', 'max:100'],
            'footer_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
