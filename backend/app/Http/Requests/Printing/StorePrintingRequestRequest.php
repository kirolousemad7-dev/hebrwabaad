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
            'file.required' => 'أرفق ملف التصميم.',
            'file.uploaded' => 'تعذر رفع ملف التصميم. تحقق من إعدادات الرفع على الخادم أو حاول مرة أخرى.',
            'file.max' => 'حجم الملف يتجاوز 10 ميجابايت.',
            'file.extensions' => 'نوع الملف غير مدعوم. استخدم PDF أو صورة أو ZIP.',
            'file.mimes' => 'نوع الملف غير مدعوم. استخدم PDF أو صورة أو ZIP.',
            'finishing.required' => 'اختر تشطيباً واحداً على الأقل.',
            'finishing.array' => 'اختر تشطيباً واحداً على الأقل.',
            'finishing.min' => 'اختر تشطيباً واحداً على الأقل.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'ملف التصميم',
            'finishing' => 'التشطيب',
            'product_slug' => 'المنتج',
            'width' => 'العرض',
            'height' => 'الارتفاع',
            'quantity' => 'الكمية',
            'required_date' => 'تاريخ التسليم',
        ];
    }
}
