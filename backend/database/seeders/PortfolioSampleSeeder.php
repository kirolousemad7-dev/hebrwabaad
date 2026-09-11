<?php

namespace Database\Seeders;

use App\Enums\PortfolioCategory;
use App\Models\PortfolioItem;
use Illuminate\Database\Seeder;

class PortfolioSampleSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            [
                'title' => 'نموذج عرض — متجر إلكتروني',
                'category' => PortfolioCategory::Web,
                'description' => 'نموذج بصري لفئة تطوير المتاجر. ليس مشروعاً لعميل حقيقي.',
                'tags' => ['نموذج عرض', 'متاجر'],
                'image_url' => '/printing/boxes-product.svg',
                'sort_order' => 1,
            ],
            [
                'title' => 'نموذج عرض — هوية بصرية',
                'category' => PortfolioCategory::Branding,
                'description' => 'نموذج بصري لفئة بناء الهوية. ليس مشروعاً لعميل حقيقي.',
                'tags' => ['نموذج عرض', 'هوية'],
                'image_url' => '/brand/logo.png',
                'sort_order' => 2,
            ],
            [
                'title' => 'نموذج عرض — محتوى سوشيال',
                'category' => PortfolioCategory::Social,
                'description' => 'نموذج بصري لفئة المحتوى الاجتماعي. ليس مشروعاً لعميل حقيقي.',
                'tags' => ['نموذج عرض', 'سوشيال'],
                'image_url' => '/printing/flyers.svg',
                'sort_order' => 3,
            ],
            [
                'title' => 'نموذج عرض — فيديو إعلاني',
                'category' => PortfolioCategory::Video,
                'description' => 'نموذج بصري لفئة الفيديو. ليس مشروعاً لعميل حقيقي.',
                'tags' => ['نموذج عرض', 'فيديو'],
                'image_url' => '/printing/custom-promo.svg',
                'sort_order' => 4,
            ],
            [
                'title' => 'نموذج عرض — حملة تسويقية',
                'category' => PortfolioCategory::Marketing,
                'description' => 'نموذج بصري لفئة الحملات. ليس مشروعاً لعميل حقيقي.',
                'tags' => ['نموذج عرض', 'تسويق'],
                'image_url' => '/printing/flyers-premium.svg',
                'sort_order' => 5,
            ],
            [
                'title' => 'نموذج عرض — تغليف مطبوع',
                'category' => PortfolioCategory::Printing,
                'description' => 'نموذج بصري لفئة الطباعة والتغليف. ليس مشروعاً لعميل حقيقي.',
                'tags' => ['نموذج عرض', 'طباعة'],
                'image_url' => '/printing/bags-luxury.svg',
                'sort_order' => 6,
            ],
        ];

        foreach ($samples as $sample) {
            PortfolioItem::query()->firstOrCreate(
                ['title' => $sample['title']],
                [
                    ...$sample,
                    'is_sample' => true,
                    'is_published' => true,
                ],
            );
        }
    }
}
