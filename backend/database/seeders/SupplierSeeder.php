<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            $items = $definition['portfolio'];
            unset($definition['portfolio']);

            $supplier = Supplier::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                $definition,
            );

            $supplier->portfolioItems()->delete();

            foreach ($items as $index => $item) {
                SupplierPortfolioItem::query()->create([
                    ...$item,
                    'supplier_id' => $supplier->id,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'al-ufuq-print',
                'name' => 'مطبعة الأفق',
                'logo' => '/suppliers/logos/ufuq.svg',
                'short_description' => 'طباعة تجارية رقمية وأوفست للعلامات التي تحتاج إنتاجاً واضحاً وسريعاً.',
                'description' => 'مطبعة الأفق شريك إنتاج في الرياض يغطّي الكروت والفلايرز والبوسترات بخطوط واضحة وخامات متعددة، مع متابعة جودة كل تشغيل قبل التسليم.',
                'specialties' => ['الطباعة التجارية', 'الطباعة الرقمية'],
                'services' => ['كروت شخصية', 'فلايرز', 'بوسترات'],
                'location' => 'الرياض',
                'is_featured' => true,
                'is_active' => true,
                'portfolio' => [
                    ['title' => 'كروت هوية تنفيذية', 'description' => 'تشغيل كروت مطفية لشركة استشارية.', 'image' => '/suppliers/portfolio/cards-1.svg', 'category' => 'كروت شخصية'],
                    ['title' => 'فلايرز عرض موسمي', 'description' => 'منشورات A5 لحملة تجزئة.', 'image' => '/suppliers/portfolio/flyers-1.svg', 'category' => 'فلايرز'],
                    ['title' => 'بوسترات نقاط البيع', 'description' => 'بوسترات داخلية لفروع متعددة.', 'image' => '/suppliers/portfolio/posters-1.svg', 'category' => 'بوسترات'],
                ],
            ],
            [
                'slug' => 'golden-pack',
                'name' => 'دار التغليف الذهبية',
                'logo' => '/suppliers/logos/golden.svg',
                'short_description' => 'تغليف منتجات وعلب جاهزة بهوية العلامة، من الكرتون إلى اللمسات الفاخرة.',
                'description' => 'دار التغليف الذهبية في جدة تركّز على حماية المنتج وظهوره في الرف والشحن، مع خامات كرتون وكرافت وطباعة هوية كاملة.',
                'specialties' => ['التغليف', 'العلب'],
                'services' => ['علب', 'تغليف', 'منتجات دعائية'],
                'location' => 'جدة',
                'is_featured' => true,
                'is_active' => true,
                'portfolio' => [
                    ['title' => 'علب منتجات تجميل', 'description' => 'علب كرتون مطبوعة بلمسة مطفية.', 'image' => '/suppliers/portfolio/boxes-1.svg', 'category' => 'علب'],
                    ['title' => 'تغليف شحن موحّد', 'description' => 'حلول تغليف تحمي المنتج أثناء النقل.', 'image' => '/suppliers/portfolio/packaging-1.svg', 'category' => 'تغليف'],
                    ['title' => 'علب هدايا موسمية', 'description' => 'علب تقديم بهوية الحملة.', 'image' => '/suppliers/portfolio/boxes-2.svg', 'category' => 'علب'],
                ],
            ],
            [
                'slug' => 'sticker-studio',
                'name' => 'استوديو الملصق',
                'logo' => '/suppliers/logos/sticker.svg',
                'short_description' => 'استيكرات وليبل منتجات بقصّات دقيقة لعلامات التجزئة والأغذية.',
                'description' => 'استوديو الملصق في الدمام ينتج ملصقات فينيل وورق بقص خاص ودائري ومستطيل، مناسب للعبوات والفعاليات.',
                'specialties' => ['الاستيكرات', 'الطباعة الرقمية'],
                'services' => ['استيكرات', 'منتجات دعائية'],
                'location' => 'الدمام',
                'is_featured' => false,
                'is_active' => true,
                'portfolio' => [
                    ['title' => 'ليبل عبوات غذائية', 'description' => 'ملصقات مقاومة للرطوبة لخط إنتاج.', 'image' => '/suppliers/portfolio/stickers-1.svg', 'category' => 'استيكرات'],
                    ['title' => 'استيكرات قص خاص', 'description' => 'قص يتبع شكل الشعار بدقة.', 'image' => '/suppliers/portfolio/stickers-2.svg', 'category' => 'استيكرات'],
                    ['title' => 'ملصقات فعالية', 'description' => 'تشغيل سريع لفعالية يوم واحد.', 'image' => '/suppliers/portfolio/stickers-3.svg', 'category' => 'استيكرات'],
                ],
            ],
            [
                'slug' => 'elegant-boxes',
                'name' => 'ورشة العلب الأنيقة',
                'logo' => '/suppliers/logos/boxes.svg',
                'short_description' => 'علب قابلة للطي وعلب هدايا بتشطيب نظيف يناسب المنتجات الفاخرة.',
                'description' => 'ورشة العلب الأنيقة في الرياض تصمّم وتنفّذ علب كرتون للعلامات التي تريد حضوراً أقوى عند فتح المنتج.',
                'specialties' => ['العلب', 'الطباعة الفاخرة'],
                'services' => ['علب', 'تغليف'],
                'location' => 'الرياض',
                'is_featured' => false,
                'is_active' => true,
                'portfolio' => [
                    ['title' => 'علب قابلة للطي', 'description' => 'علب خفيفة للشحن والعرض.', 'image' => '/suppliers/portfolio/boxes-1.svg', 'category' => 'علب'],
                    ['title' => 'علب عطور', 'description' => 'تشغيل فاخر بلمسة لامعة.', 'image' => '/suppliers/portfolio/boxes-2.svg', 'category' => 'علب'],
                    ['title' => 'تغليف مجموعة هدايا', 'description' => 'مجموعة علب متناسقة لحملة.', 'image' => '/suppliers/portfolio/packaging-1.svg', 'category' => 'تغليف'],
                ],
            ],
            [
                'slug' => 'nakhlah-bags',
                'name' => 'أكياس النخلة',
                'logo' => '/suppliers/logos/bags.svg',
                'short_description' => 'أكياس ورقية وكرافت مطبوعة بهوية المتجر وتجربة الاستلام.',
                'description' => 'أكياس النخلة في جدة تنتج أكياس تسوّق عملية وفاخرة، من الكرافت اليومي إلى الأكياس المطبوعة بالكامل.',
                'specialties' => ['الأكياس الورقية', 'التغليف'],
                'services' => ['أكياس', 'تغليف'],
                'location' => 'جدة',
                'is_featured' => false,
                'is_active' => true,
                'portfolio' => [
                    ['title' => 'أكياس كرافت يومية', 'description' => 'أكياس متينة لنقاط البيع.', 'image' => '/suppliers/portfolio/bags-1.svg', 'category' => 'أكياس'],
                    ['title' => 'أكياس تسوق فاخرة', 'description' => 'مقبض متين وطباعة أوضح.', 'image' => '/suppliers/portfolio/bags-2.svg', 'category' => 'أكياس'],
                    ['title' => 'أكياس حملة موسمية', 'description' => 'طباعة كاملة لألوان الحملة.', 'image' => '/suppliers/portfolio/bags-1.svg', 'category' => 'أكياس'],
                ],
            ],
            [
                'slug' => 'mashhad-luxury',
                'name' => 'طباعة المشهد الفاخرة',
                'logo' => '/suppliers/logos/mashhad.svg',
                'short_description' => 'طباعة فاخرة للكروت والمواد التي تمثّل الهوية في المناسبات الخاصة.',
                'description' => 'طباعة المشهد الفاخرة تركّز على الخامات الثقيلة والتشطيب اللامع والمطفي للكروت والمنتجات الدعائية الراقية.',
                'specialties' => ['الطباعة الفاخرة', 'الطباعة التجارية'],
                'services' => ['كروت شخصية', 'منتجات دعائية', 'فلايرز'],
                'location' => 'الرياض',
                'is_featured' => true,
                'is_active' => true,
                'portfolio' => [
                    ['title' => 'كروت فاخرة مطلية', 'description' => 'تشطيب لامع لكروت مناسبة رسمية.', 'image' => '/suppliers/portfolio/cards-2.svg', 'category' => 'كروت شخصية'],
                    ['title' => 'فلايرز حضور عالٍ', 'description' => 'ورق أثقل لعروض الشركات.', 'image' => '/suppliers/portfolio/flyers-1.svg', 'category' => 'فلايرز'],
                    ['title' => 'مواد دعائية للشركاء', 'description' => 'مجموعة مطبوعات لاجتماع سنوي.', 'image' => '/suppliers/portfolio/promo-1.svg', 'category' => 'منتجات دعائية'],
                ],
            ],
            [
                'slug' => 'athar-gifts',
                'name' => 'هدايا الأثر',
                'logo' => '/suppliers/logos/athar.svg',
                'short_description' => 'مواد دعائية وهدايا مؤسسية مطبوعة بهوية الشركة للفعاليات والشراكات.',
                'description' => 'هدايا الأثر في الخبر تنتج مواداً دعائية عملية وأنيقة: من الملصقات إلى المنتجات الترويجية المرتبطة بالطباعة والتغليف.',
                'specialties' => ['المواد الدعائية', 'الهدايا المؤسسية'],
                'services' => ['منتجات دعائية', 'استيكرات', 'كروت شخصية'],
                'location' => 'الخبر',
                'is_featured' => false,
                'is_active' => true,
                'portfolio' => [
                    ['title' => 'مجموعة ترحيب للشركاء', 'description' => 'كروت وملصقات لهوية موحّدة.', 'image' => '/suppliers/portfolio/promo-1.svg', 'category' => 'منتجات دعائية'],
                    ['title' => 'استيكرات فعالية', 'description' => 'ملصقات توزيع سريعة.', 'image' => '/suppliers/portfolio/stickers-3.svg', 'category' => 'استيكرات'],
                    ['title' => 'كروت شكر مؤسسية', 'description' => 'كروت أنيقة للمناسبات.', 'image' => '/suppliers/portfolio/cards-1.svg', 'category' => 'كروت شخصية'],
                ],
            ],
            [
                'slug' => 'waha-packaging',
                'name' => 'تغليف الواحة',
                'logo' => '/suppliers/logos/waha.svg',
                'short_description' => 'تغليف غذائي وتجاري يحمي المنتج ويظهره بشكل مرتب في نقاط البيع.',
                'description' => 'تغليف الواحة في المدينة يخدم علامات الأغذية والمنتجات الاستهلاكية بتغليف واضح وخامات مناسبة للعرض اليومي.',
                'specialties' => ['التغليف', 'الطباعة التجارية'],
                'services' => ['تغليف', 'علب', 'استيكرات'],
                'location' => 'المدينة المنورة',
                'is_featured' => false,
                'is_active' => true,
                'portfolio' => [
                    ['title' => 'تغليف غذائي يومي', 'description' => 'عبوات عرض لنقاط البيع.', 'image' => '/suppliers/portfolio/packaging-2.svg', 'category' => 'تغليف'],
                    ['title' => 'علب استهلاكية', 'description' => 'علب كرتون لمنتج غذائي.', 'image' => '/suppliers/portfolio/boxes-1.svg', 'category' => 'علب'],
                    ['title' => 'ليبل المنتج', 'description' => 'ملصقات تعريف للعبوة.', 'image' => '/suppliers/portfolio/stickers-1.svg', 'category' => 'استيكرات'],
                ],
            ],
        ];
    }
}
