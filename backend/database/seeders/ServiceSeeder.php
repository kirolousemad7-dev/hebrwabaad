<?php

namespace Database\Seeders;

use App\Enums\CatalogPricingMode;
use App\Enums\ServiceCategory;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Standard service catalog from the platform structure document.
     * Services without a documented price are seeded as quote-based so the
     * owner sets the price instead of the platform inventing one.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'social-media-strategy',
                'name' => 'استراتيجية التواصل الاجتماعي',
                'summary' => 'خطة محتوى وتموضع للمنصات الاجتماعية مبنية على أهداف العلامة.',
                'description' => 'تحليل الجمهور والمنافسين، تحديد رسائل العلامة، بناء خطة نشر ربع سنوية، ومؤشرات قياس واضحة.',
                'category' => ServiceCategory::Strategy,
                'base_price' => 4500,
                'duration_days' => 14,
                'is_featured' => true,
            ],
            [
                'slug' => 'content-creation',
                'name' => 'كتابة وإنتاج المحتوى',
                'summary' => 'محتوى عربي احترافي للمنشورات والمقالات والإعلانات.',
                'description' => 'إعداد نصوص تسويقية بالعربية الفصحى أو اللهجة المحلية، مع مراجعة لغوية وتوافق مع نبرة العلامة.',
                'category' => ServiceCategory::Content,
                'base_price' => 2800,
                'duration_days' => 10,
                'is_featured' => true,
            ],
            [
                'slug' => 'content-calendar',
                'name' => 'التقويم التحريري الشهري',
                'summary' => 'جدولة شهرية للمحتوى عبر جميع القنوات.',
                'description' => 'تقويم نشر شهري يحدد المواضيع والقوالب وأوقات النشر لكل منصة.',
                'category' => ServiceCategory::Content,
                'base_price' => 1800,
                'duration_days' => 7,
            ],
            [
                'slug' => 'graphic-design',
                'name' => 'التصميم الجرافيكي',
                'summary' => 'تصاميم بصرية للهوية والمنشورات والمواد التسويقية.',
                'description' => 'تصميم منشورات، مواد مطبوعة، وعناصر هوية بصرية بصيغ جاهزة للنشر والطباعة.',
                'category' => ServiceCategory::Production,
                'base_price' => 3200,
                'duration_days' => 12,
            ],
            [
                'slug' => 'video-production',
                'name' => 'إنتاج ومونتاج الفيديو',
                'summary' => 'فيديوهات تعريفية وإعلانية مع مونتاج احترافي.',
                'description' => 'كتابة السيناريو، التصوير، المونتاج، التعليق الصوتي، والإخراج النهائي بجودة عالية.',
                'category' => ServiceCategory::Production,
                'base_price' => 6500,
                'duration_days' => 21,
                'is_featured' => true,
            ],
            [
                'slug' => 'ecommerce-store',
                'name' => 'إنشاء متجر إلكتروني',
                'summary' => 'متجر إلكتروني متكامل جاهز لاستقبال الطلبات.',
                'description' => 'تجهيز المتجر، ربط بوابات الدفع والشحن، رفع المنتجات، وتدريب الفريق على الإدارة.',
                'category' => ServiceCategory::Stores,
                'base_price' => 12000,
                'duration_days' => 30,
            ],
            [
                'slug' => 'advertising-campaign',
                'name' => 'إدارة الحملات الإعلانية',
                'summary' => 'حملات مدفوعة مع متابعة يومية وتحسين للأداء.',
                'description' => 'إعداد الحملات على المنصات المناسبة، إدارة الميزانية، اختبار الإعلانات، وتقارير أداء دورية.',
                'category' => ServiceCategory::Campaigns,
                'base_price' => 5500,
                'duration_days' => 30,
            ],
            [
                'slug' => 'printing-service',
                'name' => 'خدمات الطباعة',
                'summary' => 'طباعة المواد التسويقية بجودة تجارية.',
                'description' => 'طباعة البروشورات، الرول أب، البطاقات، والمواد الدعائية مع متابعة الجودة والتسليم.',
                'category' => ServiceCategory::Printing,
                'base_price' => 2200,
                'duration_days' => 7,
            ],
            [
                'slug' => 'event-branding',
                'name' => 'هوية وتنظيم الفعاليات',
                'summary' => 'هوية بصرية متكاملة للفعاليات والمعارض.',
                'description' => 'تصميم هوية الفعالية، المواد الدعائية، تجهيز المساحات، والتنسيق الميداني يوم التنفيذ.',
                'category' => ServiceCategory::Other,
                'base_price' => 9000,
                'duration_days' => 25,
            ],

            // الاستراتيجية والتسويق
            [
                'slug' => 'marketing-strategy',
                'name' => 'استراتيجية تسويقية',
                'summary' => 'خطة تسويق شاملة بأهداف ومراحل تنفيذ.',
                'category' => ServiceCategory::Strategy,
            ],
            [
                'slug' => 'content-strategy',
                'name' => 'استراتيجية محتوى',
                'summary' => 'تحديد محاور المحتوى ونبرة الصوت لكل منصة.',
                'category' => ServiceCategory::Strategy,
            ],
            [
                'slug' => 'competitor-analysis',
                'name' => 'تحليل المنافسين',
                'summary' => 'قراءة تموضع المنافسين وفرص التميّز.',
                'category' => ServiceCategory::Strategy,
            ],
            [
                'slug' => 'audience-research',
                'name' => 'دراسة الجمهور والعميل المستهدف',
                'summary' => 'تحديد شخصيات العملاء واحتياجاتهم الشرائية.',
                'category' => ServiceCategory::Strategy,
            ],
            [
                'slug' => 'launch-strategy',
                'name' => 'استراتيجية إطلاق منتج أو فرع',
                'summary' => 'خطة إطلاق بمراحل ما قبل وبعد الافتتاح.',
                'category' => ServiceCategory::Strategy,
            ],
            [
                'slug' => 'campaign-strategy',
                'name' => 'استراتيجية حملة',
                'summary' => 'بناء رسالة الحملة وقنواتها ومؤشرات نجاحها.',
                'category' => ServiceCategory::Strategy,
            ],
            [
                'slug' => 'marketing-consultation',
                'name' => 'استشارة تسويقية',
                'summary' => 'جلسة استشارية لتقييم الوضع الحالي وتحديد الأولويات.',
                'category' => ServiceCategory::Strategy,
            ],

            // البراند والتصميم
            [
                'slug' => 'brand-identity',
                'name' => 'هوية بصرية',
                'summary' => 'نظام بصري كامل للعلامة من الشعار حتى التطبيقات.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'logo-design',
                'name' => 'تصميم شعار',
                'summary' => 'شعار أساسي بنسخ متعددة الاستخدام.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'brand-guidelines',
                'name' => 'دليل استخدام الهوية',
                'summary' => 'مرجع مكتوب لقواعد استخدام الهوية البصرية.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'social-media-designs',
                'name' => 'تصاميم سوشيال ميديا',
                'summary' => 'تصاميم منشورات جاهزة للنشر على المنصات.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'presentation-design',
                'name' => 'تصميم عروض تقديمية',
                'summary' => 'عروض احترافية للعرض على العملاء والشركاء.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'company-profile',
                'name' => 'ملف شركة',
                'summary' => 'ملف تعريفي يوضح الخدمات والأعمال السابقة.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'menu-design',
                'name' => 'تصميم منيو',
                'summary' => 'منيو مطبوع أو رقمي مرتب حسب الأقسام.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'ad-designs',
                'name' => 'تصاميم إعلانية',
                'summary' => 'مواد إعلانية مخصصة للحملات المدفوعة.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'event-designs',
                'name' => 'تصميمات مناسبات وفعاليات',
                'summary' => 'مواد بصرية للفعاليات والمناسبات.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'packaging-design',
                'name' => 'تصميم تغليف',
                'summary' => 'تصميم عبوات ومواد تغليف المنتج.',
                'category' => ServiceCategory::Production,
            ],

            // التصوير والفيديو
            [
                'slug' => 'product-photography',
                'name' => 'تصوير منتجات',
                'summary' => 'صور منتجات بخلفيات نظيفة جاهزة للمتاجر.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'food-photography',
                'name' => 'تصوير أطعمة',
                'summary' => 'صور أطعمة ومشروبات للمطاعم والمقاهي.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'real-estate-photography',
                'name' => 'تصوير عقارات ومنشآت',
                'summary' => 'تصوير المواقع والمنشآت بزوايا احترافية.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'corporate-photography',
                'name' => 'تصوير شخصي ومؤسسي',
                'summary' => 'صور فريق العمل والصور المؤسسية.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'event-photography',
                'name' => 'تصوير فعاليات',
                'summary' => 'تغطية مصورة للفعاليات والمناسبات.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'ad-video',
                'name' => 'فيديو إعلاني',
                'summary' => 'فيديو إعلاني قصير موجّه للحملات.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'corporate-video',
                'name' => 'فيديو مؤسسي',
                'summary' => 'فيديو تعريفي يشرح المنشأة وخدماتها.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'reels',
                'name' => 'ريلز',
                'summary' => 'مقاطع قصيرة عمودية للمنصات الاجتماعية.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'interviews',
                'name' => 'مقابلات',
                'summary' => 'تصوير مقابلات وشهادات عملاء.',
                'category' => ServiceCategory::Production,
            ],
            [
                'slug' => 'motion-graphics',
                'name' => 'موشن جرافيك',
                'summary' => 'فيديوهات رسوم متحركة لتوضيح الرسائل.',
                'category' => ServiceCategory::Production,
            ],

            // المتاجر الرقمية
            [
                'slug' => 'store-ui-design',
                'name' => 'تصميم واجهة المتجر',
                'summary' => 'واجهة متجر مرتبة تدعم الهوية البصرية.',
                'category' => ServiceCategory::Stores,
            ],
            [
                'slug' => 'ux-improvement',
                'name' => 'تحسين تجربة المستخدم',
                'summary' => 'مراجعة تجربة الاستخدام ومعالجة نقاط التعطل.',
                'category' => ServiceCategory::Stores,
            ],
            [
                'slug' => 'product-pages-setup',
                'name' => 'إعداد صفحات المنتجات',
                'summary' => 'تهيئة صفحات المنتجات بمعلومات كاملة.',
                'category' => ServiceCategory::Stores,
            ],
            [
                'slug' => 'product-descriptions',
                'name' => 'كتابة وصف المنتجات',
                'summary' => 'أوصاف بيعية واضحة لكل منتج.',
                'category' => ServiceCategory::Stores,
            ],
            [
                'slug' => 'product-upload',
                'name' => 'رفع المنتجات',
                'summary' => 'إدخال المنتجات وتصنيفاتها في المتجر.',
                'category' => ServiceCategory::Stores,
            ],
            [
                'slug' => 'landing-page-design',
                'name' => 'تصميم صفحات هبوط',
                'summary' => 'صفحة هبوط مخصصة لحملة أو عرض محدد.',
                'category' => ServiceCategory::Stores,
            ],
            [
                'slug' => 'purchase-journey-optimization',
                'name' => 'تحسين رحلة الشراء',
                'summary' => 'تقليل خطوات الشراء ورفع معدل إتمام الطلب.',
                'category' => ServiceCategory::Stores,
            ],

            // المحتوى
            [
                'slug' => 'video-scripts',
                'name' => 'كتابة نصوص فيديو',
                'summary' => 'سكربتات فيديو مكتوبة للتصوير والمونتاج.',
                'category' => ServiceCategory::Content,
            ],
            [
                'slug' => 'content-ideas',
                'name' => 'أفكار محتوى',
                'summary' => 'بنك أفكار محتوى قابل للتنفيذ.',
                'category' => ServiceCategory::Content,
            ],
            [
                'slug' => 'ad-scenarios',
                'name' => 'سيناريوهات إعلانية',
                'summary' => 'سيناريوهات إعلانية جاهزة للتنفيذ.',
                'category' => ServiceCategory::Content,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            $service = Service::query()->firstOrNew(['slug' => $definition['slug']]);
            $existed = $service->exists;

            $service->fill([
                'name' => $definition['name'],
                'summary' => $definition['summary'] ?? null,
                'description' => $definition['description'] ?? null,
                'category' => $definition['category'],
            ]);

            // Commercial configuration belongs to the owner: it is only seeded
            // when the service is first created, never overwritten afterwards.
            if (! $existed) {
                $price = $definition['base_price'] ?? null;

                $service->fill([
                    'base_price' => $price ?? 0,
                    'currency' => 'SAR',
                    'pricing_mode' => $price === null
                        ? CatalogPricingMode::Quote
                        : CatalogPricingMode::Fixed,
                    'duration_days' => $definition['duration_days'] ?? null,
                    'is_active' => true,
                    'is_featured' => $definition['is_featured'] ?? false,
                ]);
            }

            $service->save();
        }
    }
}
