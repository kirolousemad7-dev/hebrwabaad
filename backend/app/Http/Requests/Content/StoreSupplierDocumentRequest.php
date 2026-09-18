<?php

namespace App\Http\Requests\Content;

use App\Enums\SupplierVisibility;
use App\Enums\UserRole;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplierDocumentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->canReviewContent() || $user->role === UserRole::Supplier);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'visibility' => ['sometimes', 'string', Rule::in(SupplierVisibility::documentValues())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'disk' => ['prohibited'],
            'path' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $visibility = $this->input('visibility');

            if ($user === null || $visibility === null) {
                return;
            }

            // Suppliers may not publish documents as public without staff review.
            if ($user->role === UserRole::Supplier && $visibility === SupplierVisibility::Public->value) {
                $validator->errors()->add('visibility', 'Suppliers cannot mark documents as public.');
            }
        });
    }
}
