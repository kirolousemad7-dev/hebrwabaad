<?php

namespace App\Http\Requests\Content;

use App\Enums\PortfolioCategory;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpsertWorkSubmissionRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(PortfolioCategory::values())],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'cover_media_id' => ['nullable', 'uuid', 'exists:content_media,id'],
            'gallery_media_ids' => ['nullable', 'array', 'max:12'],
            'gallery_media_ids.*' => ['uuid', 'exists:content_media,id'],
            'tags' => ['nullable', 'array', 'max:16'],
            'tags.*' => ['string', 'max:40'],
            'tools' => ['nullable', 'array', 'max:16'],
            'tools.*' => ['string', 'max:40'],
            'project_url' => ['nullable', 'url', 'max:2048'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'client_label' => ['nullable', 'string', 'max:120'],
            'employee_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
