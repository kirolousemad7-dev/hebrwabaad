<?php

namespace App\Http\Requests\NeedsDiscovery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreNeedsDiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $answers = $this->input('answers');
        if (is_string($answers)) {
            $decoded = json_decode($answers, true);
            if (is_array($decoded)) {
                $this->merge(['answers' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'answers' => ['required', 'array'],
            'answers.need' => ['nullable'],
            'answers.project_type' => ['nullable'],
            'answers.service' => ['nullable'],
            'answers.budget' => ['nullable'],
            'answers.deadline' => ['nullable'],
            'answers.has_files' => ['nullable'],
            'answers.contact' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv'])->max(10240),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $phone = $this->input('phone');
            $email = $this->input('email');
            if ((! $phone || trim((string) $phone) === '') && (! $email || trim((string) $email) === '')) {
                $validator->errors()->add('phone', 'Phone or email is required.');
            }
        });
    }
}
