<?php

namespace Database\Seeders;

use App\Models\RecommendationGoal;
use Illuminate\Database\Seeder;

class RecommendationGoalSeeder extends Seeder
{
    /**
     * The eight goals from the platform structure PDF §10.
     * Item slugs must point to real catalog services/packages/addons.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'launch-project',
                'name_ar' => 'إطلاق مشروع',
                'explanation' => 'استراتيجية + هوية + محتوى + تصوير + فيديو + قنوات البيع.',
                'sort_order' => 1,
                'items' => [
                    ['item_type' => 'package', 'item_slug' => 'foundation-package', 'priority' => 0, 'reason_ar' => 'باقة إطلاق مشروع تغطي التأسيس البصري والمحتوى.'],
                    ['item_type' => 'service', 'item_slug' => 'marketing-strategy', 'priority' => 1, 'reason_ar' => 'استراتيجية واضحة قبل التنفيذ.'],
                    ['item_type' => 'service', 'item_slug' => 'brand-identity', 'priority' => 2],
                    ['item_type' => 'service', 'item_slug' => 'content-creation', 'priority' => 3],
                    ['item_type' => 'addon', 'item_slug' => 'printing-addon', 'priority' => 4, 'reason_ar' => 'مواد افتتاح مطبوعة عند الحاجة.'],
                ],
            ],
            [
                'slug' => 'increase-sales',
                'name_ar' => 'زيادة المبيعات',
                'explanation' => 'تحليل + استراتيجية تحويل + محتوى بيعي + إعلانات + تحسين رحلة الشراء.',
                'sort_order' => 2,
                'items' => [
                    ['item_type' => 'package', 'item_slug' => 'digital-marketing-package', 'priority' => 0, 'reason_ar' => 'باقة شهرية تركّز على المحتوى والحضور.'],
                    ['item_type' => 'service', 'item_slug' => 'competitor-analysis', 'priority' => 1],
                    ['item_type' => 'service', 'item_slug' => 'advertising-campaign', 'priority' => 2],
                    ['item_type' => 'service', 'item_slug' => 'purchase-journey-optimization', 'priority' => 3],
                    ['item_type' => 'addon', 'item_slug' => 'ads-management', 'priority' => 4],
                ],
            ],
            [
                'slug' => 'build-brand',
                'name_ar' => 'بناء براند',
                'explanation' => 'تموضع + هوية + دليل + محتوى + قوالب.',
                'sort_order' => 3,
                'items' => [
                    ['item_type' => 'package', 'item_slug' => 'brand-building', 'priority' => 0, 'reason_ar' => 'باقة بناء البراند للتأسيس الاحترافي.'],
                    ['item_type' => 'service', 'item_slug' => 'brand-identity', 'priority' => 1],
                    ['item_type' => 'service', 'item_slug' => 'brand-guidelines', 'priority' => 2],
                    ['item_type' => 'service', 'item_slug' => 'social-media-designs', 'priority' => 3],
                ],
            ],
            [
                'slug' => 'launch-product',
                'name_ar' => 'إطلاق منتج',
                'explanation' => 'استراتيجية إطلاق + تصوير + فيديو + محتوى + صفحة/متجر + تغليف.',
                'sort_order' => 4,
                'items' => [
                    ['item_type' => 'package', 'item_slug' => 'product-launch', 'priority' => 0],
                    ['item_type' => 'service', 'item_slug' => 'launch-strategy', 'priority' => 1],
                    ['item_type' => 'service', 'item_slug' => 'product-photography', 'priority' => 2],
                    ['item_type' => 'service', 'item_slug' => 'ad-video', 'priority' => 3],
                    ['item_type' => 'addon', 'item_slug' => 'packaging-addon', 'priority' => 4],
                    ['item_type' => 'addon', 'item_slug' => 'landing-page', 'priority' => 5],
                ],
            ],
            [
                'slug' => 'improve-social',
                'name_ar' => 'تحسين السوشيال',
                'explanation' => 'تدقيق الحساب + استراتيجية + تقويم + تصميم + فيديو + محتوى.',
                'sort_order' => 5,
                'items' => [
                    ['item_type' => 'package', 'item_slug' => 'digital-marketing-package', 'priority' => 0],
                    ['item_type' => 'service', 'item_slug' => 'social-media-strategy', 'priority' => 1],
                    ['item_type' => 'service', 'item_slug' => 'social-media-plan', 'priority' => 2],
                    ['item_type' => 'service', 'item_slug' => 'content-calendar', 'priority' => 3],
                    ['item_type' => 'service', 'item_slug' => 'reels', 'priority' => 4],
                    ['item_type' => 'addon', 'item_slug' => 'extra-design', 'priority' => 5],
                ],
            ],
            [
                'slug' => 'open-branch',
                'name_ar' => 'افتتاح فرع',
                'explanation' => 'حملة افتتاح + تصميم + طباعة + تصوير + فيديو + فعالية.',
                'sort_order' => 6,
                'items' => [
                    ['item_type' => 'package', 'item_slug' => 'events-package', 'priority' => 0],
                    ['item_type' => 'service', 'item_slug' => 'launch-strategy', 'priority' => 1],
                    ['item_type' => 'service', 'item_slug' => 'event-designs', 'priority' => 2],
                    ['item_type' => 'service', 'item_slug' => 'printing-service', 'priority' => 3],
                    ['item_type' => 'service', 'item_slug' => 'event-photography', 'priority' => 4],
                    ['item_type' => 'addon', 'item_slug' => 'printing-addon', 'priority' => 5],
                ],
            ],
            [
                'slug' => 'event',
                'name_ar' => 'فعالية',
                'explanation' => 'Concept + هوية + تجهيز + طباعة + تأجير/شراء + تصوير وفيديو.',
                'sort_order' => 7,
                'items' => [
                    ['item_type' => 'package', 'item_slug' => 'events-package', 'priority' => 0],
                    ['item_type' => 'service', 'item_slug' => 'event-branding', 'priority' => 1],
                    ['item_type' => 'service', 'item_slug' => 'printing-service', 'priority' => 2],
                    ['item_type' => 'service', 'item_slug' => 'video-production', 'priority' => 3],
                    ['item_type' => 'addon', 'item_slug' => 'extra-photography', 'priority' => 4],
                ],
            ],
            [
                'slug' => 'build-store',
                'name_ar' => 'إنشاء متجر',
                'explanation' => 'استراتيجية تجارة إلكترونية + متجر + صفحات منتجات + تصوير + محتوى.',
                'sort_order' => 8,
                'items' => [
                    ['item_type' => 'package', 'item_slug' => 'ecommerce-launch-package', 'priority' => 0],
                    ['item_type' => 'service', 'item_slug' => 'ecommerce-store', 'priority' => 1],
                    ['item_type' => 'service', 'item_slug' => 'product-pages-setup', 'priority' => 2],
                    ['item_type' => 'service', 'item_slug' => 'product-photography', 'priority' => 3],
                    ['item_type' => 'service', 'item_slug' => 'product-descriptions', 'priority' => 4],
                    ['item_type' => 'addon', 'item_slug' => 'landing-page', 'priority' => 5],
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            $goal = RecommendationGoal::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name_ar' => $definition['name_ar'],
                    'explanation' => $definition['explanation'],
                    'is_active' => true,
                    'sort_order' => $definition['sort_order'],
                ],
            );

            $goal->items()->delete();

            foreach ($definition['items'] as $item) {
                $goal->items()->create([
                    'item_type' => $item['item_type'],
                    'item_slug' => $item['item_slug'],
                    'priority' => $item['priority'] ?? 0,
                    'reason_ar' => $item['reason_ar'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'is_active' => true,
                ]);
            }
        }
    }
}
