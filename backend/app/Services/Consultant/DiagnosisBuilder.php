<?php

namespace App\Services\Consultant;

use App\Support\Consultant\BusinessCatalog;
use App\Support\Consultant\ConsultationState;

class DiagnosisBuilder
{
    /**
     * @return array{summary: string, challenges: list<string>, priorities: list<array{label: string, level: string}>}
     */
    public function build(ConsultationState $state): array
    {
        $challenges = [];
        $priorities = [];

        $hasWebsite = $state->get('has_website');
        $noWebsite = in_array($hasWebsite, [false, 'no', null], true);
        $social = (array) $state->get('social_platforms', []);
        $weakSocial = $social === [] || in_array('none', $social, true);
        $goals = $state->goals();
        $challenge = $state->get('answers')['biggest_challenge'] ?? null;
        $runAds = $state->get('answers')['run_ads'] ?? null;
        $channels = (array) ($state->get('answers')['current_channels'] ?? []);

        if ($noWebsite && (in_array('build_website', $goals, true) || in_array('improve_online', $goals, true) || $state->get('business_category') === 'ecommerce')) {
            $challenges[] = 'لا يوجد موقع أو متجر جاهز لاستقبال الطلبات والتحويل.';
            $priorities[] = ['label' => 'الحضور الرقمي', 'level' => 'high'];
        } elseif ($noWebsite) {
            $challenges[] = 'الحضور الرقمي محدود بسبب غياب موقع واضح.';
            $priorities[] = ['label' => 'الحضور الرقمي', 'level' => 'medium'];
        }

        if ($weakSocial) {
            $challenges[] = 'لا توجد قنوات محتوى ثابتة على السوشيال ميديا.';
            $priorities[] = ['label' => 'المحتوى والتسويق', 'level' => 'high'];
        }

        if (in_array($runAds, ['no', null], true) && (in_array('increase_sales', $goals, true) || in_array('more_customers', $goals, true) || in_array('run_ads', $goals, true))) {
            $challenges[] = 'اكتساب العملاء يعتمد على القنوات العضوية دون حملات مدفوعة منتظمة.';
            $priorities[] = ['label' => 'اكتساب العملاء', 'level' => 'high'];
        }

        if (in_array('none', $channels, true)) {
            $challenges[] = 'لا توجد عملية تسويق منتظمة يمكن قياسها.';
        }

        if (in_array($challenge, ['low_conversion', 'weak_online'], true) || in_array('improve_conversion', $goals, true)) {
            $challenges[] = 'مسار التحويل من الاكتشاف إلى الطلب غير مكتمل.';
            $priorities[] = ['label' => 'التحويل', 'level' => 'high'];
        }

        if (in_array('rebrand', $goals, true) || $challenge === 'branding') {
            $challenges[] = 'الهوية البصرية أو الرسالة التسويقية تحتاج وضوحاً أكبر.';
            $priorities[] = ['label' => 'الهوية', 'level' => 'medium'];
        }

        if ($state->get('business_category') === 'restaurants-food' && in_array('increase_sales', $goals, true)) {
            $challenges[] = 'فرصة النمو الأوضح هي زيادة الطلبات عبر التوصيل والحضور الرقمي.';
            $priorities[] = ['label' => 'زيادة الطلبات', 'level' => 'high'];
        }

        if ($this->isEvent($state)) {
            $challenges[] = 'الفعالية تحتاج تنسيقاً بين الهوية، المواد المطبوعة، والتغطية.';
            $priorities[] = ['label' => 'تنفيذ الفعالية', 'level' => 'high'];
        }

        if ($challenges === []) {
            $challenges[] = 'الاحتياج الحالي هو ترتيب الأولويات واختيار الحل المناسب من خدمات المنصة.';
            $priorities[] = ['label' => 'تحديد الحل', 'level' => 'medium'];
        }

        $priorities = $this->uniquePriorities($priorities);
        $summary = $this->summary($state, $challenges);

        return [
            'summary' => $summary,
            'challenges' => array_values(array_unique($challenges)),
            'priorities' => $priorities,
        ];
    }

    /**
     * @param  list<string>  $challenges
     */
    private function summary(ConsultationState $state, array $challenges): string
    {
        $category = BusinessCatalog::category((string) $state->get('business_category'));
        $label = is_array($category) ? $category['label'] : 'نشاطك';
        $first = $challenges[0] ?? 'يمكن ترتيب الأولويات بعد مراجعة الوضع الحالي.';

        return "بعد مراجعة إجاباتك عن {$label}: {$first}";
    }

    /**
     * @param  list<array{label: string, level: string}>  $priorities
     * @return list<array{label: string, level: string}>
     */
    private function uniquePriorities(array $priorities): array
    {
        $seen = [];
        $unique = [];

        foreach ($priorities as $priority) {
            $key = $priority['label'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $priority;
        }

        return $unique;
    }

    private function isEvent(ConsultationState $state): bool
    {
        return $state->get('business_category') === 'events'
            || in_array('prepare_event', $state->goals(), true);
    }
}
