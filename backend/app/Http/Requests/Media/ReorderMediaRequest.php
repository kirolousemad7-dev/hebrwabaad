<?php

namespace App\Http\Requests\Media;

use App\Services\Media\MediaEntityRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var MediaEntityRegistry $registry */
        $registry = app(MediaEntityRegistry::class);

        return [
            'entity_type' => ['required', 'string', Rule::in($registry->keys())],
            'entity_id' => ['required', 'integer', 'min:1'],
            'collection' => ['nullable', 'string', 'max:64'],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'min:1'],
        ];
    }
}
