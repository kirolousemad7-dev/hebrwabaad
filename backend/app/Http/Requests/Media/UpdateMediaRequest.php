<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaVisibility;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateMediaRequest extends FormRequest
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
        return [
            'file' => [
                'nullable',
                File::types(MediaService::ALLOWED_EXTENSIONS)->max(MediaService::MAX_KILOBYTES),
            ],
            'visibility' => ['nullable', 'string', Rule::in(MediaVisibility::values())],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
