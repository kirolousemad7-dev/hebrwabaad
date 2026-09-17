<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends ApiFormRequest
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
        $tagId = $this->route('tag')?->id ?? $this->route('tag');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('tags', 'slug')->ignore($tagId)],
            'scope' => ['sometimes', 'string', 'in:supplier,service,product,portfolio,project,task,shared'],
            'color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
