<?php

namespace Database\Seeders;

use App\Models\EventType;
use App\Models\PrintingProduct;
use App\Models\PrintingProductCategory;
use App\Models\PrintingProductOption;
use Illuminate\Database\Seeder;

/**
 * Seeds public printing catalog + event types from known platform content.
 * Prices match the existing frontend catalog only — nothing invented.
 */
class PlatformCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'business-cards', 'name_ar' => 'كروت', 'name_en' => 'Business cards', 'sort_order' => 10],
            ['slug' => 'flyers', 'name_ar' => 'بروشورات', 'name_en' => 'Flyers', 'sort_order' => 20],
            ['slug' => 'stickers', 'name_ar' => 'استيكرات', 'name_en' => 'Stickers', 'sort_order' => 30],
            ['slug' => 'menus', 'name_ar' => 'منيو', 'name_en' => 'Menus', 'sort_order' => 40],
            ['slug' => 'bags', 'name_ar' => 'أكياس', 'name_en' => 'Bags', 'sort_order' => 50],
            ['slug' => 'boxes', 'name_ar' => 'علب', 'name_en' => 'Boxes', 'sort_order' => 60],
            ['slug' => 'cups', 'name_ar' => 'أكواب', 'name_en' => 'Cups', 'sort_order' => 70],
            ['slug' => 'posters', 'name_ar' => 'بوسترات', 'name_en' => 'Posters', 'sort_order' => 80],
            ['slug' => 'promo', 'name_ar' => 'مواد دعائية', 'name_en' => 'Promo', 'sort_order' => 90],
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
            ['type' => 'material', 'slug' => 'matte-paper', 'name_ar' => 'ورق مطفي'],
            ['type' => 'material', 'slug' => 'glossy-paper', 'name_ar' => 'ورق لامع'],
            ['type' => 'material', 'slug' => 'cardstock', 'name_ar' => 'كرتون'],
            ['type' => 'size', 'slug' => 'standard-size', 'name_ar' => 'قياس قياسي'],
            ['type' => 'size', 'slug' => 'custom-size', 'name_ar' => 'مخصص'],
            ['type' => 'size', 'slug' => 'a5', 'name_ar' => 'A5'],
            ['type' => 'finishing', 'slug' => 'none', 'name_ar' => 'بدون تشطيب'],
            ['type' => 'method', 'slug' => 'offset', 'name_ar' => 'أوفست'],
            ['type' => 'color', 'slug' => 'full-color', 'name_ar' => 'ألوان كاملة'],
            ['type' => 'quantity', 'slug' => 'qty-100', 'name_ar' => '100'],
            ['type' => 'quantity', 'slug' => 'qty-500', 'name_ar' => '500'],
        ];

        $optionIds = [];
        foreach ($optionDefs as $index => $row) {
            $option = PrintingProductOption::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'is_active' => true, 'sort_order' => ($index + 1) * 10],
            );
            $optionIds[$row['slug']] = $option->id;
        }

        $products = [
            ['slug' => 'standard-business-cards', 'category' => 'business-cards', 'name_ar' => 'كروت شخصية قياسية', 'short' => 'كروت تعريف يومية بلمسة نظيفة تناسب الاستخدام اليومي.', 'price' => 85, 'image' => '/printing/business-cards.svg', 'options' => ['matte-paper', 'glossy-paper', 'standard-size', 'custom-size']],
            ['slug' => 'premium-business-cards', 'category' => 'business-cards', 'name_ar' => 'كروت شخصية فاخرة', 'short' => 'خامة أثقل ولمسة أنعم لحضور أقوى في اللقاءات.', 'price' => 180, 'image' => '/printing/business-cards-premium.svg', 'options' => ['cardstock', 'matte-paper', 'standard-size', 'custom-size']],
            ['slug' => 'a5-flyers', 'category' => 'flyers', 'name_ar' => 'فلايرز A5', 'short' => 'منشورات بحجم عملي للتوزيع في الفعاليات ونقاط البيع.', 'price' => 120, 'image' => '/printing/flyers.svg', 'options' => ['matte-paper', 'glossy-paper', 'a5', 'custom-size']],
        ];

        foreach ($products as $index => $row) {
            $product = PrintingProduct::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'category_id' => $categoryIds[$row['category']] ?? null,
                    'name_ar' => $row['name_ar'],
                    'short_description' => $row['short'],
                    'image_path' => $row['image'],
                    'pricing_mode' => 'STARTING_FROM',
                    'starting_price' => $row['price'],
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

        $eventTypes = [
            ['slug' => 'opening', 'name_ar' => 'افتتاح', 'sort_order' => 10],
            ['slug' => 'conference', 'name_ar' => 'مؤتمر', 'sort_order' => 20],
            ['slug' => 'exhibition', 'name_ar' => 'معرض', 'sort_order' => 30],
            ['slug' => 'product-launch', 'name_ar' => 'إطلاق منتج', 'sort_order' => 40],
            ['slug' => 'internal-event', 'name_ar' => 'فعالية داخلية', 'sort_order' => 50],
            ['slug' => 'occasion', 'name_ar' => 'مناسبة', 'sort_order' => 60],
        ];

        foreach ($eventTypes as $row) {
            EventType::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'is_active' => true, 'is_public' => true],
            );
        }
    }
}
