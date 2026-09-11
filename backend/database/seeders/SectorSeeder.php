<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Sector;
use App\Models\Service;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    /**
     * The twelve sectors from the platform structure PDF §3.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'restaurants-cafes',
                'name_ar' => 'المطاعم والمقاهي',
                'name_en' => 'Restaurants & Cafes',
                'needs' => ['هوية', 'تصوير أطعمة', 'محتوى', 'فيديو', 'قوائم', 'تغليف', 'حملات وافتتاحات'],
                'service_slugs' => ['brand-identity', 'food-photography', 'content-creation', 'reels', 'menu-design', 'packaging-design', 'advertising-campaign'],
                'package_slugs' => ['restaurants', 'foundation-package'],
                'sort_order' => 1,
            ],
            [
                'slug' => 'retail-stores',
                'name_ar' => 'التجزئة والمتاجر',
                'name_en' => 'Retail & Stores',
                'needs' => ['هوية', 'منتجات', 'تصوير', 'محتوى', 'متجر إلكتروني', 'تغليف وإعلانات'],
                'service_slugs' => ['brand-identity', 'product-photography', 'content-creation', 'ecommerce-store', 'packaging-design', 'ad-designs'],
                'package_slugs' => ['product-launch', 'ecommerce-launch-package'],
                'sort_order' => 2,
            ],
            [
                'slug' => 'ecommerce',
                'name_ar' => 'التجارة الإلكترونية',
                'name_en' => 'E-commerce',
                'needs' => ['بناء متجر', 'صفحات منتجات', 'تصوير', 'وصف منتجات', 'محتوى', 'تحسين تجربة الشراء'],
                'service_slugs' => ['ecommerce-store', 'product-pages-setup', 'product-photography', 'product-descriptions', 'content-creation', 'purchase-journey-optimization'],
                'package_slugs' => ['ecommerce-launch-package'],
                'sort_order' => 3,
            ],
            [
                'slug' => 'real-estate',
                'name_ar' => 'العقار والتطوير العقاري',
                'name_en' => 'Real Estate',
                'needs' => ['تصوير عقاري', 'فيديو', 'جولات', 'هوية المشاريع', 'محتوى', 'حملات إطلاق'],
                'service_slugs' => ['real-estate-photography', 'ad-video', 'brand-identity', 'content-creation', 'launch-strategy'],
                'package_slugs' => ['product-launch', 'foundation-package'],
                'sort_order' => 4,
            ],
            [
                'slug' => 'industry-b2b',
                'name_ar' => 'الصناعة وقطاع الأعمال B2B',
                'name_en' => 'Industry & B2B',
                'needs' => ['استراتيجية', 'هوية', 'ملف شركة', 'تصوير منشآت', 'فيديو تعريفي', 'عروض ومحتوى'],
                'service_slugs' => ['marketing-strategy', 'brand-identity', 'company-profile', 'real-estate-photography', 'corporate-video', 'presentation-design'],
                'package_slugs' => ['b2b-companies'],
                'sort_order' => 5,
            ],
            [
                'slug' => 'health-clinics',
                'name_ar' => 'الصحة والعيادات',
                'name_en' => 'Health & Clinics',
                'needs' => ['هوية', 'محتوى توعوي', 'تصوير', 'فيديو', 'مواد مطبوعة', 'حملات'],
                'service_slugs' => ['brand-identity', 'content-creation', 'corporate-photography', 'ad-video', 'printing-service', 'advertising-campaign'],
                'package_slugs' => ['brand-building', 'digital-marketing-package'],
                'sort_order' => 6,
            ],
            [
                'slug' => 'beauty-fashion',
                'name_ar' => 'الجمال والموضة',
                'name_en' => 'Beauty & Fashion',
                'needs' => ['تصوير منتجات', 'مودلز', 'فيديو قصير', 'هوية', 'تغليف', 'محتوى'],
                'service_slugs' => ['product-photography', 'corporate-photography', 'reels', 'brand-identity', 'packaging-design', 'content-creation'],
                'package_slugs' => ['product-launch', 'digital-marketing-package'],
                'sort_order' => 7,
            ],
            [
                'slug' => 'hotels-hospitality',
                'name_ar' => 'الفنادق والضيافة',
                'name_en' => 'Hotels & Hospitality',
                'needs' => ['تصوير', 'فيديو', 'هوية', 'محتوى', 'مطبوعات', 'تجهيزات'],
                'service_slugs' => ['corporate-photography', 'corporate-video', 'brand-identity', 'content-creation', 'printing-service', 'event-branding'],
                'package_slugs' => ['foundation-package', 'events-package'],
                'sort_order' => 8,
            ],
            [
                'slug' => 'education-training',
                'name_ar' => 'التعليم والتدريب',
                'name_en' => 'Education & Training',
                'needs' => ['هوية', 'عروض', 'محتوى', 'تصوير', 'فيديو', 'صفحات هبوط', 'مواد مطبوعة'],
                'service_slugs' => ['brand-identity', 'presentation-design', 'content-creation', 'corporate-photography', 'ad-video', 'landing-page-design', 'printing-service'],
                'package_slugs' => ['brand-building', 'digital-marketing-package'],
                'sort_order' => 9,
            ],
            [
                'slug' => 'professional-services',
                'name_ar' => 'الشركات والخدمات المهنية',
                'name_en' => 'Professional Services',
                'needs' => ['هوية', 'ملف شركة', 'عروض تقديمية', 'محتوى', 'تصوير', 'فيديو مؤسسي'],
                'service_slugs' => ['brand-identity', 'company-profile', 'presentation-design', 'content-creation', 'corporate-photography', 'corporate-video'],
                'package_slugs' => ['b2b-companies', 'brand-building'],
                'sort_order' => 10,
            ],
            [
                'slug' => 'events-exhibitions',
                'name_ar' => 'الفعاليات والمعارض',
                'name_en' => 'Events & Exhibitions',
                'needs' => ['هوية فعالية', 'تصميم', 'طباعة', 'أجنحة', 'تصوير', 'فيديو', 'تأجير مستلزمات'],
                'service_slugs' => ['event-branding', 'event-designs', 'printing-service', 'event-photography', 'video-production'],
                'package_slugs' => ['events-package'],
                'sort_order' => 11,
            ],
            [
                'slug' => 'food-consumer-products',
                'name_ar' => 'المنتجات الغذائية والاستهلاكية',
                'name_en' => 'Food & Consumer Products',
                'needs' => ['تصوير', 'تغليف', 'هوية منتج', 'محتوى', 'فيديو', 'إطلاق منتج'],
                'service_slugs' => ['product-photography', 'packaging-design', 'brand-identity', 'content-creation', 'ad-video', 'launch-strategy'],
                'package_slugs' => ['product-launch'],
                'sort_order' => 12,
            ],
        ];
    }

    public function run(): void
    {
        $serviceIds = Service::query()->pluck('id', 'slug');
        $packageIds = Package::query()->pluck('id', 'slug');

        foreach (self::definitions() as $definition) {
            $sector = Sector::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name_ar' => $definition['name_ar'],
                    'name_en' => $definition['name_en'] ?? null,
                    'description' => $definition['description'] ?? null,
                    'needs' => $definition['needs'],
                    'is_active' => true,
                    'is_public' => true,
                    'sort_order' => $definition['sort_order'],
                ],
            );

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

            $sector->services()->sync($services);
            $sector->packages()->sync($packages);
        }
    }
}
