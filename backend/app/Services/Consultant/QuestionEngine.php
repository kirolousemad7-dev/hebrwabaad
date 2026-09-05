<?php

namespace App\Services\Consultant;

use App\Support\Consultant\BusinessCatalog;
use App\Support\Consultant\ConsultationState;

class QuestionEngine
{
    /**
     * @return array<string, mixed>|null
     */
    public function next(ConsultationState $state): ?array
    {
        foreach ($this->pendingIds($state) as $id) {
            if (! $state->answered($id) && ! $state->skipped($id)) {
                return $this->hydrate($id, $state);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function pendingIds(ConsultationState $state): array
    {
        $ids = ['help_mode', 'business_category'];

        $category = is_string($state->get('business_category')) ? $state->get('business_category') : null;
        $categoryMeta = $category ? BusinessCatalog::category($category) : null;

        if (is_array($categoryMeta) && count($categoryMeta['subtypes'] ?? []) > 1) {
            $ids[] = 'business_subtype';
        }

        if ($state->get('help_mode') !== 'chat') {
            $ids[] = 'business_name';
            $ids[] = 'location';
        }

        if (in_array($category, ['restaurants-food', 'retail', 'medical-healthcare', 'hospitality-tourism', 'beauty-personal-care', 'fitness-sports'], true)) {
            $ids[] = 'branches';
        }

        $ids[] = 'goals';

        if ($this->isEventFlow($state)) {
            if (! $state->filled('business_subtype')) {
                $ids[] = 'event_type';
            }
            $ids[] = 'event_date';
            $ids[] = 'attendees';
            $ids[] = 'event_needs';
        }

        foreach ($this->situationIds($state) as $situationId) {
            $ids[] = $situationId;
        }

        if (! $state->isUnsure()) {
            $ids[] = 'needed_services';
        }

        $ids[] = 'budget';
        $ids[] = 'timeline';

        return array_values(array_unique($ids));
    }

    public function progress(ConsultationState $state): array
    {
        $ids = $this->pendingIds($state);
        $answered = 0;

        foreach ($ids as $id) {
            if ($state->answered($id) || $state->skipped($id)) {
                $answered++;
            }
        }

        $total = max(count($ids), 1);

        return [
            'current' => min($answered + 1, $total),
            'total' => $total,
            'percent' => (int) round(($answered / $total) * 100),
        ];
    }

    public function readyForRecommendation(ConsultationState $state): bool
    {
        if (! $state->filled('business_category') && ! $state->filled('business_subtype')) {
            return false;
        }

        $hasDirection = $state->goals() !== [] || $state->neededServices() !== [] || $state->isUnsure();
        $hasBudget = $state->filled('budget');
        $hasTimeline = $state->filled('timeline') || ($this->isEventFlow($state) && $state->filled('event_date'));

        $situationAnswered = 0;
        foreach ($this->situationIds($state) as $id) {
            if ($state->answered($id) || $state->skipped($id)) {
                $situationAnswered++;
            }
        }

        return $hasDirection && $hasBudget && $hasTimeline && $situationAnswered >= 1;
    }

    /**
     * @return list<string>
     */
    public function situationIds(ConsultationState $state): array
    {
        $category = (string) $state->get('business_category');
        $goals = $state->goals();
        $needs = $state->neededServices();

        if ($category === 'restaurants-food') {
            return ['dine_channels', 'has_website', 'has_online_ordering', 'social_platforms', 'biggest_challenge'];
        }

        if ($category === 'ecommerce' || in_array('build_ecommerce', $goals, true) || in_array('ecommerce', $needs, true)) {
            return ['product_count', 'has_website', 'has_payment', 'has_shipping', 'biggest_challenge'];
        }

        if ($category === 'real-estate') {
            return ['re_role', 'has_website', 'has_crm', 'lead_source', 'biggest_challenge'];
        }

        if ($category === 'medical-healthcare') {
            return ['has_booking', 'has_website', 'social_platforms', 'biggest_challenge'];
        }

        if ($this->isEventFlow($state)) {
            return ['social_platforms'];
        }

        if ($category === 'printing-packaging' || in_array('printing', $needs, true) || in_array('print_materials', $goals, true) || in_array('improve_packaging', $goals, true)) {
            return ['printing_product', 'printing_quantity', 'biggest_challenge'];
        }

        if (in_array('build_website', $goals, true) || in_array('build_app', $goals, true) || in_array('website', $needs, true) || in_array('software', $needs, true) || $category === 'technology') {
            return ['website_purpose', 'has_website', 'needs_payments', 'biggest_challenge'];
        }

        if ($this->isMarketingFlow($state) || $state->isUnsure()) {
            return ['has_website', 'current_channels', 'run_ads', 'social_platforms', 'biggest_challenge'];
        }

        return ['has_website', 'social_platforms', 'biggest_challenge'];
    }

    /**
     * @return array<string, mixed>
     */
    public function hydrate(string $id, ConsultationState $state): array
    {
        $question = BusinessCatalog::question($id) ?? [
            'id' => $id,
            'title' => $id,
            'type' => 'text',
        ];

        $question['options'] = $this->optionsFor($id, $state, $question['options'] ?? null);

        return $question;
    }

    /**
     * @param  list<array<string, mixed>>|null  $defined
     * @return list<array<string, mixed>>
     */
    private function optionsFor(string $id, ConsultationState $state, ?array $defined): array
    {
        if ($id === 'business_category') {
            return array_map(fn (array $category) => [
                'id' => $category['id'],
                'label' => $category['label'],
                'icon' => $category['icon'],
            ], BusinessCatalog::categories());
        }

        if ($id === 'business_subtype' || $id === 'event_type') {
            $category = BusinessCatalog::category((string) $state->get('business_category'));

            return is_array($category) ? array_values($category['subtypes'] ?? []) : [];
        }

        if ($id === 'goals') {
            return BusinessCatalog::goals();
        }

        if ($id === 'needed_services') {
            return BusinessCatalog::serviceNeeds();
        }

        if ($id === 'budget') {
            return array_map(fn (array $band) => [
                'id' => $band['id'],
                'label' => $band['label'],
            ], BusinessCatalog::budgetBands());
        }

        if ($id === 'timeline') {
            return BusinessCatalog::timelines();
        }

        if ($id === 'printing_product') {
            return BusinessCatalog::printingProductOptions();
        }

        return is_array($defined) ? $defined : [];
    }

    public function isEventFlow(ConsultationState $state): bool
    {
        return $state->get('business_category') === 'events'
            || in_array('prepare_event', $state->goals(), true)
            || in_array('event_management', $state->neededServices(), true);
    }

    public function isMarketingFlow(ConsultationState $state): bool
    {
        $goals = $state->goals();
        $needs = $state->neededServices();

        return (bool) array_intersect($goals, [
            'increase_sales',
            'more_customers',
            'brand_awareness',
            'improve_online',
            'improve_social',
            'run_ads',
            'generate_leads',
            'improve_conversion',
            'retention',
        ]) || (bool) array_intersect($needs, [
            'social_media',
            'marketing_strategy',
            'performance_marketing',
            'media_buying',
            'content',
        ]);
    }
}
