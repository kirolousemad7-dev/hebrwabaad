<?php

namespace App\Services\Catalog;

use App\Enums\TaskStatus;
use App\Models\CatalogAddon;
use App\Models\OrderItem;
use App\Models\Task;

/**
 * Enforces configured revision_rounds without inventing prices.
 */
class RevisionScopeService
{
    public const EXTRA_REVISION_ADDON_SLUG = 'extra-revision-round';

    /**
     * @return array{
     *   allowed: bool,
     *   included_rounds: int|null,
     *   used_rounds: int,
     *   remaining: int|null,
     *   requires_addon: bool,
     *   addon_slug: string|null,
     *   addon_priced: bool,
     *   message: string
     * }
     */
    public function evaluate(OrderItem $item): array
    {
        $item->loadMissing('service');
        $included = $item->service?->revision_rounds;

        $used = Task::query()
            ->where('order_item_id', $item->id)
            ->where('status', TaskStatus::Revision->value)
            ->count();

        // Count historical revision visits via tasks currently in revision OR
        // completed tasks that mention revision in source metadata is unavailable —
        // use status Revision as active revision round signal; owners track rounds.
        if ($included === null) {
            return [
                'allowed' => false,
                'included_rounds' => null,
                'used_rounds' => $used,
                'remaining' => null,
                'requires_addon' => true,
                'addon_slug' => self::EXTRA_REVISION_ADDON_SLUG,
                'addon_priced' => $this->addonIsPriced(),
                'message' => 'عدد جولات التعديل غير مُعرّف لهذه الخدمة. استخدم إضافة جولة التعديل الإضافية إن كانت مُسعّرة.',
            ];
        }

        $remaining = max(0, (int) $included - $used);
        $withinScope = $used < (int) $included;

        return [
            'allowed' => $withinScope,
            'included_rounds' => (int) $included,
            'used_rounds' => $used,
            'remaining' => $remaining,
            'requires_addon' => ! $withinScope,
            'addon_slug' => self::EXTRA_REVISION_ADDON_SLUG,
            'addon_priced' => $this->addonIsPriced(),
            'message' => $withinScope
                ? 'ما زال ضمن جولات التعديل المشمولة.'
                : 'تم استنفاد جولات التعديل المشمولة. أضف «جولة تعديل إضافية» إذا كانت مُسعّرة.',
        ];
    }

    private function addonIsPriced(): bool
    {
        $addon = CatalogAddon::query()
            ->where('slug', self::EXTRA_REVISION_ADDON_SLUG)
            ->where('is_active', true)
            ->first();

        if ($addon === null) {
            return false;
        }

        return $addon->isChargeable();
    }
}
