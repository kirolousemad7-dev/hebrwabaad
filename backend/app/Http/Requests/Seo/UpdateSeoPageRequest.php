<?php

namespace App\Http\Requests\Seo;

use App\Http\Requests\ApiFormRequest;
use App\Support\SeoPages;
use Illuminate\Validation\Rule;

class UpdateSeoPageRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:70'],
            'description' => ['nullable', 'string', 'max:320'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/i'],
            'og_title' => ['nullable', 'string', 'max:70'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/i'],
            'twitter_title' => ['nullable', 'string', 'max:70'],
            'twitter_description' => ['nullable', 'string', 'max:320'],
            'twitter_image' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/i'],
            'robots' => ['nullable', 'string', Rule::in(SeoPages::robotsOptions())],
            'schema_json' => ['nullable', 'array'],
        ];
    }
}
