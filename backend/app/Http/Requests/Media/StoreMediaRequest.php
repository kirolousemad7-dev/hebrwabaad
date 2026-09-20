<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaVisibility;
use App\Services\Media\MediaEntityRegistry;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreMediaRequest extends FormRequest
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
            'file' => [
                'required',
                File::types(MediaService::ALLOWED_EXTENSIONS)->max(MediaService::MAX_KILOBYTES),
            ],
            'entity_type' => ['required', 'string', Rule::in($registry->keys())],
            'entity_id' => ['required', 'integer', 'min:1'],
            'visibility' => ['nullable', 'string', Rule::in(MediaVisibility::values())],
            'collection' => ['nullable', 'string', 'max:64'],
            'is_primary' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
