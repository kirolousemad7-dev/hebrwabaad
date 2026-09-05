<?php

namespace App\Services\Consultant;

use App\Support\Consultant\BusinessCatalog;
use App\Support\Consultant\ConsultationState;
use App\Support\PrintingCatalog;

class RecommendationEngine
{
    public function __construct(
        private readonly RecommendationValidator $validator,
    ) {}

    /**
     * @param  array{packages: list<array<string, mixed>>, services: list<array<string, mixed>>, printing_slugs: list<string>}  $catalog
     * @return array<string, mixed>
     */
    public function recommend(ConsultationState $state, array $catalog): array
    {
        $intent = $this->intent($state);
        $budgetMax = $this->budgetMax($state);
        $scored = [];

        foreach ($catalog['packages'] as $package) {
            if (! ($package['is_active'] ?? false)) {
                continue;
            }

            $score = $this->scorePackage($package, $intent, $state);
            if ($score <= 0) {
                continue;
            }

            $fitsBudget = $budgetMax === null || (float) $package['final_price'] <= $budgetMax;
            $scored[] = [
                'package' => $package,
                'score' => $score,
                'fits_budget' => $fitsBudget,
            ];
        }

        usort($scored, function (array $a, array $b): int {
            if ($a['fits_budget'] !== $b['fits_budget']) {
                return $a['fits_budget'] ? -1 : 1;
            }

            return $b['score'] <=> $a['score'];
        });

        $inBudget = array_values(array_filter($scored, fn (array $row) => $row['fits_budget']));
        $bestRow = $inBudget[0] ?? null;
        $altRow = $inBudget[1] ?? null;

        if ($bestRow && ! $this->shouldRecommendPackage($intent, $bestRow['score'])) {
            $bestRow = null;
            $altRow = null;
        }

        if ($this->isSimpleNeed($state, $intent) && $bestRow && ($bestRow['package']['slug'] ?? null) === 'digital-marketing-package') {
            $foundation = $this->findScored($inBudget, 'foundation-package');
            if ($foundation) {
                $altRow = $bestRow;
                $bestRow = $foundation;
            }
        }

        $services = $this->matchingServices($state, $intent, $catalog['services'], $budgetMax);
        $printing = $this->printingRecommendation($state, $intent);

        $payload = [
            'intent' => $intent,
            'best_match' => $bestRow ? $this->presentPackage($bestRow['package'], $this->reasons($bestRow['package'], $intent, $state, 'best')) : null,
            'alternative' => $altRow ? $this->presentPackage($altRow['package'], $this->reasons($altRow['package'], $intent, $state, 'alt')) : null,
            'services' => $services,
            'printing' => $printing,
            'cta' => $this->cta($intent, $bestRow['package'] ?? null, $services, $printing, $state),
            'fallback' => null,
        ];

        if ($payload['best_match'] === null && $services === [] && $printing === null) {
            $payload['fallback'] = [
                'title' => 'احتياج مخصص',
                'message' => 'متطلباتك أوسع من الباقات الجاهزة المتاحة حالياً، أو لا توجد باقة نشطة تناسب الميزانية. يمكنك طلب عرض سعر أو التحدث مع مختص.',
            ];
            $payload['cta'] = $this->expertOrQuoteCta($state, $intent);
        }

        if ($budgetMax !== null && $scored !== [] && $inBudget === [] && $payload['best_match'] === null) {
            $payload['fallback'] = [
                'title' => 'الميزانية أقل من الباقات الجاهزة',
                'message' => 'لا توجد باقة نشطة ضمن هذا النطاق حالياً. يمكنك اختيار خدمة منفردة إن ناسبت الميزانية، أو طلب عرض سعر مخصص.',
            ];
            if ($services === []) {
                $payload['cta'] = [
                    'type' => 'request_quote',
                    'label' => 'اطلب عرض سعر',
                    'path' => '/build-package',
                ];
            }
        }

        return $this->validator->validate($payload, $catalog);
    }

    /**
     * @return array{primary: string, flags: list<string>}
     */
    public function intent(ConsultationState $state): array
    {
        $goals = $state->goals();
        $needs = $state->neededServices();
        $category = (string) $state->get('business_category');
        $websitePurpose = $state->get('answers')['website_purpose'] ?? null;
        $flags = [];

        if ($category === 'ecommerce' || in_array('build_ecommerce', $goals, true) || in_array('ecommerce', $needs, true) || $websitePurpose === 'ecommerce') {
            return ['primary' => 'ecommerce', 'flags' => $this->extraFlags($state, 'ecommerce')];
        }

        if ($category === 'events' || in_array('prepare_event', $goals, true) || in_array('event_management', $needs, true)) {
            return ['primary' => 'event', 'flags' => $this->extraFlags($state, 'event')];
        }

        if ($category === 'printing-packaging' || in_array('printing', $needs, true) || in_array('packaging', $needs, true) || in_array('print_materials', $goals, true) || in_array('improve_packaging', $goals, true)) {
            return ['primary' => 'printing', 'flags' => $this->extraFlags($state, 'printing')];
        }

        if (in_array('build_app', $goals, true) || in_array('software', $needs, true) || in_array('web_application', $needs, true) || $websitePurpose === 'web_app' || in_array('automate', $goals, true)) {
            return ['primary' => 'custom', 'flags' => $this->extraFlags($state, 'custom')];
        }

        if (in_array('build_website', $goals, true) || in_array('website', $needs, true) || in_array($websitePurpose, ['company_site', 'landing', 'booking'], true)) {
            $flags[] = 'website';
            if ($this->isMarketingHeavy($state)) {
                return ['primary' => 'marketing', 'flags' => array_values(array_unique([...$flags, ...$this->extraFlags($state, 'marketing')]))];
            }

            return ['primary' => 'foundation', 'flags' => $flags];
        }

        if ($this->isMarketingHeavy($state) || $category === 'restaurants-food' && $state->isUnsure()) {
            return ['primary' => 'marketing', 'flags' => $this->extraFlags($state, 'marketing')];
        }

        if (in_array('launch_business', $goals, true) || in_array('rebrand', $goals, true) || in_array('branding', $needs, true)) {
            return ['primary' => 'foundation', 'flags' => $this->extraFlags($state, 'foundation')];
        }

        if ($state->isUnsure()) {
            return ['primary' => 'marketing', 'flags' => ['unsure']];
        }

        return ['primary' => 'foundation', 'flags' => $this->extraFlags($state, 'foundation')];
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  array{primary: string, flags: list<string>}  $intent
     */
    private function scorePackage(array $package, array $intent, ConsultationState $state): int
    {
        $slug = (string) $package['slug'];
        $primary = $intent['primary'];
        $score = 0;

        $map = [
            'ecommerce-launch-package' => ['ecommerce' => 100, 'marketing' => 25, 'foundation' => 10],
            'events-package' => ['event' => 100],
            'digital-marketing-package' => ['marketing' => 90, 'ecommerce' => 20, 'foundation' => 15, 'printing' => 5],
            'foundation-package' => ['foundation' => 90, 'marketing' => 35, 'ecommerce' => 15, 'custom' => 20],
        ];

        $score += $map[$slug][$primary] ?? 0;

        if ($score === 0 && $primary === 'custom') {
            return 0;
        }

        if ($primary === 'printing' && $slug !== 'events-package') {
            return $slug === 'foundation-package' ? 20 : 0;
        }

        if ($this->isSimpleNeed($state, $intent) && $slug === 'foundation-package') {
            $score += 20;
        }

        if ($this->isSimpleNeed($state, $intent) && $slug === 'digital-marketing-package') {
            $score -= 25;
        }

        return $score;
    }

    /**
     * @param  array{primary: string, flags: list<string>}  $intent
     */
    private function shouldRecommendPackage(array $intent, int $score): bool
    {
        if (in_array($intent['primary'], ['printing', 'custom'], true)) {
            return $score >= 80;
        }

        return $score >= 35;
    }

    /**
     * @param  array{primary: string, flags: list<string>}  $intent
     */
    private function isSimpleNeed(ConsultationState $state, array $intent): bool
    {
        $goals = $state->goals();
        $simpleGoals = array_intersect($goals, ['launch_business', 'rebrand', 'build_website', 'improve_online']);
        $heavyGoals = array_intersect($goals, ['run_ads', 'build_ecommerce', 'prepare_event', 'build_app']);

        return $intent['primary'] === 'foundation'
            || ($simpleGoals !== [] && $heavyGoals === [] && ! in_array('run_ads', $goals, true));
    }

    private function isMarketingHeavy(ConsultationState $state): bool
    {
        return (bool) array_intersect($state->goals(), [
            'increase_sales',
            'more_customers',
            'brand_awareness',
            'improve_social',
            'run_ads',
            'generate_leads',
            'improve_conversion',
        ]) || (bool) array_intersect($state->neededServices(), [
            'social_media',
            'marketing_strategy',
            'performance_marketing',
            'media_buying',
            'content',
        ]);
    }

    /**
     * @return list<string>
     */
    private function extraFlags(ConsultationState $state, string $primary): array
    {
        $flags = [$primary];
        if ($state->isUnsure()) {
            $flags[] = 'unsure';
        }
        if (in_array($state->get('timeline'), ['asap', '1_week'], true)) {
            $flags[] = 'urgent';
        }

        return array_values(array_unique($flags));
    }

    private function budgetMax(ConsultationState $state): ?float
    {
        $id = $state->get('budget');
        foreach (BusinessCatalog::budgetBands() as $band) {
            if ($band['id'] === $id) {
                return $band['max'] !== null ? (float) $band['max'] : null;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function findScored(array $rows, string $slug): ?array
    {
        foreach ($rows as $row) {
            if (($row['package']['slug'] ?? null) === $slug) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  array{primary: string, flags: list<string>}  $intent
     * @return list<string>
     */
    private function reasons(array $package, array $intent, ConsultationState $state, string $kind): array
    {
        $reasons = [];
        $slug = (string) $package['slug'];

        if ($slug === 'ecommerce-launch-package') {
            $reasons[] = 'الباقة تجمع متجراً إلكترونياً مع محتوى وحملة إطلاق من الخدمات المتاحة فعلياً.';
            $reasons[] = 'مدة التنفيذ والسعر المعروض هما بيانات الباقة الحالية في المنصة.';
        } elseif ($slug === 'events-package') {
            $reasons[] = 'الباقة تغطي هوية الفعالية والطباعة والتغطية المرئية من الخدمات النشطة.';
        } elseif ($slug === 'digital-marketing-package') {
            $reasons[] = 'أهدافك تركز على اكتساب العملاء والحضور الرقمي، وهذه الباقة تجمع الاستراتيجية والمحتوى والحملات.';
        } elseif ($slug === 'foundation-package') {
            $reasons[] = $this->isSimpleNeed($state, $intent)
                ? 'نرشّح الباقة التأسيسية لأنها تغطي الاحتياج الحالي دون خدمات غير ضرورية.'
                : 'الباقة التأسيسية نقطة انطلاق واضحة للهوية والمحتوى.';
        }

        if ($kind === 'alt') {
            $reasons[] = 'بديل إن أردت نطاقاً مختلفاً من الخدمات المدرجة في الباقة.';
        }

        if ($reasons === []) {
            $reasons[] = 'الباقة نشطة في المنصة وتتقاطع مع الأهداف التي ذكرتها.';
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  list<string>  $reasons
     * @return array<string, mixed>
     */
    private function presentPackage(array $package, array $reasons): array
    {
        return [
            'kind' => 'package',
            'id' => $package['id'],
            'slug' => $package['slug'],
            'name' => $package['name'],
            'description' => $package['description'],
            'category' => $package['category'],
            'price' => $package['price'],
            'discount_amount' => $package['discount_amount'],
            'final_price' => $package['final_price'],
            'currency' => $package['currency'],
            'duration_days' => $package['duration_days'],
            'items' => $package['items'],
            'reasons' => $reasons,
            'cta' => [
                'type' => $package['category'] === 'EVENTS' ? 'plan_event' : 'choose_package',
                'label' => $package['category'] === 'EVENTS' ? 'خطط فعاليتك' : 'اختر هذه الباقة',
                'path' => $package['category'] === 'EVENTS'
                    ? '/event-packages'
                    : '/customer?intent=order&package='.urlencode((string) $package['slug']),
            ],
        ];
    }

    /**
     * @param  array{primary: string, flags: list<string>}  $intent
     * @param  list<array<string, mixed>>  $services
     * @return list<array<string, mixed>>
     */
    private function matchingServices(ConsultationState $state, array $intent, array $services, ?float $budgetMax): array
    {
        $wantedSlugs = match ($intent['primary']) {
            'ecommerce' => ['ecommerce-store', 'advertising-campaign', 'content-creation'],
            'event' => ['event-branding', 'printing-service', 'video-production'],
            'printing' => ['printing-service', 'graphic-design'],
            'custom' => [],
            'marketing' => ['social-media-strategy', 'content-creation', 'advertising-campaign', 'content-calendar'],
            default => ['social-media-strategy', 'graphic-design', 'content-creation'],
        };

        $matched = [];
        foreach ($services as $service) {
            if (! in_array($service['slug'], $wantedSlugs, true)) {
                continue;
            }
            if ($budgetMax !== null && (float) $service['base_price'] > $budgetMax) {
                continue;
            }
            $matched[] = [
                'kind' => 'service',
                'id' => $service['id'],
                'slug' => $service['slug'],
                'name' => $service['name'],
                'summary' => $service['summary'],
                'category' => $service['category'],
                'base_price' => $service['base_price'],
                'currency' => $service['currency'],
                'duration_days' => $service['duration_days'],
                'cta' => [
                    'type' => 'request_service',
                    'label' => 'اطلب هذه الخدمة',
                    'path' => '/services',
                ],
            ];
        }

        return array_slice($matched, 0, 3);
    }

    /**
     * @param  array{primary: string, flags: list<string>}  $intent
     * @return array<string, mixed>|null
     */
    private function printingRecommendation(ConsultationState $state, array $intent): ?array
    {
        if ($intent['primary'] !== 'printing' && ! in_array('printing', $state->neededServices(), true) && ! in_array('print_materials', $state->goals(), true)) {
            return null;
        }

        $product = $state->get('answers')['printing_product'] ?? null;
        $slug = is_string($product) && PrintingCatalog::hasSlug($product) ? $product : null;
        $meta = $slug ? PrintingCatalog::product($slug) : null;

        return [
            'kind' => 'printing',
            'product_slug' => $slug,
            'requires_quote' => $meta['requires_quote'] ?? ($product === 'custom_quote' || $slug === null),
            'starting_price' => $meta['starting_price'] ?? null,
            'currency' => 'SAR',
            'cta' => [
                'type' => $meta && ($meta['requires_quote'] ?? false) ? 'request_quote' : 'printing',
                'label' => $meta && ($meta['requires_quote'] ?? false) ? 'اطلب عرض سعر' : 'استكشف الطباعة والتغليف',
                'path' => $slug && ! ($meta['requires_quote'] ?? false)
                    ? '/printing/customize/'.$slug
                    : '/printing-packaging',
            ],
        ];
    }

    /**
     * @param  array{primary: string, flags: list<string>}  $intent
     * @param  array<string, mixed>|null  $package
     * @param  list<array<string, mixed>>  $services
     * @param  array<string, mixed>|null  $printing
     * @return array{type: string, label: string, path: string}
     */
    private function cta(array $intent, ?array $package, array $services, ?array $printing, ConsultationState $state): array
    {
        if ($intent['primary'] === 'custom') {
            return $this->expertOrQuoteCta($state, $intent);
        }

        if ($package) {
            if (($package['category'] ?? null) === 'EVENTS') {
                return [
                    'type' => 'plan_event',
                    'label' => 'خطط فعاليتك',
                    'path' => '/event-packages',
                ];
            }

            return [
                'type' => 'choose_package',
                'label' => 'اختر هذه الباقة',
                'path' => '/customer?intent=order&package='.urlencode((string) $package['slug']),
            ];
        }

        if ($printing) {
            return $printing['cta'];
        }

        if ($services !== []) {
            return [
                'type' => 'request_service',
                'label' => 'اطلب هذه الخدمة',
                'path' => '/services',
            ];
        }

        return $this->expertOrQuoteCta($state, $intent);
    }

    /**
     * @param  array{primary: string, flags: list<string>}  $intent
     * @return array{type: string, label: string, path: string}
     */
    private function expertOrQuoteCta(ConsultationState $state, array $intent): array
    {
        $highIntent = in_array($state->get('budget'), ['25000_50000', '50000_plus'], true)
            || in_array($state->get('timeline'), ['asap', '1_week'], true);

        if ($highIntent) {
            return [
                'type' => 'book_consultation',
                'label' => 'احجز استشارة',
                'path' => '/consultant?lead=1',
            ];
        }

        if ($intent['primary'] === 'custom' || $state->isUnsure()) {
            return [
                'type' => 'talk_expert',
                'label' => 'تحدث مع مختص',
                'path' => '/consultant?lead=1',
            ];
        }

        return [
            'type' => 'request_quote',
            'label' => 'اطلب عرض سعر',
            'path' => '/build-package',
        ];
    }
}
