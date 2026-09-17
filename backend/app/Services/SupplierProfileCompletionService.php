<?php

namespace App\Services;

use App\Enums\SupplierVerificationStatus;
use App\Models\Supplier;

class SupplierProfileCompletionService
{
    /**
     * @return array{
     *     percent: int,
     *     sections: array<string, array{complete: bool, missing: list<string>}>,
     *     missing: list<string>
     * }
     */
    public function summarize(Supplier $supplier): array
    {
        $supplier->loadMissing(['contacts', 'categories', 'offeredServices', 'products', 'portfolioItems', 'documents']);

        $sections = [
            'company' => $this->section([
                'name' => filled($supplier->name),
                'short_description' => filled($supplier->short_description),
                'logo' => filled($supplier->logo),
                'country' => filled($supplier->country),
                'city' => filled($supplier->city),
            ], [
                'name' => 'اسم الشركة',
                'short_description' => 'وصف قصير',
                'logo' => 'الشعار',
                'country' => 'الدولة',
                'city' => 'المدينة',
            ]),
            'contact' => $this->section([
                'email' => filled($supplier->email),
                'phone' => filled($supplier->phone),
                'contact_person' => filled($supplier->contact_person) || $supplier->contacts->isNotEmpty(),
            ], [
                'email' => 'البريد',
                'phone' => 'الهاتف',
                'contact_person' => 'جهة اتصال',
            ]),
            'categories' => $this->section([
                'categories' => $supplier->categories->isNotEmpty() || filled($supplier->category),
            ], [
                'categories' => 'تصنيف واحد على الأقل',
            ]),
            'services' => $this->section([
                'services' => $supplier->offeredServices->isNotEmpty() || ! empty($supplier->services),
            ], [
                'services' => 'خدمة واحدة على الأقل',
            ]),
            'products' => $this->section([
                'products' => $supplier->products->isNotEmpty(),
            ], [
                'products' => 'منتج واحد على الأقل',
            ]),
            'portfolio' => $this->section([
                'portfolio' => $supplier->portfolioItems->isNotEmpty(),
            ], [
                'portfolio' => 'عنصر معرض واحد على الأقل',
            ]),
            'documents' => $this->section([
                'documents' => $supplier->documents->isNotEmpty(),
            ], [
                'documents' => 'مستند واحد على الأقل',
            ]),
            'verification' => $this->section([
                'verification' => $supplier->verification_status !== SupplierVerificationStatus::Unverified,
            ], [
                'verification' => 'التحقق من الحساب',
            ]),
        ];

        $completeCount = count(array_filter($sections, fn (array $section): bool => $section['complete']));
        $percent = (int) round(($completeCount / max(1, count($sections))) * 100);
        $missing = [];
        foreach ($sections as $section) {
            foreach ($section['missing'] as $item) {
                $missing[] = $item;
            }
        }

        return [
            'percent' => $percent,
            'sections' => $sections,
            'missing' => $missing,
        ];
    }

    /**
     * @param  array<string, bool>  $checks
     * @param  array<string, string>  $labels
     * @return array{complete: bool, missing: list<string>}
     */
    private function section(array $checks, array $labels): array
    {
        $missing = [];
        foreach ($checks as $key => $ok) {
            if (! $ok) {
                $missing[] = $labels[$key] ?? $key;
            }
        }

        return [
            'complete' => $missing === [],
            'missing' => $missing,
        ];
    }
}
