<?php

namespace Database\Seeders;

use App\Enums\CatalogPricingMode;
use App\Enums\PackageCategory;
use App\Models\Package;
use App\Models\Service;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * The eight solution packages from the platform structure document.
     * Existing records are matched by their established slug so the packages are
     * updated in place instead of duplicated. Prices are never invented: a
     * package without a documented price is created as quote-based.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'foundation-package',
                'name' => 'باقة إطلاق مشروع',
                'description' => 'حل متكامل لإطلاق المشروع: استراتيجية واضحة، هوية، خطة محتوى، ومواد بصرية جاهزة للنشر.',
                'audience' => 'مشروع جديد أو إعادة إطلاق قوية.',
                'deliverables' => [
                    'استراتيجية',
                    'هوية',
                    'خطة محتوى',
                    'تصاميم',
                    'تصوير',
                    'فيديو',
                    'تجهيز القنوات / المتجر حسب الحاجة',
                ],
                'category' => PackageCategory::General,
                'sort_order' => 1,
                'price' => 9500,
                'discount_amount' => 500,
                'duration_days' => 30,
                'is_featured' => true,
                'items' => [
                    ['service' => 'marketing-strategy', 'quantity' => 1, 'notes' => 'ورشة تحديد الأهداف مشمولة.'],
                    ['service' => 'brand-identity', 'quantity' => 1],
                    ['service' => 'content-calendar', 'quantity' => 1],
                    ['service' => 'social-media-designs', 'quantity' => 1],
                    ['service' => 'corporate-photography', 'quantity' => 1],
                    ['service' => 'ad-video', 'quantity' => 1],
                ],
            ],
            [
                'slug' => 'brand-building',
                'name' => 'باقة بناء البراند',
                'description' => 'تأسيس احترافي للعلامة: تموضع واضح، هوية بصرية، ودليل استخدام يحفظ ثبات العلامة.',
                'audience' => 'براند يحتاج تأسيساً احترافياً.',
                'deliverables' => [
                    'استراتيجية تموضع',
                    'هوية بصرية',
                    'دليل استخدام الهوية',
                    'ملف شركة / مواد أساسية',
                    'قوالب محتوى',
                ],
                'category' => PackageCategory::General,
                'sort_order' => 2,
                'items' => [
                    ['service' => 'marketing-strategy', 'quantity' => 1, 'notes' => 'استراتيجية تموضع العلامة.'],
                    ['service' => 'brand-identity', 'quantity' => 1],
                    ['service' => 'brand-guidelines', 'quantity' => 1],
                    ['service' => 'company-profile', 'quantity' => 1],
                    ['service' => 'social-media-designs', 'quantity' => 1, 'notes' => 'قوالب محتوى جاهزة.'],
                ],
            ],
            [
                'slug' => 'digital-marketing-package',
                'name' => 'باقة السوشيال الشهرية',
                'description' => 'إدارة شهرية متكاملة للمحتوى: استراتيجية، تقويم نشر، تصاميم وريلز، وتقرير أداء.',
                'audience' => 'براند يحتاج استمرارية شهرية.',
                'deliverables' => [
                    'استراتيجية شهرية',
                    'تقويم محتوى',
                    'تصاميم',
                    'ريلز',
                    'كتابة محتوى',
                    'تقرير أداء',
                ],
                'category' => PackageCategory::Marketing,
                'sort_order' => 3,
                'price' => 16500,
                'discount_amount' => 1500,
                'duration_days' => 30,
                'is_featured' => true,
                'items' => [
                    ['service' => 'social-media-strategy', 'quantity' => 1],
                    ['service' => 'content-calendar', 'quantity' => 1],
                    ['service' => 'social-media-designs', 'quantity' => 1],
                    ['service' => 'reels', 'quantity' => 1],
                    ['service' => 'content-creation', 'quantity' => 2, 'notes' => 'دفعتان من المحتوى شهرياً.'],
                ],
            ],
            [
                'slug' => 'product-launch',
                'name' => 'باقة إطلاق منتج',
                'description' => 'إطلاق منتج جديد باستراتيجية إطلاق، تصوير المنتج، فيديوهات، ومواد إعلانية.',
                'audience' => 'منتج جديد.',
                'deliverables' => [
                    'استراتيجية إطلاق',
                    'تصوير المنتج',
                    'فيديوهات',
                    'تصاميم',
                    'محتوى',
                    'مواد إعلان / تغليف حسب الحاجة',
                ],
                'category' => PackageCategory::Marketing,
                'sort_order' => 4,
                'items' => [
                    ['service' => 'launch-strategy', 'quantity' => 1],
                    ['service' => 'product-photography', 'quantity' => 1],
                    ['service' => 'ad-video', 'quantity' => 1],
                    ['service' => 'ad-designs', 'quantity' => 1],
                    ['service' => 'content-creation', 'quantity' => 1],
                    ['service' => 'packaging-design', 'quantity' => 1, 'notes' => 'حسب الحاجة.'],
                ],
            ],
            [
                'slug' => 'ecommerce-launch-package',
                'name' => 'باقة المتجر الإلكتروني',
                'description' => 'متجر جاهز للبيع: إعداد المتجر، صفحات المنتجات، محتوى المنتجات، وتحسين تجربة الشراء.',
                'audience' => 'مشروع يريد البيع أونلاين.',
                'deliverables' => [
                    'استراتيجية',
                    'إعداد المتجر',
                    'صفحات المنتجات',
                    'تصوير / محتوى المنتجات',
                    'تحسين تجربة الشراء',
                ],
                'category' => PackageCategory::General,
                'sort_order' => 5,
                'price' => 24000,
                'discount_amount' => 2000,
                'duration_days' => 60,
                'items' => [
                    ['service' => 'marketing-strategy', 'quantity' => 1],
                    ['service' => 'ecommerce-store', 'quantity' => 1],
                    ['service' => 'product-pages-setup', 'quantity' => 1],
                    ['service' => 'product-photography', 'quantity' => 1],
                    ['service' => 'product-descriptions', 'quantity' => 1],
                    ['service' => 'purchase-journey-optimization', 'quantity' => 1],
                ],
            ],
            [
                'slug' => 'restaurants',
                'name' => 'باقة المطاعم',
                'description' => 'حل مخصص للمطاعم والمقاهي: تصوير أطعمة، محتوى، تصاميم، ريلز، ومنيو.',
                'audience' => 'المطاعم والمقاهي.',
                'deliverables' => [
                    'استراتيجية',
                    'تصوير أطعمة',
                    'محتوى',
                    'تصاميم',
                    'ريلز',
                    'منيو',
                    'تغليف / مواد افتتاح حسب الطلب',
                ],
                'category' => PackageCategory::Marketing,
                'sort_order' => 6,
                'items' => [
                    ['service' => 'marketing-strategy', 'quantity' => 1],
                    ['service' => 'food-photography', 'quantity' => 1],
                    ['service' => 'content-creation', 'quantity' => 1],
                    ['service' => 'social-media-designs', 'quantity' => 1],
                    ['service' => 'reels', 'quantity' => 1],
                    ['service' => 'menu-design', 'quantity' => 1],
                    ['service' => 'packaging-design', 'quantity' => 1, 'notes' => 'حسب الطلب.'],
                ],
            ],
            [
                'slug' => 'b2b-companies',
                'name' => 'باقة الشركات B2B',
                'description' => 'حضور مؤسسي للشركات والخدمات المهنية: هوية، ملف شركة، تصوير منشأة، وفيديو تعريفي.',
                'audience' => 'الشركات والخدمات المهنية.',
                'deliverables' => [
                    'استراتيجية',
                    'هوية / تحديث هوية',
                    'ملف شركة',
                    'تصوير منشأة',
                    'فيديو تعريفي',
                    'عروض تقديمية',
                ],
                'category' => PackageCategory::General,
                'sort_order' => 7,
                'items' => [
                    ['service' => 'marketing-strategy', 'quantity' => 1],
                    ['service' => 'brand-identity', 'quantity' => 1, 'notes' => 'هوية جديدة أو تحديث هوية قائمة.'],
                    ['service' => 'company-profile', 'quantity' => 1],
                    ['service' => 'real-estate-photography', 'quantity' => 1, 'notes' => 'تصوير المنشأة.'],
                    ['service' => 'corporate-video', 'quantity' => 1],
                    ['service' => 'presentation-design', 'quantity' => 1],
                ],
            ],
            [
                'slug' => 'events-package',
                'name' => 'باقة الفعالية',
                'description' => 'تجهيز كامل للفعالية من فكرة الهوية حتى الطباعة والتجهيزات والتغطية المرئية.',
                'audience' => 'افتتاح، مؤتمر، معرض، أو مناسبة.',
                'deliverables' => [
                    'فكرة وهوية الفعالية',
                    'تصميم',
                    'طباعة',
                    'تجهيزات',
                    'تصوير وفيديو',
                    'إدارة التنفيذ / التأجير',
                ],
                'category' => PackageCategory::Events,
                'sort_order' => 8,
                'price' => 21000,
                'discount_amount' => 1000,
                'duration_days' => 45,
                'items' => [
                    ['service' => 'event-branding', 'quantity' => 1],
                    ['service' => 'event-designs', 'quantity' => 1],
                    ['service' => 'printing-service', 'quantity' => 2, 'notes' => 'دفعتا طباعة قبل وأثناء الفعالية.'],
                    ['service' => 'event-photography', 'quantity' => 1],
                    ['service' => 'video-production', 'quantity' => 1, 'notes' => 'تغطية مرئية ليوم واحد.'],
                ],
            ],
        ];
    }

    /**
     * The three levels recommended by the source document. Level pricing and
     * scope details are left for the owner to configure.
     *
     * @return array<int, array{slug: string, name: string}>
     */
    public static function tierDefinitions(): array
    {
        return [
            ['slug' => 'basic', 'name' => 'أساسية'],
            ['slug' => 'professional', 'name' => 'احترافية'],
            ['slug' => 'advanced', 'name' => 'متقدمة'],
        ];
    }

    public function run(): void
    {
        $serviceIds = Service::query()->pluck('id', 'slug');

        foreach (self::definitions() as $definition) {
            $items = $definition['items'];

            $package = Package::query()->firstOrNew(['slug' => $definition['slug']]);
            $existed = $package->exists;

            $package->fill([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'audience' => $definition['audience'],
                'deliverables' => $definition['deliverables'],
                'category' => $definition['category'],
                'sort_order' => $definition['sort_order'],
            ]);

            // Pricing and delivery terms belong to the owner: seeded on create only.
            if (! $existed) {
                $price = $definition['price'] ?? null;

                $package->fill([
                    'price' => $price ?? 0,
                    'discount_amount' => $definition['discount_amount'] ?? 0,
                    'currency' => 'SAR',
                    'pricing_mode' => $price === null
                        ? CatalogPricingMode::Quote
                        : CatalogPricingMode::Fixed,
                    'duration_days' => $definition['duration_days'] ?? null,
                    'revision_rounds' => null,
                    'is_active' => true,
                    'is_featured' => $definition['is_featured'] ?? false,
                ]);
            }

            $package->save();

            $pivot = [];

            foreach ($items as $index => $item) {
                $serviceId = $serviceIds[$item['service']] ?? null;

                if ($serviceId === null) {
                    continue;
                }

                $pivot[$serviceId] = [
                    'quantity' => $item['quantity'] ?? 1,
                    'sort_order' => $index,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $package->services()->sync($pivot);

            foreach (self::tierDefinitions() as $index => $tier) {
                $existingTier = $package->tiers()->where('slug', $tier['slug'])->first();

                if ($existingTier !== null) {
                    $existingTier->update([
                        'name' => $tier['name'],
                        'sort_order' => $index,
                    ]);

                    continue;
                }

                $package->tiers()->create([
                    'name' => $tier['name'],
                    'slug' => $tier['slug'],
                    'price' => null,
                    'currency' => $package->currency ?: 'SAR',
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
            }
        }
    }
}
