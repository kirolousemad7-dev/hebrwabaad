<?php

namespace App\Support\Consultant;

use App\Support\PrintingCatalog;

class BusinessCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function categories(): array
    {
        return [
            [
                'id' => 'restaurants-food',
                'label' => 'مطاعم وأغذية',
                'icon' => 'utensils',
                'subtypes' => [
                    ['id' => 'restaurant', 'label' => 'مطعم'],
                    ['id' => 'meat-restaurant', 'label' => 'مطعم لحوم'],
                    ['id' => 'chicken-restaurant', 'label' => 'مطعم فراخ'],
                    ['id' => 'seafood-restaurant', 'label' => 'مطعم بحري'],
                    ['id' => 'fast-food', 'label' => 'وجبات سريعة'],
                    ['id' => 'cafe', 'label' => 'كافيه'],
                    ['id' => 'bakery', 'label' => 'مخبز'],
                    ['id' => 'pastry', 'label' => 'حلويات ومعجنات'],
                    ['id' => 'cloud-kitchen', 'label' => 'مطبخ سحابي'],
                    ['id' => 'catering', 'label' => 'كاترينج'],
                    ['id' => 'food-truck', 'label' => 'فود ترك'],
                    ['id' => 'juice-beverage', 'label' => 'عصائر ومشروبات'],
                    ['id' => 'dessert-shop', 'label' => 'محل حلويات'],
                ],
            ],
            [
                'id' => 'retail',
                'label' => 'تجزئة',
                'icon' => 'store',
                'subtypes' => [
                    ['id' => 'clothing', 'label' => 'ملابس'],
                    ['id' => 'shoes', 'label' => 'أحذية'],
                    ['id' => 'accessories', 'label' => 'إكسسوارات'],
                    ['id' => 'cosmetics', 'label' => 'مستحضرات تجميل'],
                    ['id' => 'furniture', 'label' => 'أثاث'],
                    ['id' => 'electronics', 'label' => 'إلكترونيات'],
                    ['id' => 'home-appliances', 'label' => 'أجهزة منزلية'],
                    ['id' => 'supermarket', 'label' => 'سوبرماركت'],
                    ['id' => 'jewelry', 'label' => 'مجوهرات'],
                    ['id' => 'gifts', 'label' => 'هدايا'],
                    ['id' => 'perfumes', 'label' => 'عطور'],
                    ['id' => 'baby-products', 'label' => 'منتجات أطفال'],
                    ['id' => 'sports-products', 'label' => 'منتجات رياضية'],
                    ['id' => 'spare-parts', 'label' => 'قطع غيار'],
                ],
            ],
            [
                'id' => 'ecommerce',
                'label' => 'تجارة إلكترونية',
                'icon' => 'cart',
                'subtypes' => [
                    ['id' => 'general-ecommerce', 'label' => 'متجر إلكتروني عام'],
                    ['id' => 'fashion-ecommerce', 'label' => 'أزياء أونلاين'],
                    ['id' => 'electronics-ecommerce', 'label' => 'إلكترونيات أونلاين'],
                    ['id' => 'beauty-ecommerce', 'label' => 'جمال وعناية أونلاين'],
                    ['id' => 'food-ecommerce', 'label' => 'أغذية أونلاين'],
                    ['id' => 'b2b-ecommerce', 'label' => 'تجارة إلكترونية B2B'],
                    ['id' => 'marketplace', 'label' => 'سوق إلكتروني'],
                    ['id' => 'subscription-business', 'label' => 'اشتراكات'],
                ],
            ],
            [
                'id' => 'real-estate',
                'label' => 'عقارات',
                'icon' => 'building',
                'subtypes' => [
                    ['id' => 'real-estate-company', 'label' => 'شركة عقارية'],
                    ['id' => 'real-estate-developer', 'label' => 'مطوّر عقاري'],
                    ['id' => 'brokerage', 'label' => 'وساطة عقارية'],
                    ['id' => 'property-management', 'label' => 'إدارة أملاك'],
                    ['id' => 'rental', 'label' => 'تأجير'],
                    ['id' => 'construction-real-estate', 'label' => 'عقارات إنشائية'],
                ],
            ],
            [
                'id' => 'construction-engineering',
                'label' => 'إنشاءات وهندسة',
                'icon' => 'hardhat',
                'subtypes' => [
                    ['id' => 'construction', 'label' => 'إنشاءات'],
                    ['id' => 'contracting', 'label' => 'مقاولات'],
                    ['id' => 'engineering', 'label' => 'هندسة'],
                    ['id' => 'architecture', 'label' => 'عمارة'],
                    ['id' => 'interior-design', 'label' => 'تصميم داخلي'],
                    ['id' => 'landscaping', 'label' => 'تنسيق حدائق'],
                    ['id' => 'building-materials', 'label' => 'مواد بناء'],
                ],
            ],
            [
                'id' => 'medical-healthcare',
                'label' => 'طبي ورعاية صحية',
                'icon' => 'heart',
                'subtypes' => [
                    ['id' => 'clinic', 'label' => 'عيادة'],
                    ['id' => 'dental-clinic', 'label' => 'عيادة أسنان'],
                    ['id' => 'medical-center', 'label' => 'مركز طبي'],
                    ['id' => 'pharmacy', 'label' => 'صيدلية'],
                    ['id' => 'laboratory', 'label' => 'معمل تحاليل'],
                    ['id' => 'physiotherapy', 'label' => 'علاج طبيعي'],
                    ['id' => 'medical-supplier', 'label' => 'مستلزمات طبية'],
                    ['id' => 'healthcare-company', 'label' => 'شركة رعاية صحية'],
                ],
            ],
            [
                'id' => 'education',
                'label' => 'تعليم',
                'icon' => 'book',
                'subtypes' => [
                    ['id' => 'school', 'label' => 'مدرسة'],
                    ['id' => 'university', 'label' => 'جامعة'],
                    ['id' => 'training-center', 'label' => 'مركز تدريب'],
                    ['id' => 'academy', 'label' => 'أكاديمية'],
                    ['id' => 'online-courses', 'label' => 'دورات أونلاين'],
                    ['id' => 'tutor', 'label' => 'مدرس خصوصي'],
                    ['id' => 'educational-platform', 'label' => 'منصة تعليمية'],
                    ['id' => 'language-center', 'label' => 'مركز لغات'],
                ],
            ],
            [
                'id' => 'hospitality-tourism',
                'label' => 'ضيافة وسياحة',
                'icon' => 'plane',
                'subtypes' => [
                    ['id' => 'hotel', 'label' => 'فندق'],
                    ['id' => 'resort', 'label' => 'منتجع'],
                    ['id' => 'hostel', 'label' => 'استضافة'],
                    ['id' => 'travel-agency', 'label' => 'وكالة سفر'],
                    ['id' => 'tourism-company', 'label' => 'شركة سياحة'],
                    ['id' => 'tour-operator', 'label' => 'منظم رحلات'],
                    ['id' => 'car-rental-travel', 'label' => 'تأجير سيارات'],
                ],
            ],
            [
                'id' => 'automotive',
                'label' => 'سيارات',
                'icon' => 'car',
                'subtypes' => [
                    ['id' => 'car-dealer', 'label' => 'معرض سيارات'],
                    ['id' => 'car-rental', 'label' => 'تأجير سيارات'],
                    ['id' => 'car-maintenance', 'label' => 'صيانة سيارات'],
                    ['id' => 'auto-service', 'label' => 'خدمة سيارات'],
                    ['id' => 'auto-spare-parts', 'label' => 'قطع غيار'],
                    ['id' => 'car-accessories', 'label' => 'إكسسوارات سيارات'],
                    ['id' => 'car-wash', 'label' => 'غسيل سيارات'],
                ],
            ],
            [
                'id' => 'beauty-personal-care',
                'label' => 'جمال وعناية',
                'icon' => 'sparkle',
                'subtypes' => [
                    ['id' => 'beauty-salon', 'label' => 'صالون تجميل'],
                    ['id' => 'barber', 'label' => 'حلاقة'],
                    ['id' => 'spa', 'label' => 'سبا'],
                    ['id' => 'beauty-center', 'label' => 'مركز تجميل'],
                    ['id' => 'nail-salon', 'label' => 'مركز أظافر'],
                    ['id' => 'skincare', 'label' => 'عناية بالبشرة'],
                    ['id' => 'makeup-artist', 'label' => 'خبير تجميل'],
                ],
            ],
            [
                'id' => 'fitness-sports',
                'label' => 'لياقة ورياضة',
                'icon' => 'dumbbell',
                'subtypes' => [
                    ['id' => 'gym', 'label' => 'جيم'],
                    ['id' => 'personal-trainer', 'label' => 'مدرب شخصي'],
                    ['id' => 'sports-academy', 'label' => 'أكاديمية رياضية'],
                    ['id' => 'fitness-center', 'label' => 'مركز لياقة'],
                    ['id' => 'sports-club', 'label' => 'نادي رياضي'],
                ],
            ],
            [
                'id' => 'technology',
                'label' => 'تقنية',
                'icon' => 'chip',
                'subtypes' => [
                    ['id' => 'software-company', 'label' => 'شركة برمجيات'],
                    ['id' => 'saas', 'label' => 'SaaS'],
                    ['id' => 'it-services', 'label' => 'خدمات تقنية'],
                    ['id' => 'cybersecurity', 'label' => 'أمن سيبراني'],
                    ['id' => 'ai-company', 'label' => 'شركة ذكاء اصطناعي'],
                    ['id' => 'tech-startup', 'label' => 'شركة ناشئة'],
                    ['id' => 'tech-product', 'label' => 'منتج تقني'],
                ],
            ],
            [
                'id' => 'professional-services',
                'label' => 'خدمات مهنية',
                'icon' => 'briefcase',
                'subtypes' => [
                    ['id' => 'law-firm', 'label' => 'مكتب محاماة'],
                    ['id' => 'accounting', 'label' => 'محاسبة'],
                    ['id' => 'consulting', 'label' => 'استشارات'],
                    ['id' => 'recruitment', 'label' => 'توظيف'],
                    ['id' => 'hr-services', 'label' => 'موارد بشرية'],
                    ['id' => 'financial-services', 'label' => 'خدمات مالية'],
                    ['id' => 'insurance', 'label' => 'تأمين'],
                    ['id' => 'architecture-firm', 'label' => 'مكتب معماري'],
                    ['id' => 'marketing-agency-pro', 'label' => 'وكالة تسويق'],
                ],
            ],
            [
                'id' => 'media-creative',
                'label' => 'إعلام وإبداع',
                'icon' => 'camera',
                'subtypes' => [
                    ['id' => 'marketing-agency', 'label' => 'وكالة تسويق'],
                    ['id' => 'advertising-agency', 'label' => 'وكالة إعلان'],
                    ['id' => 'photography', 'label' => 'تصوير فوتوغرافي'],
                    ['id' => 'videography', 'label' => 'تصوير فيديو'],
                    ['id' => 'content-creator', 'label' => 'صانع محتوى'],
                    ['id' => 'influencer', 'label' => 'مؤثر'],
                    ['id' => 'production-company', 'label' => 'شركة إنتاج'],
                    ['id' => 'design-studio', 'label' => 'ستوديو تصميم'],
                ],
            ],
            [
                'id' => 'events',
                'label' => 'فعاليات',
                'icon' => 'calendar',
                'subtypes' => [
                    ['id' => 'event-company', 'label' => 'شركة فعاليات'],
                    ['id' => 'wedding', 'label' => 'زفاف'],
                    ['id' => 'corporate-event', 'label' => 'فعالية شركات'],
                    ['id' => 'conference', 'label' => 'مؤتمر'],
                    ['id' => 'exhibition', 'label' => 'معرض'],
                    ['id' => 'product-launch', 'label' => 'إطلاق منتج'],
                    ['id' => 'festival', 'label' => 'مهرجان'],
                    ['id' => 'birthday', 'label' => 'عيد ميلاد'],
                    ['id' => 'graduation', 'label' => 'تخرج'],
                    ['id' => 'private-event', 'label' => 'مناسبة خاصة'],
                ],
            ],
            [
                'id' => 'printing-packaging',
                'label' => 'طباعة وتغليف',
                'icon' => 'print',
                'subtypes' => [
                    ['id' => 'printing-business', 'label' => 'نشاط طباعة'],
                    ['id' => 'packaging', 'label' => 'تغليف'],
                    ['id' => 'packaging-manufacturer', 'label' => 'تصنيع تغليف'],
                    ['id' => 'signage', 'label' => 'لافتات'],
                    ['id' => 'promotional-materials', 'label' => 'مواد ترويجية'],
                ],
            ],
            [
                'id' => 'manufacturing',
                'label' => 'تصنيع',
                'icon' => 'factory',
                'subtypes' => [
                    ['id' => 'factory', 'label' => 'مصنع'],
                    ['id' => 'manufacturer', 'label' => 'مصنّع'],
                    ['id' => 'industrial-supplier', 'label' => 'مورد صناعي'],
                    ['id' => 'b2b-manufacturing', 'label' => 'تصنيع B2B'],
                ],
            ],
            [
                'id' => 'logistics',
                'label' => 'خدمات لوجستية',
                'icon' => 'truck',
                'subtypes' => [
                    ['id' => 'shipping', 'label' => 'شحن'],
                    ['id' => 'delivery', 'label' => 'توصيل'],
                    ['id' => 'logistics-company', 'label' => 'شركة لوجستية'],
                    ['id' => 'warehouse', 'label' => 'مستودع'],
                    ['id' => 'transportation', 'label' => 'نقل'],
                ],
            ],
            [
                'id' => 'agriculture',
                'label' => 'زراعة',
                'icon' => 'leaf',
                'subtypes' => [
                    ['id' => 'farm', 'label' => 'مزرعة'],
                    ['id' => 'agricultural-business', 'label' => 'نشاط زراعي'],
                    ['id' => 'food-production', 'label' => 'إنتاج غذائي'],
                    ['id' => 'agricultural-supplier', 'label' => 'مورد زراعي'],
                ],
            ],
            [
                'id' => 'other',
                'label' => 'أخرى',
                'icon' => 'dots',
                'subtypes' => [
                    ['id' => 'freelancer', 'label' => 'مستقل'],
                    ['id' => 'personal-brand', 'label' => 'علامة شخصية'],
                    ['id' => 'ngo', 'label' => 'منظمة غير ربحية'],
                    ['id' => 'organization', 'label' => 'مؤسسة'],
                    ['id' => 'startup', 'label' => 'شركة ناشئة'],
                    ['id' => 'other-business', 'label' => 'نشاط آخر'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function goals(): array
    {
        return [
            ['id' => 'increase_sales', 'label' => 'زيادة المبيعات'],
            ['id' => 'more_customers', 'label' => 'الحصول على عملاء أكثر'],
            ['id' => 'brand_awareness', 'label' => 'زيادة الوعي بالعلامة'],
            ['id' => 'launch_business', 'label' => 'إطلاق نشاط جديد'],
            ['id' => 'launch_product', 'label' => 'إطلاق منتج جديد'],
            ['id' => 'improve_online', 'label' => 'تحسين الحضور الرقمي'],
            ['id' => 'build_website', 'label' => 'بناء موقع إلكتروني'],
            ['id' => 'build_ecommerce', 'label' => 'بناء متجر إلكتروني'],
            ['id' => 'improve_social', 'label' => 'تحسين السوشيال ميديا'],
            ['id' => 'run_ads', 'label' => 'تشغيل إعلانات مدفوعة'],
            ['id' => 'retention', 'label' => 'تحسين الاحتفاظ بالعملاء'],
            ['id' => 'rebrand', 'label' => 'إعادة بناء الهوية'],
            ['id' => 'prepare_event', 'label' => 'التحضير لفعالية'],
            ['id' => 'improve_packaging', 'label' => 'تحسين التغليف'],
            ['id' => 'print_materials', 'label' => 'طباعة مواد تسويقية'],
            ['id' => 'automate', 'label' => 'أتمتة النشاط'],
            ['id' => 'build_app', 'label' => 'بناء برنامج أو تطبيق'],
            ['id' => 'generate_leads', 'label' => 'توليد عملاء محتملين'],
            ['id' => 'improve_conversion', 'label' => 'تحسين التحويل'],
            ['id' => 'other_goal', 'label' => 'هدف آخر'],
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function serviceNeeds(): array
    {
        return [
            ['id' => 'website', 'label' => 'موقع إلكتروني'],
            ['id' => 'ecommerce', 'label' => 'متجر إلكتروني'],
            ['id' => 'web_application', 'label' => 'تطبيق ويب'],
            ['id' => 'software', 'label' => 'تطوير برمجيات'],
            ['id' => 'branding', 'label' => 'هوية بصرية'],
            ['id' => 'graphic_design', 'label' => 'تصميم جرافيكي'],
            ['id' => 'social_media', 'label' => 'سوشيال ميديا'],
            ['id' => 'marketing_strategy', 'label' => 'استراتيجية تسويق'],
            ['id' => 'performance_marketing', 'label' => 'تسويق أدائي'],
            ['id' => 'media_buying', 'label' => 'شراء إعلانات'],
            ['id' => 'content', 'label' => 'صناعة محتوى'],
            ['id' => 'video', 'label' => 'إنتاج فيديو'],
            ['id' => 'photography', 'label' => 'تصوير'],
            ['id' => 'event_management', 'label' => 'إدارة فعاليات'],
            ['id' => 'printing', 'label' => 'طباعة'],
            ['id' => 'packaging', 'label' => 'تغليف'],
            ['id' => 'business_strategy', 'label' => 'استراتيجية أعمال'],
            ['id' => 'ai_solutions', 'label' => 'حلول ذكاء اصطناعي'],
            ['id' => 'custom', 'label' => 'حل مخصص'],
            ['id' => 'unsure', 'label' => 'لست متأكداً'],
        ];
    }

    /**
     * Budget bands use the platform currency (SAR), matching real catalog prices.
     *
     * @return list<array{id: string, label: string, min: int|null, max: int|null}>
     */
    public static function budgetBands(): array
    {
        return [
            ['id' => 'under_5000', 'label' => 'أقل من 5,000 ر.س', 'min' => 0, 'max' => 4999],
            ['id' => '5000_10000', 'label' => '5,000 – 10,000 ر.س', 'min' => 5000, 'max' => 10000],
            ['id' => '10000_25000', 'label' => '10,000 – 25,000 ر.س', 'min' => 10000, 'max' => 25000],
            ['id' => '25000_50000', 'label' => '25,000 – 50,000 ر.س', 'min' => 25000, 'max' => 50000],
            ['id' => '50000_plus', 'label' => 'أكثر من 50,000 ر.س', 'min' => 50000, 'max' => null],
            ['id' => 'not_sure', 'label' => 'غير متأكد بعد', 'min' => null, 'max' => null],
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function timelines(): array
    {
        return [
            ['id' => 'asap', 'label' => 'في أقرب وقت'],
            ['id' => '1_week', 'label' => 'خلال أسبوع'],
            ['id' => '2_4_weeks', 'label' => 'من أسبوعين إلى 4 أسابيع'],
            ['id' => '1_3_months', 'label' => 'من شهر إلى 3 أشهر'],
            ['id' => '3_6_months', 'label' => 'من 3 إلى 6 أشهر'],
            ['id' => 'flexible', 'label' => 'مرن'],
            ['id' => 'not_sure', 'label' => 'غير متأكد'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function questions(): array
    {
        return [
            'help_mode' => [
                'id' => 'help_mode',
                'title' => 'كيف تريد أن نبدأ؟',
                'body' => 'يمكننا إرشادك خطوة بخطوة، أو الإجابة على أسئلتك مباشرة، أو اكتشاف احتياجك إن لم تكن متأكداً.',
                'type' => 'cards',
                'options' => [
                    ['id' => 'guided', 'label' => 'استشارة موجّهة', 'description' => 'أسئلة قصيرة ثم توصية واضحة'],
                    ['id' => 'chat', 'label' => 'محادثة حرة', 'description' => 'اكتب ما تعرفه وسنكمل معاً'],
                    ['id' => 'unsure', 'label' => 'لست متأكداً مما أحتاجه', 'description' => 'نكتشف الاحتياج من الحوار'],
                ],
            ],
            'business_category' => [
                'id' => 'business_category',
                'title' => 'ما نوع نشاطك؟',
                'body' => 'اختر التصنيف الأقرب. يمكنك البحث إن كانت القائمة طويلة.',
                'type' => 'search_cards',
                'searchable' => true,
            ],
            'business_subtype' => [
                'id' => 'business_subtype',
                'title' => 'ما التخصص الأدق؟',
                'body' => 'هذا يساعدنا على طرح الأسئلة المناسبة لنشاطك.',
                'type' => 'chips',
                'searchable' => true,
            ],
            'business_name' => [
                'id' => 'business_name',
                'title' => 'ما اسم النشاط؟',
                'body' => 'اختياري — يساعدنا على تخصيص التوصية.',
                'type' => 'text',
                'skippable' => true,
                'placeholder' => 'مثال: بيت الفراخ',
            ],
            'location' => [
                'id' => 'location',
                'title' => 'أين سوقك الأساسي؟',
                'body' => 'المدينة أو الحي يكفي.',
                'type' => 'text',
                'skippable' => true,
                'placeholder' => 'مثال: الإسكندرية — سموحة',
            ],
            'branches' => [
                'id' => 'branches',
                'title' => 'كم عدد الفروع؟',
                'body' => 'فرع واحد أو أكثر.',
                'type' => 'chips',
                'skippable' => true,
                'options' => [
                    ['id' => '1', 'label' => 'فرع واحد'],
                    ['id' => '2', 'label' => 'فرعان'],
                    ['id' => '3_5', 'label' => '3 إلى 5'],
                    ['id' => '6_plus', 'label' => 'أكثر من 5'],
                ],
            ],
            'goals' => [
                'id' => 'goals',
                'title' => 'ما هدفك الأساسي؟',
                'body' => 'يمكنك اختيار أكثر من هدف.',
                'type' => 'multi_chips',
            ],
            'needed_services' => [
                'id' => 'needed_services',
                'title' => 'ما الخدمات التي تعتقد أنك تحتاجها؟',
                'body' => 'إن لم تكن متأكداً اختر «لست متأكداً» وسنحددها من الحوار.',
                'type' => 'multi_chips',
            ],
            'budget' => [
                'id' => 'budget',
                'title' => 'ما الميزانية التقريبية؟',
                'body' => 'نستخدم نطاقات بالريال السعودي لتتوافق مع أسعار المنصة الفعلية.',
                'type' => 'chips',
            ],
            'timeline' => [
                'id' => 'timeline',
                'title' => 'متى تحتاج التنفيذ؟',
                'body' => 'هذا يؤثر على التوصية وخطة العمل.',
                'type' => 'chips',
            ],
            'has_website' => [
                'id' => 'has_website',
                'title' => 'هل لديك موقع إلكتروني؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'yes', 'label' => 'نعم'],
                    ['id' => 'no', 'label' => 'لا'],
                    ['id' => 'in_progress', 'label' => 'قيد الإنشاء'],
                ],
            ],
            'social_platforms' => [
                'id' => 'social_platforms',
                'title' => 'ما المنصات التي تستخدمها حالياً؟',
                'type' => 'multi_chips',
                'skippable' => true,
                'options' => [
                    ['id' => 'instagram', 'label' => 'Instagram'],
                    ['id' => 'facebook', 'label' => 'Facebook'],
                    ['id' => 'tiktok', 'label' => 'TikTok'],
                    ['id' => 'snapchat', 'label' => 'Snapchat'],
                    ['id' => 'x', 'label' => 'X'],
                    ['id' => 'linkedin', 'label' => 'LinkedIn'],
                    ['id' => 'none', 'label' => 'لا يوجد حضور يذكر'],
                ],
            ],
            'biggest_challenge' => [
                'id' => 'biggest_challenge',
                'title' => 'ما أكبر تحدٍ تواجهه الآن؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'not_enough_customers', 'label' => 'عملاء غير كافين'],
                    ['id' => 'weak_online', 'label' => 'حضور رقمي ضعيف'],
                    ['id' => 'low_conversion', 'label' => 'تحويل ضعيف'],
                    ['id' => 'inconsistent_content', 'label' => 'محتوى غير منتظم'],
                    ['id' => 'branding', 'label' => 'هوية غير واضحة'],
                    ['id' => 'operations', 'label' => 'تشغيل وعمليات'],
                    ['id' => 'other', 'label' => 'تحدٍ آخر'],
                ],
            ],
            'dine_channels' => [
                'id' => 'dine_channels',
                'title' => 'كيف يشتري منك العملاء؟',
                'type' => 'multi_chips',
                'options' => [
                    ['id' => 'dine_in', 'label' => 'صالة'],
                    ['id' => 'delivery', 'label' => 'توصيل'],
                    ['id' => 'takeaway', 'label' => 'استلام'],
                ],
            ],
            'daily_orders' => [
                'id' => 'daily_orders',
                'title' => 'ما متوسط الطلبات اليومية تقريباً؟',
                'type' => 'chips',
                'skippable' => true,
                'options' => [
                    ['id' => 'under_20', 'label' => 'أقل من 20'],
                    ['id' => '20_50', 'label' => '20 إلى 50'],
                    ['id' => '50_150', 'label' => '50 إلى 150'],
                    ['id' => '150_plus', 'label' => 'أكثر من 150'],
                    ['id' => 'not_sure', 'label' => 'غير متأكد'],
                ],
            ],
            'has_online_ordering' => [
                'id' => 'has_online_ordering',
                'title' => 'هل لديك طلب أونلاين من موقعك؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'yes', 'label' => 'نعم'],
                    ['id' => 'apps_only', 'label' => 'تطبيقات التوصيل فقط'],
                    ['id' => 'no', 'label' => 'لا'],
                ],
            ],
            'product_count' => [
                'id' => 'product_count',
                'title' => 'كم عدد المنتجات تقريباً؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'under_20', 'label' => 'أقل من 20'],
                    ['id' => '20_100', 'label' => '20 إلى 100'],
                    ['id' => '100_500', 'label' => '100 إلى 500'],
                    ['id' => '500_plus', 'label' => 'أكثر من 500'],
                    ['id' => 'not_sure', 'label' => 'غير متأكد'],
                ],
            ],
            'has_payment' => [
                'id' => 'has_payment',
                'title' => 'هل بوابة الدفع مفعّلة؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'yes', 'label' => 'نعم'],
                    ['id' => 'no', 'label' => 'لا'],
                    ['id' => 'not_sure', 'label' => 'غير متأكد'],
                ],
            ],
            'has_shipping' => [
                'id' => 'has_shipping',
                'title' => 'هل الشحن مجهز؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'yes', 'label' => 'نعم'],
                    ['id' => 'partial', 'label' => 'جزئياً'],
                    ['id' => 'no', 'label' => 'لا'],
                ],
            ],
            're_role' => [
                'id' => 're_role',
                'title' => 'هل أنت مطوّر أم وسيط؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'developer', 'label' => 'مطوّر'],
                    ['id' => 'broker', 'label' => 'وسيط'],
                    ['id' => 'both', 'label' => 'الاثنان'],
                    ['id' => 'management', 'label' => 'إدارة أملاك'],
                ],
            ],
            'has_crm' => [
                'id' => 'has_crm',
                'title' => 'هل لديك نظام لإدارة العملاء (CRM)؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'yes', 'label' => 'نعم'],
                    ['id' => 'no', 'label' => 'لا'],
                    ['id' => 'not_sure', 'label' => 'غير متأكد'],
                ],
            ],
            'lead_source' => [
                'id' => 'lead_source',
                'title' => 'من أين يأتي عملاؤك المحتملون حالياً؟',
                'type' => 'multi_chips',
                'skippable' => true,
                'options' => [
                    ['id' => 'referrals', 'label' => 'ترشيحات'],
                    ['id' => 'ads', 'label' => 'إعلانات'],
                    ['id' => 'website', 'label' => 'الموقع'],
                    ['id' => 'walk_in', 'label' => 'حضور مباشر'],
                    ['id' => 'social', 'label' => 'سوشيال ميديا'],
                    ['id' => 'none', 'label' => 'لا يوجد مصدر ثابت'],
                ],
            ],
            'has_booking' => [
                'id' => 'has_booking',
                'title' => 'هل الحجز أونلاين متاح؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'yes', 'label' => 'نعم'],
                    ['id' => 'phone_only', 'label' => 'هاتف فقط'],
                    ['id' => 'no', 'label' => 'لا'],
                ],
            ],
            'event_type' => [
                'id' => 'event_type',
                'title' => 'ما نوع الفعالية؟',
                'type' => 'chips',
            ],
            'event_date' => [
                'id' => 'event_date',
                'title' => 'ما تاريخ الفعالية؟',
                'body' => 'التاريخ أهم من الميزانية في الفعاليات.',
                'type' => 'text',
                'placeholder' => 'مثال: 20 أكتوبر 2026',
            ],
            'attendees' => [
                'id' => 'attendees',
                'title' => 'كم عدد الحضور المتوقع؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'under_50', 'label' => 'أقل من 50'],
                    ['id' => '50_150', 'label' => '50 إلى 150'],
                    ['id' => '150_400', 'label' => '150 إلى 400'],
                    ['id' => '400_plus', 'label' => 'أكثر من 400'],
                    ['id' => 'not_sure', 'label' => 'غير متأكد'],
                ],
            ],
            'event_needs' => [
                'id' => 'event_needs',
                'title' => 'ما الذي تحتاجه للفعالية؟',
                'type' => 'multi_chips',
                'options' => [
                    ['id' => 'branding', 'label' => 'هوية بصرية'],
                    ['id' => 'printing', 'label' => 'طباعة'],
                    ['id' => 'photography', 'label' => 'تصوير'],
                    ['id' => 'videography', 'label' => 'فيديو'],
                    ['id' => 'invitations', 'label' => 'دعوات'],
                    ['id' => 'social_coverage', 'label' => 'تغطية سوشيال'],
                    ['id' => 'unsure', 'label' => 'لست متأكداً'],
                ],
            ],
            'website_purpose' => [
                'id' => 'website_purpose',
                'title' => 'ما هدف الموقع أو النظام؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'company_site', 'label' => 'موقع تعريفي'],
                    ['id' => 'landing', 'label' => 'صفحة هبوط'],
                    ['id' => 'ecommerce', 'label' => 'متجر إلكتروني'],
                    ['id' => 'booking', 'label' => 'حجز مواعيد'],
                    ['id' => 'web_app', 'label' => 'تطبيق ويب مخصص'],
                ],
            ],
            'needs_payments' => [
                'id' => 'needs_payments',
                'title' => 'هل تحتاج دفعاً إلكترونياً؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'yes', 'label' => 'نعم'],
                    ['id' => 'no', 'label' => 'لا'],
                    ['id' => 'later', 'label' => 'لاحقاً'],
                ],
            ],
            'current_channels' => [
                'id' => 'current_channels',
                'title' => 'كيف تسوّق حالياً؟',
                'type' => 'multi_chips',
                'options' => [
                    ['id' => 'organic', 'label' => 'محتوى عضوي'],
                    ['id' => 'meta_ads', 'label' => 'إعلانات Meta'],
                    ['id' => 'google_ads', 'label' => 'إعلانات Google'],
                    ['id' => 'tiktok_ads', 'label' => 'إعلانات TikTok'],
                    ['id' => 'offline', 'label' => 'تسويق ميداني'],
                    ['id' => 'none', 'label' => 'لا شيء منتظم'],
                ],
            ],
            'run_ads' => [
                'id' => 'run_ads',
                'title' => 'هل تشغّل إعلانات مدفوعة الآن؟',
                'type' => 'chips',
                'options' => [
                    ['id' => 'yes', 'label' => 'نعم'],
                    ['id' => 'tried', 'label' => 'جرّبت سابقاً'],
                    ['id' => 'no', 'label' => 'لا'],
                ],
            ],
            'printing_product' => [
                'id' => 'printing_product',
                'title' => 'أي منتج طباعة تحتاجه؟',
                'body' => 'الخيارات من كتالوج الطباعة الحالي فقط.',
                'type' => 'search_cards',
                'searchable' => true,
            ],
            'printing_quantity' => [
                'id' => 'printing_quantity',
                'title' => 'ما الكمية التقريبية؟',
                'type' => 'chips',
                'skippable' => true,
                'options' => [
                    ['id' => 'under_50', 'label' => 'أقل من 50'],
                    ['id' => '50_200', 'label' => '50 إلى 200'],
                    ['id' => '200_1000', 'label' => '200 إلى 1000'],
                    ['id' => '1000_plus', 'label' => 'أكثر من 1000'],
                    ['id' => 'not_sure', 'label' => 'غير متأكد'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function category(string $id): ?array
    {
        foreach (self::categories() as $category) {
            if ($category['id'] === $id) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function question(string $id): ?array
    {
        return self::questions()[$id] ?? null;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function printingProductOptions(): array
    {
        $labels = [
            'standard-business-cards' => 'كروت شخصية قياسية',
            'premium-business-cards' => 'كروت شخصية فاخرة',
            'luxury-business-cards' => 'كروت شخصية فاخرة مطلية',
            'a5-flyers' => 'فلايرز A5',
            'a4-flyers' => 'فلايرز A4',
            'premium-flyers' => 'فلايرز فاخرة',
            'die-cut-stickers' => 'استيكرات قص خاص',
            'round-stickers' => 'استيكرات دائرية',
            'product-labels' => 'ليبل منتجات',
            'folding-boxes' => 'علب قابلة للطي',
            'product-boxes' => 'علب منتجات',
            'gift-boxes' => 'علب هدايا',
            'paper-bags' => 'أكياس ورقية',
            'luxury-shopping-bags' => 'أكياس تسوق فاخرة',
            'custom-printed-bags' => 'أكياس مطبوعة مخصصة',
            'product-packaging' => 'تغليف منتجات',
            'food-packaging' => 'تغليف غذائي',
            'branded-packaging' => 'تغليف بهوية العلامة',
            'a3-posters' => 'بوسترات A3',
            'a2-posters' => 'بوسترات A2',
            'large-format-posters' => 'بوسترات كبيرة',
            'custom-printed-product' => 'منتج مطبوع مخصص',
            'custom-promotional-product' => 'منتج ترويجي مخصص',
        ];

        $options = [];

        foreach (PrintingCatalog::slugs() as $slug) {
            $options[] = [
                'id' => $slug,
                'label' => $labels[$slug] ?? $slug,
            ];
        }

        $options[] = ['id' => 'not_sure', 'label' => 'لست متأكداً'];
        $options[] = ['id' => 'custom_quote', 'label' => 'أحتاج عرض سعر مخصص'];

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicConfig(): array
    {
        return [
            'categories' => self::categories(),
            'goals' => self::goals(),
            'service_needs' => self::serviceNeeds(),
            'budget_bands' => self::budgetBands(),
            'timelines' => self::timelines(),
            'printing_products' => self::printingProductOptions(),
        ];
    }
}
