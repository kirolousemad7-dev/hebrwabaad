<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;

class ReviewNotesRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:2000'],
        ];
    }
}
