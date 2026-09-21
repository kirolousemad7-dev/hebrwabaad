<?php

namespace Database\Seeders;

use App\Enums\MarketingSectionType;
use App\Models\MarketingContent;
use App\Models\MarketingSection;
use App\Models\User;
use App\Services\Marketing\MarketingMediaLibraryService;
use Illuminate\Database\Seeder;

/**
 * Seeds marketing section structure + content keys from the current landing hardcodes.
 * Registers known local public assets as Media library registry rows without moving SPA files.
 */
class MarketingSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['key' => 'hero', 'admin_title' => 'Hero', 'type' => MarketingSectionType::Hero, 'sort_order' => 10],
            ['key' => 'services', 'admin_title' => 'Services showcase', 'type' => MarketingSectionType::Services, 'sort_order' => 20],
            ['key' => 'why-us', 'admin_title' => 'Why us', 'type' => MarketingSectionType::WhyUs, 'sort_order' => 30],
            ['key' => 'packages', 'admin_title' => 'Packages preview', 'type' => MarketingSectionType::Packages, 'sort_order' => 40],
            ['key' => 'build-package', 'admin_title' => 'Build package teaser', 'type' => MarketingSectionType::BuildPackage, 'sort_order' => 50],
            ['key' => 'process', 'admin_title' => 'Process / journey', 'type' => MarketingSectionType::Process, 'sort_order' => 60],
            ['key' => 'portfolio', 'admin_title' => 'Portfolio section', 'type' => MarketingSectionType::Portfolio, 'sort_order' => 70],
            ['key' => 'suppliers', 'admin_title' => 'Suppliers network', 'type' => MarketingSectionType::Suppliers, 'sort_order' => 80],
            ['key' => 'about', 'admin_title' => 'About teaser', 'type' => MarketingSectionType::About, 'sort_order' => 90],
            ['key' => 'final-cta', 'admin_title' => 'Final CTA', 'type' => MarketingSectionType::FinalCta, 'sort_order' => 100],
            ['key' => 'contact', 'admin_title' => 'Contact section', 'type' => MarketingSectionType::Contact, 'sort_order' => 110],
        ];

        foreach ($sections as $row) {
            MarketingSection::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'admin_title' => $row['admin_title'],
                    'type' => $row['type'],
                    'is_enabled' => true,
                    'sort_order' => $row['sort_order'],
                    'config' => [
                        'source' => 'landing_hardcode_v3',
                        'notes' => 'Phase 1 seed — frontend still uses local hardcodes until Phase 3 wiring.',
                    ],
                ],
            );
        }

        $this->seedContents();
        $this->registerDefaultAssets();
    }

    private function seedContents(): void
    {
        $map = [
            'hero' => [
                ['content_key' => 'heading', 'value_text' => 'نمنح أعمالك أبعادًا للنمو', 'sort_order' => 1],
                ['content_key' => 'eyebrow_fallback', 'value_text' => 'حبر وأبعاد للطباعة والتصميم', 'sort_order' => 2],
                ['content_key' => 'description', 'value_text' => 'منصة متكاملة تبدأ بتشخيص نشاطك، ثم تخطيط النمو واختيار الخدمات والموردين، وصولًا إلى التنفيذ والقياس والمتابعة.', 'sort_order' => 3],
                ['content_key' => 'cta_primary', 'value_text' => 'اكتشف احتياجك', 'sort_order' => 4],
                ['content_key' => 'cta_secondary', 'value_text' => 'تصفح الخدمات', 'sort_order' => 5],
                ['content_key' => 'visual_image', 'value_text' => null, 'sort_order' => 6, 'metadata' => ['visual_key' => 'hero', 'local_public_path' => '/marketing/storefront.jpg']],
            ],
            'services' => [
                ['content_key' => 'eyebrow', 'value_text' => 'SERVICES', 'sort_order' => 1],
                ['content_key' => 'title', 'value_text' => 'خدمات تبني حضور علامتك', 'sort_order' => 2],
                ['content_key' => 'description', 'value_text' => 'اختر محورًا واستكشف كيف ننفّذه — من التشخيص والهوية إلى الطباعة والفعاليات.', 'sort_order' => 3],
                ['content_key' => 'visual_strategy', 'value_text' => null, 'sort_order' => 10, 'metadata' => ['visual_key' => 'services.strategy', 'local_public_path' => '/marketing/storefront.jpg']],
                ['content_key' => 'visual_branding', 'value_text' => null, 'sort_order' => 11, 'metadata' => ['visual_key' => 'services.branding']],
                ['content_key' => 'visual_digital', 'value_text' => null, 'sort_order' => 12, 'metadata' => ['visual_key' => 'services.digital']],
                ['content_key' => 'visual_ecommerce', 'value_text' => null, 'sort_order' => 13, 'metadata' => ['visual_key' => 'services.ecommerce']],
                ['content_key' => 'visual_printing', 'value_text' => null, 'sort_order' => 14, 'metadata' => ['visual_key' => 'services.printing']],
                ['content_key' => 'visual_events', 'value_text' => null, 'sort_order' => 15, 'metadata' => ['visual_key' => 'services.events']],
                ['content_key' => 'note', 'value_text' => 'Landing service cards remain hardcoded; catalog Services API is the domain source of truth. Presentation images only via visual_* keys.', 'sort_order' => 90, 'metadata' => ['reuse' => 'Service model /api/services']],
            ],
            'why-us' => [
                ['content_key' => 'eyebrow', 'value_text' => 'لماذا نحن', 'sort_order' => 1],
                ['content_key' => 'title', 'value_text' => 'لماذا حبر وأبعاد؟', 'sort_order' => 2],
                ['content_key' => 'item_1_title', 'value_text' => 'حلول متكاملة', 'sort_order' => 10],
                ['content_key' => 'item_1_description', 'value_text' => 'كل خدمات مشروعك تحت إدارة واحدة.', 'sort_order' => 11],
                ['content_key' => 'item_2_title', 'value_text' => 'فريق متخصص', 'sort_order' => 20],
                ['content_key' => 'item_2_description', 'value_text' => 'كل جزء من المشروع يتم تنفيذه بواسطة المتخصص المناسب.', 'sort_order' => 21],
                ['content_key' => 'item_3_title', 'value_text' => 'تنفيذ منظم', 'sort_order' => 30],
                ['content_key' => 'item_3_description', 'value_text' => 'مراحل واضحة ومتابعة مستمرة من البداية حتى التسليم.', 'sort_order' => 31],
                ['content_key' => 'item_4_title', 'value_text' => 'جودة تصنع الفرق', 'sort_order' => 40],
                ['content_key' => 'item_4_description', 'value_text' => 'نهتم بالتفاصيل لأن قوة العلامة تبدأ من جودة التنفيذ.', 'sort_order' => 41],
            ],
            'packages' => [
                ['content_key' => 'eyebrow', 'value_text' => 'PACKAGES', 'sort_order' => 1],
                ['content_key' => 'title', 'value_text' => 'باقات تناسب مرحلة مشروعك', 'sort_order' => 2],
                ['content_key' => 'description', 'value_text' => 'معاينة لمفهوم الباقات. التفاصيل والأسعار داخل المنصة بعد تسجيل الدخول.', 'sort_order' => 3],
                ['content_key' => 'visual_basic', 'value_text' => null, 'sort_order' => 10, 'metadata' => ['visual_key' => 'packages.basic']],
                ['content_key' => 'visual_professional', 'value_text' => null, 'sort_order' => 11, 'metadata' => ['visual_key' => 'packages.professional']],
                ['content_key' => 'visual_integrated', 'value_text' => null, 'sort_order' => 12, 'metadata' => ['visual_key' => 'packages.integrated']],
                ['content_key' => 'note', 'value_text' => 'Landing package previews remain hardcoded; Package model /api/packages is the domain source of truth. Presentation images only via visual_* keys.', 'sort_order' => 90, 'metadata' => ['reuse' => 'Package model']],
            ],
            'build-package' => [
                ['content_key' => 'eyebrow', 'value_text' => 'صمّم باقتك', 'sort_order' => 1],
                ['content_key' => 'title', 'value_text' => 'اختر ما تحتاجه — وابنِ الحل المناسب لك', 'sort_order' => 2],
                ['content_key' => 'description', 'value_text' => 'اختار الخدمات اللي تناسب مشروعك من عدة فئات، حدد الكميات والإضافات، وشوف ملخص طلبك في مكان واحد.', 'sort_order' => 3],
            ],
            'process' => [
                ['content_key' => 'eyebrow', 'value_text' => 'مسار العمل', 'sort_order' => 1],
                ['content_key' => 'title', 'value_text' => 'رحلة العميل', 'sort_order' => 2],
                ['content_key' => 'step_1_title', 'value_text' => 'تشخيص النشاط', 'sort_order' => 10],
                ['content_key' => 'step_2_title', 'value_text' => 'تحديد الأولويات', 'sort_order' => 20],
                ['content_key' => 'step_3_title', 'value_text' => 'اختيار الخدمة أو الباقة', 'sort_order' => 30],
                ['content_key' => 'step_4_title', 'value_text' => 'استلام العرض', 'sort_order' => 40],
                ['content_key' => 'step_5_title', 'value_text' => 'التنفيذ وإدارة المشروع', 'sort_order' => 50],
                ['content_key' => 'step_6_title', 'value_text' => 'قياس النتائج والمتابعة', 'sort_order' => 60],
            ],
            'portfolio' => [
                ['content_key' => 'eyebrow', 'value_text' => 'أعمالنا', 'sort_order' => 1],
                ['content_key' => 'title', 'value_text' => 'أعمال تتحدث عنّا', 'sort_order' => 2],
                ['content_key' => 'description', 'value_text' => 'نماذج مختارة من المعرض المنشور عبر المنصة.', 'sort_order' => 3],
                ['content_key' => 'note', 'value_text' => 'Items come from PortfolioItem public API.', 'sort_order' => 4, 'metadata' => ['reuse' => 'PortfolioItem']],
            ],
            'suppliers' => [
                ['content_key' => 'eyebrow', 'value_text' => 'شبكة الموردين', 'sort_order' => 1],
                ['content_key' => 'title', 'value_text' => 'موردونا وشركاؤنا', 'sort_order' => 2],
                ['content_key' => 'description', 'value_text' => 'نعمل مع شبكة من الموردين والمتخصصين لتوفير حلول الطباعة، التغليف، التجهيزات والفعاليات حسب احتياج المشروع.', 'sort_order' => 3],
                ['content_key' => 'note', 'value_text' => 'Featured suppliers come from Supplier public API.', 'sort_order' => 4, 'metadata' => ['reuse' => 'Supplier']],
            ],
            'about' => [
                ['content_key' => 'eyebrow', 'value_text' => 'من نحن', 'sort_order' => 1],
                ['content_key' => 'quote', 'value_text' => 'نحوّل احتياجات الأعمال إلى حلول واضحة قابلة للتنفيذ.', 'sort_order' => 2],
                ['content_key' => 'body', 'value_text' => 'حبر وأبعاد منصة سعودية متكاملة لخدمات الأعمال والنمو. نساعد الشركات والمنشآت ورواد الأعمال على تشخيص احتياجاتهم، بناء علاماتهم، وتطوير حضورهم من خلال شبكة متخصصين وموردين.', 'sort_order' => 3],
                ['content_key' => 'tagline', 'value_text' => 'نمنح أعمالك أبعادًا للنمو', 'sort_order' => 4],
                ['content_key' => 'cta_label', 'value_text' => 'اقرأ القصة كاملة', 'sort_order' => 5],
                ['content_key' => 'visual_image', 'value_text' => null, 'sort_order' => 6, 'metadata' => ['visual_key' => 'about', 'local_public_path' => '/marketing/storefront.jpg']],
                ['content_key' => 'note', 'value_text' => 'Full About page body remains CmsPage slug=about. Overlay process strip stays UI chrome.', 'sort_order' => 7, 'metadata' => ['reuse' => 'CmsPage']],
            ],
            'final-cta' => [
                ['content_key' => 'eyebrow', 'value_text' => 'جاهز نبدأ؟', 'sort_order' => 1],
                ['content_key' => 'title', 'value_text' => 'لنبنِ شيئًا يستحق التذكّر.', 'sort_order' => 2],
                ['content_key' => 'description', 'value_text' => 'احكِ لنا عن فكرتك، ودع فريق حبر وأبعاد يحوّلها إلى تجربة متكاملة من الفكرة حتى التسليم.', 'sort_order' => 3],
                ['content_key' => 'cta_primary_label', 'value_text' => 'ابدأ مشروعك', 'sort_order' => 4],
                ['content_key' => 'cta_primary_url', 'value_text' => '#contact', 'sort_order' => 5],
                ['content_key' => 'cta_secondary_label', 'value_text' => 'تواصل معنا', 'sort_order' => 6],
                ['content_key' => 'cta_secondary_url', 'value_text' => '#contact', 'sort_order' => 7],
            ],
            'contact' => [
                ['content_key' => 'eyebrow', 'value_text' => 'تواصل معنا', 'sort_order' => 1],
                ['content_key' => 'title', 'value_text' => 'خلينا نتكلم عن مشروعك', 'sort_order' => 2],
                ['content_key' => 'description', 'value_text' => 'أرسل فكرتك عبر النموذج، أو أنشئ حساباً للدخول إلى المنصة ومتابعة مشروعك مباشرة.', 'sort_order' => 3],
                ['content_key' => 'visual_image', 'value_text' => null, 'sort_order' => 4, 'metadata' => ['visual_key' => 'contact', 'local_public_path' => '/marketing/storefront.jpg']],
            ],
        ];

        foreach ($map as $sectionKey => $contents) {
            $section = MarketingSection::query()->where('key', $sectionKey)->first();
            if ($section === null) {
                continue;
            }

            foreach ($contents as $content) {
                MarketingContent::query()->updateOrCreate(
                    [
                        'marketing_section_id' => $section->id,
                        'content_key' => $content['content_key'],
                    ],
                    [
                        'value_text' => $content['value_text'] ?? null,
                        'value_html' => $content['value_html'] ?? null,
                        'sort_order' => $content['sort_order'] ?? 0,
                        'is_enabled' => true,
                        'metadata' => $content['metadata'] ?? null,
                    ],
                );
            }
        }
    }

    private function registerDefaultAssets(): void
    {
        $owner = User::query()->where('role', 'OWNER')->orderBy('id')->first();
        if ($owner === null) {
            return;
        }

        /** @var MarketingMediaLibraryService $library */
        $library = app(MarketingMediaLibraryService::class);

        $assets = [
            [
                'path' => '/marketing/storefront.jpg',
                'source_key' => 'storefront',
                'title' => 'Storefront',
                'alt_text' => 'واجهة فرع حبر وأبعاد',
                'used_by' => ['hero.image', 'about.image', 'contact.image', 'services.strategy.image'],
            ],
            [
                'path' => '/brand/mark.png',
                'source_key' => 'brand.mark',
                'title' => 'Brand mark',
                'alt_text' => 'شعار حبر وأبعاد',
                'used_by' => ['hero.accentSvg', 'about.accentSvg'],
            ],
            [
                'path' => '/brand/logo.png',
                'source_key' => 'brand.logo',
                'title' => 'Brand logo',
                'alt_text' => 'شعار حبر وأبعاد',
                'used_by' => ['contact.accentSvg'],
            ],
            [
                'path' => '/printing/business-cards.svg',
                'source_key' => 'printing.business-cards',
                'title' => 'Business cards accent',
                'alt_text' => '',
                'used_by' => ['services.strategy.accentSvg'],
            ],
            [
                'path' => '/printing/business-cards-luxury.svg',
                'source_key' => 'printing.business-cards-luxury',
                'title' => 'Luxury cards accent',
                'alt_text' => '',
                'used_by' => ['services.branding.accentSvg'],
            ],
            [
                'path' => '/printing/flyers.svg',
                'source_key' => 'printing.flyers',
                'title' => 'Flyers accent',
                'alt_text' => '',
                'used_by' => ['services.digital.accentSvg'],
            ],
            [
                'path' => '/printing/packaging.svg',
                'source_key' => 'printing.packaging',
                'title' => 'Packaging accent',
                'alt_text' => '',
                'used_by' => ['services.ecommerce.accentSvg'],
            ],
            [
                'path' => '/printing/boxes.svg',
                'source_key' => 'printing.boxes',
                'title' => 'Boxes accent',
                'alt_text' => '',
                'used_by' => ['services.printing.accentSvg'],
            ],
            [
                'path' => '/printing/posters.svg',
                'source_key' => 'printing.posters',
                'title' => 'Posters accent',
                'alt_text' => '',
                'used_by' => ['services.events.accentSvg'],
            ],
            [
                'path' => '/printing/stickers.svg',
                'source_key' => 'printing.stickers',
                'title' => 'Stickers accent',
                'alt_text' => '',
                'used_by' => ['packages.basic.accentSvg'],
            ],
            [
                'path' => '/printing/business-cards-premium.svg',
                'source_key' => 'printing.business-cards-premium',
                'title' => 'Premium cards accent',
                'alt_text' => '',
                'used_by' => ['packages.professional.accentSvg'],
            ],
            [
                'path' => '/printing/packaging-branded.svg',
                'source_key' => 'printing.packaging-branded',
                'title' => 'Branded packaging accent',
                'alt_text' => '',
                'used_by' => ['packages.integrated.accentSvg'],
            ],
        ];

        foreach ($assets as $asset) {
            $media = $library->registerLocalPublicAsset($owner, $asset['path'], [
                'source_key' => $asset['source_key'],
                'title' => $asset['title'],
                'alt_text' => $asset['alt_text'],
                'used_by' => $asset['used_by'],
            ]);

            if ($asset['source_key'] === 'storefront') {
                foreach (['hero', 'about', 'contact'] as $sectionKey) {
                    $section = MarketingSection::query()->where('key', $sectionKey)->first();
                    if ($section === null) {
                        continue;
                    }
                    MarketingContent::query()
                        ->where('marketing_section_id', $section->id)
                        ->where('content_key', 'visual_image')
                        ->update(['media_id' => $media->id]);
                }

                $services = MarketingSection::query()->where('key', 'services')->first();
                if ($services !== null) {
                    MarketingContent::query()
                        ->where('marketing_section_id', $services->id)
                        ->where('content_key', 'visual_strategy')
                        ->update(['media_id' => $media->id]);
                }
            }
        }
    }
}
