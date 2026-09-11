<?php

namespace App\Services\Catalog;

use App\Models\CatalogAddon;
use App\Models\Package;
use App\Models\RecommendationGoal;
use App\Models\RecommendationGoalItem;
use App\Models\Service;
use App\Support\Consultant\ConsultationState;

class GoalRecommendationService
{
    /**
     * Map consultation goal ids to recommendation_goals.slug values.
     *
     * @var array<string, string>
     */
    private const GOAL_ALIASES = [
        'launch_business' => 'launch-project',
        'launch_product' => 'launch-product',
        'increase_sales' => 'increase-sales',
        'more_customers' => 'increase-sales',
        'improve_conversion' => 'increase-sales',
        'rebrand' => 'build-brand',
        'brand_awareness' => 'build-brand',
        'improve_social' => 'improve-social',
        'prepare_event' => 'event',
        'build_ecommerce' => 'build-store',
        'build_website' => 'build-store',
        'open_branch' => 'open-branch',
    ];

    /**
     * @return array{
     *     matched: bool,
     *     goals: list<array<string, mixed>>,
     *     package: array<string, mixed>|null,
     *     services: list<array<string, mixed>>,
     *     addons: list<array<string, mixed>>,
     *     ctas: list<array{type: string, label: string, path: string}>
     * }|null
     */
    public function recommendForState(ConsultationState $state): ?array
    {
        $goalSlugs = $this->resolveGoalSlugs($state->goals());

        if ($goalSlugs === []) {
            return null;
        }

        $goals = RecommendationGoal::query()
            ->active()
            ->whereIn('slug', $goalSlugs)
            ->with(['items' => fn ($query) => $query->active()->orderBy('priority')->orderBy('id')])
            ->orderBy('sort_order')
            ->get();

        if ($goals->isEmpty()) {
            return null;
        }

        return $this->buildRecommendation($goals->all());
    }

    /**
     * @param  list<string>  $goalSlugs
     * @return array<string, mixed>|null
     */
    public function recommendForSlugs(array $goalSlugs): ?array
    {
        $goals = RecommendationGoal::query()
            ->active()
            ->whereIn('slug', $goalSlugs)
            ->with(['items' => fn ($query) => $query->active()->orderBy('priority')->orderBy('id')])
            ->orderBy('sort_order')
            ->get();

        if ($goals->isEmpty()) {
            return null;
        }

        return $this->buildRecommendation($goals->all());
    }

    /**
     * @param  list<RecommendationGoal>  $goals
     * @return array<string, mixed>
     */
    private function buildRecommendation(array $goals): array
    {
        $package = null;
        $services = [];
        $addons = [];
        $goalSummaries = [];

        foreach ($goals as $goal) {
            $goalSummaries[] = [
                'slug' => $goal->slug,
                'name_ar' => $goal->name_ar,
                'explanation' => $goal->explanation,
            ];

            foreach ($goal->items as $item) {
                $resolved = $this->resolveItem($item);

                if ($resolved === null) {
                    continue;
                }

                if ($resolved['kind'] === 'package' && $package === null) {
                    $package = $resolved;
                } elseif ($resolved['kind'] === 'service') {
                    $services[$resolved['slug']] = $resolved;
                } elseif ($resolved['kind'] === 'addon') {
                    $addons[$resolved['slug']] = $resolved;
                }
            }
        }

        return [
            'matched' => true,
            'goals' => $goalSummaries,
            'package' => $package,
            'services' => array_values($services),
            'addons' => array_values($addons),
            'ctas' => $this->ctas(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveItem(RecommendationGoalItem $item): ?array
    {
        return match ($item->item_type) {
            'package' => $this->presentPackage($item),
            'service' => $this->presentService($item),
            'addon' => $this->presentAddon($item),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentPackage(RecommendationGoalItem $item): ?array
    {
        $package = Package::query()
            ->active()
            ->public()
            ->where('slug', $item->item_slug)
            ->first();

        if ($package === null) {
            return null;
        }

        return [
            'kind' => 'package',
            'id' => $package->id,
            'slug' => $package->slug,
            'name' => $package->name,
            'description' => $package->description,
            'category' => $package->category->value,
            'price' => $package->price,
            'discount_amount' => $package->discount_amount,
            'final_price' => $package->finalPrice(),
            'currency' => $package->currency,
            'pricing_mode' => $package->pricingMode()->value,
            'is_chargeable' => $package->isChargeable(),
            'duration_days' => $package->duration_days,
            'reason_ar' => $item->reason_ar,
            'quantity' => $item->quantity,
            'priority' => $item->priority,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentService(RecommendationGoalItem $item): ?array
    {
        $service = Service::query()
            ->active()
            ->public()
            ->where('slug', $item->item_slug)
            ->first();

        if ($service === null) {
            return null;
        }

        return [
            'kind' => 'service',
            'id' => $service->id,
            'slug' => $service->slug,
            'name' => $service->name,
            'summary' => $service->summary,
            'category' => $service->category->value,
            'base_price' => $service->base_price,
            'currency' => $service->currency,
            'pricing_mode' => $service->pricingMode()->value,
            'is_chargeable' => $service->isChargeable(),
            'duration_days' => $service->duration_days,
            'reason_ar' => $item->reason_ar,
            'quantity' => $item->quantity,
            'priority' => $item->priority,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentAddon(RecommendationGoalItem $item): ?array
    {
        $addon = CatalogAddon::query()
            ->active()
            ->public()
            ->where('slug', $item->item_slug)
            ->first();

        if ($addon === null || ! $addon->isAvailable()) {
            return null;
        }

        return [
            'kind' => 'addon',
            'id' => $addon->id,
            'slug' => $addon->slug,
            'name' => $addon->name,
            'summary' => $addon->summary,
            'price' => $addon->price,
            'currency' => $addon->currency,
            'pricing_mode' => $addon->pricingMode()->value,
            'is_chargeable' => $addon->isChargeable(),
            'is_urgent' => $addon->is_urgent,
            'reason_ar' => $item->reason_ar,
            'quantity' => $item->quantity,
            'priority' => $item->priority,
        ];
    }

    /**
     * @param  list<string>  $goalIds
     * @return list<string>
     */
    private function resolveGoalSlugs(array $goalIds): array
    {
        $slugs = [];

        foreach ($goalIds as $goalId) {
            if (isset(self::GOAL_ALIASES[$goalId])) {
                $slugs[] = self::GOAL_ALIASES[$goalId];
            } elseif (RecommendationGoal::query()->where('slug', $goalId)->exists()) {
                $slugs[] = $goalId;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @return list<array{type: string, label: string, path: string}>
     */
    private function ctas(): array
    {
        return [
            [
                'type' => 'accept_recommendation',
                'label' => 'اعتمد التوصية',
                'path' => '/build-package',
            ],
            [
                'type' => 'modify_need',
                'label' => 'عدّل احتياجي',
                'path' => '/build-package',
            ],
            [
                'type' => 'human_consultation',
                'label' => 'أبغى استشارة بشرية',
                'path' => '/consultant?lead=1',
            ],
        ];
    }
}
