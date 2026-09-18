<?php

namespace Database\Seeders;

use App\Models\PrintingProduct;
use App\Models\PrintingProductCategory;
use App\Models\PrintingProductOption;
use Illuminate\Database\Seeder;

/**
 * Official Hebr & Ab3ad printing/packaging catalog (15 products).
 * Legacy sample products remain in the table but are unpublished.
 */
class OfficialPrintingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'business-cards', 'name_ar' => 'كروت', 'name_en' => 'Business cards', 'sort_order' => 10],
            ['slug' => 'flyers', 'name_ar' => 'بروشورات', 'name_en' => 'Brochures', 'sort_order' => 20],
            ['slug' => 'stickers', 'name_ar' => 'استيكرات', 'name_en' => 'Stickers', 'sort_order' => 30],
            ['slug' => 'menus', 'name_ar' => 'منيو', 'name_en' => 'Menus', 'sort_order' => 40],
            ['slug' => 'bags', 'name_ar' => 'أكياس', 'name_en' => 'Bags', 'sort_order' => 50],
            ['slug' => 'boxes', 'name_ar' => 'علب', 'name_en' => 'Boxes', 'sort_order' => 60],
            ['slug' => 'cups', 'name_ar' => 'أكواب', 'name_en' => 'Cups', 'sort_order' => 70],
            ['slug' => 'posters', 'name_ar' => 'رول أب وبنرات', 'name_en' => 'Rollups & banners', 'sort_order' => 80],
            ['slug' => 'promo', 'name_ar' => 'هدايا دعائية', 'name_en' => 'Promo', 'sort_order' => 90],
            ['slug' => 'packaging', 'name_ar' => 'تغليف', 'name_en' => 'Packaging', 'sort_order' => 100],
            ['slug' => 'custom', 'name_ar' => 'منتجات مخصصة', 'name_en' => 'Custom', 'sort_order' => 110],
        ];

        $categoryIds = [];
        foreach ($categories as $row) {
            $category = PrintingProductCategory::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'is_active' => true],
            );
            $categoryIds[$row['slug']] = $category->id;
        }

        $optionDefs = [
            ['type' => 'size', 'slug' => 'size-9x5', 'name_ar' => '9 × 5 سم'],
            ['type' => 'size', 'slug' => 'size-a4', 'name_ar' => 'A4'],
            ['type' => 'size', 'slug' => 'size-a5', 'name_ar' => 'A5'],
            ['type' => 'size', 'slug' => 'custom-size', 'name_ar' => 'مخصص'],
            ['type' => 'material', 'slug' => 'matte-paper', 'name_ar' => 'ورق مطفي'],
            ['type' => 'material', 'slug' => 'glossy-paper', 'name_ar' => 'ورق لامع'],
            ['type' => 'material', 'slug' => 'cardstock', 'name_ar' => 'ورق مقوى'],
            ['type' => 'material', 'slug' => 'kraft-paper', 'name_ar' => 'كرافت'],
            ['type' => 'material', 'slug' => 'corrugated', 'name_ar' => 'كرتون مموج'],
            ['type' => 'finishing', 'slug' => 'spot-uv', 'name_ar' => 'سبوت UV'],
            ['type' => 'finishing', 'slug' => 'foil', 'name_ar' => 'ختم حراري'],
            ['type' => 'finishing', 'slug' => 'none', 'name_ar' => 'بدون تشطيب'],
            ['type' => 'method', 'slug' => 'one-side', 'name_ar' => 'وجه واحد'],
            ['type' => 'method', 'slug' => 'two-sides', 'name_ar' => 'وجهان'],
            ['type' => 'quantity', 'slug' => 'qty-100', 'name_ar' => '100'],
            ['type' => 'quantity', 'slug' => 'qty-300', 'name_ar' => '300'],
            ['type' => 'quantity', 'slug' => 'qty-500', 'name_ar' => '500'],
            ['type' => 'quantity', 'slug' => 'qty-800', 'name_ar' => '800'],
            ['type' => 'quantity', 'slug' => 'qty-1000', 'name_ar' => '1,000'],
            ['type' => 'quantity', 'slug' => 'qty-1500', 'name_ar' => '1,500'],
            ['type' => 'quantity', 'slug' => 'qty-2000', 'name_ar' => '2,000'],
            ['type' => 'quantity', 'slug' => 'qty-3000', 'name_ar' => '3,000'],
            ['type' => 'quantity', 'slug' => 'qty-5000', 'name_ar' => '5,000'],
            ['type' => 'quantity', 'slug' => 'qty-10000', 'name_ar' => '10,000'],
        ];

        $optionIds = [];
        foreach ($optionDefs as $index => $row) {
            $option = PrintingProductOption::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'is_active' => true, 'sort_order' => ($index + 1) * 10],
            );
            $optionIds[$row['slug']] = $option->id;
        }

        foreach (['standard-business-cards', 'premium-business-cards', 'a5-flyers'] as $legacySlug) {
            PrintingProduct::query()->where('slug', $legacySlug)->update([
                'is_public' => false,
                'is_featured' => false,
            ]);
        }

        $qty = ['qty-100', 'qty-300', 'qty-500', 'qty-800', 'qty-1000', 'qty-1500', 'qty-2000', 'qty-3000', 'qty-5000', 'qty-10000'];

        $products = [
            [
                'slug' => 'luxury-business-cards',
                'category' => 'business-cards',
                'name_ar' => 'كروت أعمال فاخرة',
                'short' => 'كروت احترافية بخيارات طباعة وتشطيب متعددة تعكس قيمة العلامة من أول لقاء.',
                'description' => "المقاس الشائع: 9 × 5 سم.\nالخامة: ورق مقوى بخيارات متعددة.\nالطباعة: وجه واحد أو وجهان.\nالتشطيب: مطفي، لامع، سبوت UV أو ختم حراري.\nوحدة التسعير: بالعدد.\nالكمية: حسب خيارات المورد (100 حتى 10,000 وأكثر).\nالتنفيذ: بعد اعتماد التصميم والبروفة.\nالتصميم والعينة والشحن والحد الأدنى للطلب تُحدَّد مع الطلب.",
                'image' => '/printing/business-cards-luxury.svg',
                'options' => array_merge(['size-9x5', 'custom-size', 'cardstock', 'matte-paper', 'glossy-paper', 'spot-uv', 'foil', 'one-side', 'two-sides'], $qty),
            ],
            [
                'slug' => 'company-brochure-printing',
                'category' => 'flyers',
                'name_ar' => 'بروفايل وبروشور تعريفي',
                'short' => 'بروشور منظم يعرض خدمات المنشأة ومميزاتها ويستخدم في الاجتماعات والمعارض ونقاط البيع.',
                'description' => "الخيارات: A4، A5، مطوي، تدبيس، وجه واحد أو وجهان.\nوحدة التسعير: بالنسخة أو الكمية.\nالمقاس والخامة والسماكة ونوع الطباعة وعدد الألوان والتشطيب والكمية ومدة التنفيذ والتصميم والعينة والشحن والحد الأدنى للطلب تُعتمد قبل التسعير.",
                'image' => '/printing/flyers-premium.svg',
                'options' => array_merge(['size-a4', 'size-a5', 'custom-size', 'matte-paper', 'glossy-paper', 'one-side', 'two-sides'], $qty),
            ],
            [
                'slug' => 'corporate-stationery-printing',
                'category' => 'custom',
                'name_ar' => 'مطبوعات الهوية المكتبية',
                'short' => 'باقة مؤسسية تشمل الورق الرسمي والأظرف والفولدرات والدفاتر والسندات بما يتوافق مع هوية المنشأة.',
                'description' => "وحدة التسعير: لكل منتج أو باقة متكاملة.\nالمقاس والخامة والسماكة ونوع الطباعة والتشطيب والكمية ومدة التنفيذ والتصميم والعينة والشحن والحد الأدنى للطلب تُعتمد قبل التسعير.",
                'image' => '/printing/custom.svg',
                'options' => array_merge(['custom-size', 'matte-paper', 'cardstock'], $qty),
            ],
            [
                'slug' => 'custom-paper-bags',
                'category' => 'bags',
                'name_ar' => 'أكياس ورقية مخصصة',
                'short' => 'أكياس مطبوعة بشعارك مناسبة للمتاجر والعطور والهدايا والمنتجات الفاخرة.',
                'description' => "مقاسات صغيرة ومتوسطة وكبيرة.\nخامة كرافت أو ورق أبيض.\nمقابض حبلية أو شريطية.\nطباعة بلون أو عدة ألوان.\nتشطيب مطفي أو لامع.\nالتسعير بالحبة حسب الكمية.",
                'image' => '/printing/bags-luxury.svg',
                'options' => array_merge(['custom-size', 'kraft-paper', 'matte-paper', 'glossy-paper'], $qty),
            ],
            [
                'slug' => 'printed-plastic-bags',
                'category' => 'bags',
                'name_ar' => 'أكياس بلاستيكية مطبوعة',
                'short' => 'أكياس عملية واقتصادية للمطاعم والصيدليات والمتاجر والطلبات اليومية.',
                'description' => "مقاسات وسماكات متعددة.\nيد مفتوحة أو مقصوصة.\nطباعة شعار أو تصميم.\nالتسعير بالكيلو أو الحبة.\nالتنفيذ حسب الكمية والألوان.",
                'image' => '/printing/bags.svg',
                'options' => array_merge(['custom-size'], $qty),
            ],
            [
                'slug' => 'ecommerce-shipping-boxes',
                'category' => 'boxes',
                'name_ar' => 'كراتين شحن للمتاجر',
                'short' => 'كراتين قوية لحماية الطلبات أثناء النقل، مع إمكانية طباعة الشعار وإضافة تجربة فتح مميزة.',
                'description' => "مقاسات قياسية أو مخصصة، كرتون مموج، طباعة خارجية، فواصل اختيارية.\nالتسعير: بالحبة حسب المقاس والكمية.",
                'image' => '/printing/boxes.svg',
                'options' => array_merge(['custom-size', 'corrugated'], $qty),
            ],
            [
                'slug' => 'custom-product-boxes',
                'category' => 'boxes',
                'name_ar' => 'علب منتجات مخصصة',
                'short' => 'علب مصممة حسب مقاس المنتج، مناسبة للعطور والأغذية والعناية والهدايا.',
                'description' => "كرتون مطوي أو مقوى، نافذة اختيارية، سلوفان، ختم حراري أو UV.\nالتسعير: بالحبة بعد اعتماد المقاس والعينة.",
                'image' => '/printing/boxes-product.svg',
                'options' => array_merge(['custom-size', 'cardstock', 'spot-uv', 'foil'], $qty),
            ],
            [
                'slug' => 'luxury-gift-boxes',
                'category' => 'boxes',
                'name_ar' => 'بوكسات هدايا فاخرة',
                'short' => 'بوكسات صلبة بتشطيبات أنيقة وبطانة أو فواصل داخلية، مناسبة للهدايا والمنتجات الراقية.',
                'description' => "التسعير: بالحبة حسب المقاس والخامة والتشطيب.\nالمقاس والخامة والسماكة ونوع الطباعة والتشطيب والكمية ومدة التنفيذ والتصميم والعينة والشحن والحد الأدنى للطلب تُعتمد قبل التسعير.",
                'image' => '/printing/boxes-gift.svg',
                'options' => array_merge(['custom-size', 'cardstock', 'spot-uv', 'foil'], $qty),
            ],
            [
                'slug' => 'food-packaging-boxes',
                'category' => 'packaging',
                'name_ar' => 'علب مطاعم وحلويات',
                'short' => 'تغليف مناسب للتلامس الغذائي للوجبات والبرجر والحلويات والمخبوزات.',
                'description' => 'أحجام وخامات متعددة، طباعة حسب الكمية، فتحات تهوية أو نوافذ عند الحاجة.',
                'image' => '/printing/packaging-food.svg',
                'options' => array_merge(['custom-size'], $qty),
            ],
            [
                'slug' => 'printed-paper-cups',
                'category' => 'cups',
                'name_ar' => 'أكواب ورقية مطبوعة',
                'short' => 'أكواب للمشروبات الساخنة والباردة تحمل شعار المقهى أو الفعالية.',
                'description' => "المقاسات: 4، 7، 8، 12، 16 أونصة حسب المورد.\nالخامة: طبقة واحدة أو مزدوجة.\nالتسعير: بالحبة أو الكرتون.",
                'image' => '/printing/custom.svg',
                'options' => array_merge(['custom-size'], $qty),
            ],
            [
                'slug' => 'product-stickers-labels',
                'category' => 'stickers',
                'name_ar' => 'استكرات وملصقات المنتجات',
                'short' => 'ملصقات تعرض اسم المنتج والمعلومات والباركود وتمنح العبوة مظهرًا جاهزًا للبيع.',
                'description' => "الخيارات: ورقي، شفاف، مقاوم للماء، قص دائري أو مخصص.\nالتسعير: بالعدد أو بالمتر المربع.",
                'image' => '/printing/stickers-labels.svg',
                'options' => array_merge(['custom-size', 'matte-paper', 'glossy-paper'], $qty),
            ],
            [
                'slug' => 'roll-label-printing',
                'category' => 'stickers',
                'name_ar' => 'رول ليبل للعبوات',
                'short' => 'ملصقات على رول مناسبة للإنتاج المتكرر والتطبيق اليدوي أو الآلي.',
                'description' => "الخيارات: ورقي، شفاف، مقاوم للرطوبة، طباعة لون أو عدة ألوان.\nالتسعير: بالرول أو العدد.",
                'image' => '/printing/stickers.svg',
                'options' => array_merge(['custom-size'], $qty),
            ],
            [
                'slug' => 'restaurant-menu-printing',
                'category' => 'menus',
                'name_ar' => 'منيو مطاعم ومقاهي',
                'short' => 'تصميم وطباعة منيو واضح بخامات تتحمل الاستخدام المتكرر، مع إمكانية إضافة QR.',
                'description' => "التسعير: بالنسخة حسب المقاس والصفحات والتشطيب.\nالمقاس والخامة والسماكة ونوع الطباعة والتشطيب والكمية ومدة التنفيذ والتصميم والعينة والشحن والحد الأدنى للطلب تُعتمد قبل التسعير.",
                'image' => '/printing/custom.svg',
                'options' => array_merge(['size-a4', 'size-a5', 'custom-size', 'matte-paper', 'glossy-paper', 'cardstock'], $qty),
            ],
            [
                'slug' => 'rollup-banner-printing',
                'category' => 'posters',
                'name_ar' => 'رول أب وبنرات',
                'short' => 'حلول عرض للمعارض والافتتاحات ونقاط البيع تشمل الطباعة والتجهيز.',
                'description' => "التسعير: الرول أب بالوحدة، والبنرات بالمتر المربع.\nالمقاس والخامة ونوع الطباعة والتشطيب والكمية ومدة التنفيذ والتصميم والعينة والشحن والحد الأدنى للطلب تُعتمد قبل التسعير.",
                'image' => '/printing/posters-large.svg',
                'options' => ['custom-size'],
            ],
            [
                'slug' => 'promotional-gifts-printing',
                'category' => 'promo',
                'name_ar' => 'هدايا دعائية مطبوعة',
                'short' => 'أقلام ودفاتر وأكواب وحقائب ومنتجات دعائية تحمل هوية المنشأة.',
                'description' => "التسعير: بالحبة حسب المنتج والكمية والطباعة.\nالمقاس والخامة ونوع الطباعة والتشطيب والكمية ومدة التنفيذ والتصميم والعينة والشحن والحد الأدنى للطلب تُعتمد قبل التسعير.",
                'image' => '/printing/custom-promo.svg',
                'options' => array_merge(['custom-size'], $qty),
            ],
        ];

        foreach ($products as $index => $row) {
            $product = PrintingProduct::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'category_id' => $categoryIds[$row['category']] ?? null,
                    'name_ar' => $row['name_ar'],
                    'short_description' => $row['short'],
                    'description' => $row['description'],
                    'image_path' => $row['image'],
                    'pricing_mode' => 'QUOTE',
                    'starting_price' => null,
                    'currency' => 'SAR',
                    'is_active' => true,
                    'is_public' => true,
                    'is_featured' => $index === 0,
                    'allows_design_and_print' => true,
                    'sort_order' => ($index + 1) * 10,
                ],
            );
            $ids = array_values(array_filter(array_map(
                fn (string $slug) => $optionIds[$slug] ?? null,
                $row['options'],
            )));
            $product->options()->sync($ids);
        }
    }
}
