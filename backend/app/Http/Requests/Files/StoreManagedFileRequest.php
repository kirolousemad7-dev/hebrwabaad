<?php

namespace App\Http\Requests\Files;

use App\Http\Requests\ApiFormRequest;
use App\Services\FileService;
use Illuminate\Validation\Rules\File;

class StoreManagedFileRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(FileService::ALLOWED_EXTENSIONS)
                    ->max(FileService::MAX_KILOBYTES),
            ],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.uploaded' => 'The file could not be uploaded. Try a smaller document or image.',
        ];
    }
}
