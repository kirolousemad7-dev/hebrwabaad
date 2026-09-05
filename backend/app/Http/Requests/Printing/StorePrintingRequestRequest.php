<?php

namespace App\Http\Requests\Printing;

use App\Enums\PrintingDimensionUnit;
use App\Enums\PrintingFinishing;
use App\Enums\PrintingMethod;
use App\Enums\PrintingShape;
use App\Http\Requests\ApiFormRequest;
use App\Support\PrintingCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StorePrintingRequestRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $finishing = $this->input('finishing');

        if (is_string($finishing) && $finishing !== '') {
            $decoded = json_decode($finishing, true);

            if (is_array($decoded)) {
                $this->merge(['finishing' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_slug' => ['required', 'string', 'max:80', Rule::in(PrintingCatalog::slugs())],
            'product_name' => ['required', 'string', 'max:255'],
            'width' => ['required', 'numeric', 'min:0.1', 'max:10000'],
            'height' => ['required', 'numeric', 'min:0.1', 'max:10000'],
            'dimension_unit' => ['required', Rule::in(PrintingDimensionUnit::values())],
            'shape' => ['required', Rule::in(PrintingShape::values())],
            'material' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'printing_method' => ['required', Rule::in(PrintingMethod::values())],
            'finishing' => ['required', 'array', 'min:1'],
            'finishing.*' => ['required', 'string', Rule::in(PrintingFinishing::values())],
            'file' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp', 'svg', 'zip'])
                    ->max(10 * 1024),
            ],
            'required_date' => ['required', 'date', 'after_or_equal:'.now('Asia/Riyadh')->toDateString()],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A design file is required.',
            'file.uploaded' => 'The design file could not be uploaded. Try a smaller PDF or image.',
            'finishing.required' => 'Select at least one finishing option.',
            'finishing.array' => 'Select at least one finishing option.',
        ];
    }
}
