<?php

namespace App\Services\Consultant;

class ConversationExtractor
{
    /**
     * Deterministic extraction from free text. Never invents catalog items.
     *
     * @return array<string, mixed>
     */
    public function extract(string $message): array
    {
        $text = mb_strtolower(trim($message));
        $extracted = [];

        $category = $this->firstMatch($text, [
            'restaurants-food' => ['مطعم', 'فراخ', 'دجاج', 'برجر', 'كافيه', 'قهوة', 'مخبز', 'حلويات', 'restaurant', 'chicken', 'cafe', 'bakery', 'food truck', 'كاترينج'],
            'ecommerce' => ['متجر إلكتروني', 'متجر الكتروني', 'إيكميرس', 'ecommerce', 'e-commerce', 'أونلاين شوب'],
            'events' => ['فعالية', 'حفل', 'زفاف', 'مؤتمر', 'معرض', 'wedding', 'event', 'تخرج', 'عيد ميلاد'],
            'real-estate' => ['عقار', 'عقارات', 'وسيط عقاري', 'مطوّر', 'مطور عقاري', 'real estate'],
            'medical-healthcare' => ['عيادة', 'أسنان', 'صيدلية', 'معمل', 'clinic', 'dental', 'pharmacy'],
            'printing-packaging' => ['طباعة', 'تغليف', 'كروت', 'فلاير', 'printing', 'packaging'],
            'technology' => ['شركة برمجيات', 'saas', 'تطبيق', 'software', 'startup تقنية'],
            'retail' => ['محل ملابس', 'بوتيك', 'سوبرماركت', 'تجزئة'],
            'education' => ['مدرسة', 'أكاديمية', 'دورة', 'تدريب', 'جامعة'],
            'beauty-personal-care' => ['صالون', 'سبا', 'حلاقة', 'تجميل'],
            'fitness-sports' => ['جيم', 'نادي رياضي', 'مدرب'],
            'automotive' => ['سيارات', 'معرض سيارات', 'غسيل سيارات'],
            'hospitality-tourism' => ['فندق', 'منتجع', 'سياحة', 'سفر'],
        ]);

        if ($category) {
            $extracted['business_category'] = $category;
        }

        $subtype = $this->firstMatch($text, [
            'chicken-restaurant' => ['فراخ', 'دجاج', 'chicken'],
            'meat-restaurant' => ['لحوم', 'مشويات', 'meat'],
            'seafood-restaurant' => ['بحري', 'أسماك', 'seafood'],
            'fast-food' => ['وجبات سريعة', 'fast food', 'برجر'],
            'cafe' => ['كافيه', 'قهوة', 'cafe'],
            'wedding' => ['زفاف', 'wedding'],
            'conference' => ['مؤتمر', 'conference'],
            'clinic' => ['عيادة', 'clinic'],
            'dental-clinic' => ['أسنان', 'dental'],
            'pharmacy' => ['صيدلية', 'pharmacy'],
            'general-ecommerce' => ['متجر إلكتروني', 'ecommerce'],
        ]);

        if ($subtype) {
            $extracted['business_subtype'] = $subtype;
        }

        if (preg_match('/فرع(?:ين)?|فرعين|branches?/u', $text)) {
            if (preg_match('/(\d+)\s*(?:فرع|branches?)/u', $text, $match)) {
                $extracted['branches'] = $match[1];
            } elseif (str_contains($text, 'فرعين') || str_contains($text, 'فرعين')) {
                $extracted['branches'] = '2';
            } elseif (str_contains($text, 'فرع واحد') || str_contains($text, 'فرعي')) {
                $extracted['branches'] = '1';
            }
        }

        foreach (['سموحة', 'الإسكندرية', 'الاسكندرية', 'القاهرة', 'جدة', 'الرياض', 'الدمام', 'المعادي', 'مدينة نصر'] as $place) {
            if (str_contains($text, mb_strtolower($place))) {
                $extracted['location'] = $place;
                break;
            }
        }

        $goals = [];
        if ($this->containsAny($text, ['زيادة المبيعات', 'نزود الطلبات', 'زيادة الطلبات', 'increase sales', 'orders'])) {
            $goals[] = 'increase_sales';
        }
        if ($this->containsAny($text, ['عملاء أكثر', 'عملاء جدد', 'more customers'])) {
            $goals[] = 'more_customers';
        }
        if ($this->containsAny($text, ['وعي', 'awareness', 'براند'])) {
            $goals[] = 'brand_awareness';
        }
        if ($this->containsAny($text, ['موقع', 'website', 'ويب سايت'])) {
            $goals[] = 'build_website';
        }
        if ($this->containsAny($text, ['متجر إلكتروني', 'ecommerce', 'أونلاين ستور'])) {
            $goals[] = 'build_ecommerce';
        }
        if ($this->containsAny($text, ['إعلان', 'ads', 'إعلانات'])) {
            $goals[] = 'run_ads';
        }
        if ($this->containsAny($text, ['فعالية', 'event', 'حفل'])) {
            $goals[] = 'prepare_event';
        }
        if ($this->containsAny($text, ['طباعة', 'printing'])) {
            $goals[] = 'print_materials';
        }
        if ($this->containsAny($text, ['تغليف', 'packaging'])) {
            $goals[] = 'improve_packaging';
        }
        if ($goals !== []) {
            $extracted['goals'] = $goals;
        }

        $needs = [];
        if ($this->containsAny($text, ['سوشيال', 'انستجرام', 'instagram', 'تيك توك'])) {
            $needs[] = 'social_media';
        }
        if ($this->containsAny($text, ['هوية', 'branding', 'شعار'])) {
            $needs[] = 'branding';
        }
        if ($this->containsAny($text, ['متجر', 'ecommerce'])) {
            $needs[] = 'ecommerce';
        }
        if ($this->containsAny($text, ['طباعة', 'فلاير', 'كروت'])) {
            $needs[] = 'printing';
        }
        if ($this->containsAny($text, ['فيديو', 'video'])) {
            $needs[] = 'video';
        }
        if ($needs !== []) {
            $extracted['needed_services'] = $needs;
        }

        if ($this->containsAny($text, ['delivery', 'توصيل', 'دليفري'])) {
            $extracted['channel'] = 'delivery';
            $extracted['answers'] = ['dine_channels' => ['delivery']];
        }

        if ($this->containsAny($text, ['لست متأكد', 'مش عارف', 'لا أعرف', 'not sure', "don't know"])) {
            $extracted['help_mode'] = 'unsure';
            $extracted['unsure_needs'] = true;
        }

        return $extracted;
    }

    /**
     * @param  array<string, list<string>>  $map
     */
    private function firstMatch(string $text, array $map): ?string
    {
        foreach ($map as $id => $needles) {
            if ($this->containsAny($text, $needles)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
