<?php

namespace Database\Seeders;

use App\Enums\CatalogPricingMode;
use App\Models\CatalogAddon;
use App\Models\Package;
use App\Models\Service;
use Illuminate\Database\Seeder;

class CatalogAddonSeeder extends Seeder
{
    /**
     * The twelve add-ons from the platform structure PDF §6.
     * Prices are never invented — all seed as QUOTE with null price.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'extra-photography',
                'name' => 'تصوير إضافي',
                'summary' => 'صور إضافية أو موقع/منتجات إضافية على جلسة التصوير.',
                'service_slugs' => ['product-photography', 'food-photography', 'corporate-photography'],
                'package_slugs' => ['restaurants', 'product-launch', 'foundation-package'],
                'sort_order' => 1,
            ],
            [
                'slug' => 'extra-video',
                'name' => 'فيديو إضافي',
                'summary' => 'ريلز إضافي أو فيديو إعلاني أو مقابلة إضافية.',
                'service_slugs' => ['reels', 'ad-video', 'video-production'],
                'package_slugs' => ['digital-marketing-package', 'product-launch'],
                'sort_order' => 2,
            ],
            [
                'slug' => 'extra-design',
                'name' => 'تصميم إضافي',
                'summary' => 'بوست أو ستوري أو بانر أو منيو إضافي.',
                'service_slugs' => ['social-media-designs', 'ad-designs', 'menu-design'],
                'package_slugs' => ['digital-marketing-package', 'brand-building'],
                'sort_order' => 3,
            ],
            [
                'slug' => 'extra-content-writing',
                'name' => 'كتابة محتوى إضافية',
                'summary' => 'كابشن أو وصف منتج أو نص إعلان إضافي.',
                'service_slugs' => ['content-creation', 'product-descriptions', 'content-writing'],
                'package_slugs' => ['digital-marketing-package', 'ecommerce-launch-package'],
                'sort_order' => 4,
            ],
            [
                'slug' => 'ads-management',
                'name' => 'إدارة إعلانات',
                'summary' => 'إعداد حملات وإدارة وتحسين الأداء الإعلاني.',
                'service_slugs' => ['advertising-campaign', 'marketing-strategy', 'social-media-strategy'],
                'package_slugs' => ['digital-marketing-package'],
                'sort_order' => 5,
            ],
            [
                'slug' => 'landing-page',
                'name' => 'صفحة هبوط',
                'summary' => 'Landing Page لحملة أو منتج.',
                'service_slugs' => ['landing-page-design', 'ecommerce-store'],
                'package_slugs' => ['ecommerce-launch-package', 'product-launch'],
                'sort_order' => 6,
            ],
            [
                'slug' => 'printing-addon',
                'name' => 'طباعة',
                'summary' => 'كروت وبروشورات واستيكرات ومنيو مطبوع.',
                'service_slugs' => ['printing-service'],
                'package_slugs' => ['events-package', 'restaurants'],
                'sort_order' => 7,
            ],
            [
                'slug' => 'packaging-addon',
                'name' => 'تغليف',
                'summary' => 'علب وأكياس وملصقات وSleeves للمنتجات والمطاعم.',
                'service_slugs' => ['packaging-design'],
                'package_slugs' => ['restaurants', 'product-launch'],
                'sort_order' => 8,
            ],
            [
                'slug' => 'urgent-service',
                'name' => 'خدمة عاجلة',
                'summary' => 'أولوية تنفيذ مقابل رسوم إضافية عند توفر السعة.',
                'is_urgent' => true,
                'requires_capacity' => true,
                'capacity_available' => false,
                'service_slugs' => [],
                'package_slugs' => [],
                'sort_order' => 9,
            ],
            [
                'slug' => 'extra-shoot-location',
                'name' => 'موقع تصوير إضافي',
                'summary' => 'فرع أو موقع تصوير إضافي خارج الجلسة الأساسية.',
                'service_slugs' => ['product-photography', 'food-photography', 'real-estate-photography'],
                'package_slugs' => ['restaurants', 'product-launch'],
                'sort_order' => 10,
            ],
            [
                'slug' => 'open-files-delivery',
                'name' => 'تسليم ملفات مفتوحة',
                'summary' => 'تسليم ملفات مفتوحة حسب نوع الخدمة والترخيص.',
                'service_slugs' => ['graphic-design', 'brand-identity', 'social-media-designs'],
                'package_slugs' => ['brand-building'],
                'sort_order' => 11,
            ],
            [
                'slug' => 'extra-revision-round',
                'name' => 'جولة تعديل إضافية',
                'summary' => 'جولة تعديل إضافية بعد العدد المتفق عليه.',
                'service_slugs' => ['graphic-design', 'video-production', 'video-editing'],
                'package_slugs' => ['foundation-package', 'digital-marketing-package'],
                'sort_order' => 12,
            ],
        ];
    }

    public function run(): void
    {
        $serviceIds = Service::query()->pluck('id', 'slug');
        $packageIds = Package::query()->pluck('id', 'slug');

        foreach (self::definitions() as $definition) {
            $addon = CatalogAddon::query()->firstOrNew(['slug' => $definition['slug']]);
            $existed = $addon->exists;

            $addon->fill([
                'name' => $definition['name'],
                'summary' => $definition['summary'],
                'description' => $definition['description'] ?? null,
                'is_urgent' => $definition['is_urgent'] ?? false,
                'requires_capacity' => $definition['requires_capacity'] ?? false,
                'sort_order' => $definition['sort_order'],
                'is_active' => true,
                'is_public' => true,
            ]);

            if (! $existed) {
                $addon->fill([
                    'pricing_mode' => CatalogPricingMode::Quote,
                    'price' => null,
                    'currency' => 'SAR',
                    'capacity_available' => $definition['capacity_available'] ?? false,
                ]);
            } elseif (array_key_exists('capacity_available', $definition) && $definition['slug'] === 'urgent-service') {
                // Keep urgent capacity flag aligned with PDF default only when still unset false intentionally.
                // Do not overwrite owner-enabled capacity.
            }

            $addon->save();

            $services = collect($definition['service_slugs'] ?? [])
                ->map(fn (string $slug) => $serviceIds[$slug] ?? null)
                ->filter()
                ->values()
                ->all();

            $packages = collect($definition['package_slugs'] ?? [])
                ->map(fn (string $slug) => $packageIds[$slug] ?? null)
                ->filter()
                ->values()
                ->all();

            $addon->services()->sync($services);
            $addon->packages()->sync($packages);
        }
    }
}
