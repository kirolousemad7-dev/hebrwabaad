export type CatalogSectionId =
  | 'business-diagnosis-strategy'
  | 'branding-design'
  | 'photography-video-production'
  | 'ecommerce-digital-experience'
  | 'content-writing'
  | 'events-management'

export type CatalogSectionConfig = {
  path: `/${CatalogSectionId}`
  subcategory: string
  title: string
  description: string
  seoTitle: string
}

export const CATALOG_SECTIONS: Record<CatalogSectionId, CatalogSectionConfig> = {
  'business-diagnosis-strategy': {
    path: '/business-diagnosis-strategy',
    subcategory: 'diagnosis-strategy',
    title: 'تشخيص الأعمال والاستراتيجية',
    description:
      'ابدأ من القرار الصحيح. نحلل نشاطك وتسويقك ومبيعاتك وحضورك الرقمي، ثم نحدد الفجوات والفرص ونبني لك خطة واضحة قابلة للتنفيذ والقياس.',
    seoTitle: 'تشخيص الأعمال وإعداد الاستراتيجيات التسويقية',
  },
  'branding-design': {
    path: '/branding-design',
    subcategory: 'branding-design',
    title: 'الهوية والتصميم',
    description:
      'نبني علامات واضحة وقابلة للتذكر من خلال التموضع والشعار والهوية والتطبيقات البصرية التي تمنح المشروع حضورًا موحدًا واحترافيًا.',
    seoTitle: 'الهوية والتصميم',
  },
  'photography-video-production': {
    path: '/photography-video-production',
    subcategory: 'photography-video',
    title: 'التصوير والفيديو',
    description:
      'نحوّل منتجاتك وخدماتك ومشاريعك إلى محتوى مرئي احترافي يخدم المتجر والإعلانات ومنصات التواصل وسابقة الأعمال.',
    seoTitle: 'التصوير والفيديو',
  },
  'ecommerce-digital-experience': {
    path: '/ecommerce-digital-experience',
    subcategory: 'ecommerce-digital',
    title: 'المتاجر والتجربة الرقمية',
    description:
      'نصمم ونطور متاجر ومواقع تسهّل على العميل فهم المنتجات واتخاذ قرار الشراء، مع تحسين الصفحات والمحتوى ورحلة الطلب.',
    seoTitle: 'المتاجر والتجربة الرقمية',
  },
  'content-writing': {
    path: '/content-writing',
    subcategory: 'content-writing',
    title: 'المحتوى',
    description: 'نكتب محتوى واضحًا ومقنعًا يعبر عن العلامة، يشرح القيمة ويبني الثقة ويقود العميل إلى الإجراء المناسب.',
    seoTitle: 'كتابة المحتوى',
  },
  'events-management': {
    path: '/events-management',
    subcategory: 'events-management',
    title: 'تنظيم الحفلات والمناسبات',
    description:
      'حلول متكاملة لتنظيم الحفلات والمعارض والمؤتمرات والافتتاحات، من الفكرة والهوية إلى التجهيز والتشغيل والتغطية.',
    seoTitle: 'تنظيم الحفلات والمناسبات',
  },
}

export const SOLUTION_PUBLIC_SLUGS = [
  'restaurants-cafes',
  'b2b-industrial',
  'ecommerce',
  'real-estate',
  'education-training',
  'health-beauty',
] as const

export type SolutionPublicSlug = (typeof SOLUTION_PUBLIC_SLUGS)[number]

/** Map DATA SOURCE solution routes onto existing sector slugs. */
export const SOLUTION_SECTOR_ALIASES: Record<string, string> = {
  'b2b-industrial': 'industry-b2b',
  'health-beauty': 'health-clinics',
}

export function resolveSectorSlug(slug: string): string {
  return SOLUTION_SECTOR_ALIASES[slug] ?? slug
}

export const SERVICE_CANONICAL_ALIASES: Record<string, string> = {
  '/target-audience-research': 'audience-research',
  '/product-launch-strategy': 'launch-strategy',
  '/marketing-campaign-strategy': 'campaign-strategy',
  '/complete-brand-identity': 'brand-identity',
  '/professional-logo-design': 'logo-design',
  '/company-profile-design': 'company-profile',
  '/restaurant-menu-design': 'menu-design',
  '/advertising-design': 'ad-designs',
  '/event-visual-identity': 'event-designs',
  '/product-packaging-design': 'packaging-design',
  '/corporate-portrait-photography': 'corporate-photography',
  '/event-coverage': 'event-photography',
  '/commercial-video-production': 'ad-video',
  '/corporate-video-production': 'corporate-video',
  '/interview-filming': 'interviews',
  '/motion-graphics-video': 'motion-graphics',
  '/ecommerce-store-development': 'ecommerce-store',
  '/online-store-interface-design': 'store-ui-design',
  '/ecommerce-user-experience': 'ux-improvement',
  '/store-product-upload': 'product-upload',
  '/checkout-journey-optimization': 'purchase-journey-optimization',
  '/marketing-content-writing': 'content-writing',
  '/video-script-writing': 'video-scripts',
  '/content-ideas-bank': 'content-ideas',
  '/monthly-content-calendar': 'content-calendar',
  '/product-description-writing': 'product-descriptions',
  '/advertising-scripts': 'ad-scenarios',
  '/paid-advertising-management': 'advertising-campaign',
}

export const PACKAGE_CANONICAL_ALIASES: Record<string, string> = {
  '/new-business-launch-package': 'foundation-package',
  '/brand-building-package': 'brand-building',
  '/monthly-social-media-package': 'digital-marketing-package',
  '/product-launch-package': 'product-launch',
  '/ecommerce-store-package': 'ecommerce-launch-package',
  '/restaurant-marketing-package': 'restaurants',
  '/b2b-company-package': 'b2b-companies',
  '/complete-event-package': 'events-package',
}

export const OFFICIAL_PRINTING_SLUGS = [
  'luxury-business-cards',
  'company-brochure-printing',
  'corporate-stationery-printing',
  'custom-paper-bags',
  'printed-plastic-bags',
  'ecommerce-shipping-boxes',
  'custom-product-boxes',
  'luxury-gift-boxes',
  'food-packaging-boxes',
  'printed-paper-cups',
  'product-stickers-labels',
  'roll-label-printing',
  'restaurant-menu-printing',
  'rollup-banner-printing',
  'promotional-gifts-printing',
] as const

export const PACKAGE_FAQS: Array<{ question: string; answer: string }> = [
  {
    question: 'هل الأسعار نهائية؟',
    answer:
      'أسعار الخدمات محددة حسب النطاق الموضح. أما الطباعة والتغليف والفعاليات فتحدد بعد اعتماد المقاس والخامة والكمية والموقع ومدة التنفيذ.',
  },
  {
    question: 'هل تشمل الأسعار الضريبة؟',
    answer: 'يجب توضيح حالة الضريبة في عرض السعر والفاتورة النهائية.',
  },
  {
    question: 'هل تشمل إدارة الحملات الميزانية الإعلانية؟',
    answer: 'لا، أتعاب الإدارة منفصلة عن ميزانية الإعلان المدفوعة للمنصة الإعلانية.',
  },
  {
    question: 'هل تشمل الباقات تكلفة المشاهير؟',
    answer: 'لا، أسعار المشاهير وصناع المحتوى تحدد بشكل منفصل حسب الاسم والمنصة ونطاق التغطية.',
  },
  {
    question: 'هل يمكن تخصيص الباقة؟',
    answer: 'نعم، يمكن إضافة أو إزالة خدمات بعد جلسة التشخيص وإعداد نطاق العمل.',
  },
  {
    question: 'متى يبدأ التنفيذ؟',
    answer: 'يبدأ التنفيذ بعد اعتماد العرض، توقيع الاتفاقية، سداد الدفعة الأولى واستلام جميع المواد المطلوبة.',
  },
  {
    question: 'كم عدد التعديلات؟',
    answer: 'يتحدد عدد جولات التعديل في وصف كل خدمة أو عرض السعر. أي تعديلات خارج النطاق تسعّر بشكل منفصل.',
  },
  {
    question: 'كيف يتم تسعير الطباعة؟',
    answer: 'سعر المورد + التصميم عند الحاجة + الشحن + هامش المنصة. لا يُعتمد السعر قبل تحديد المواصفات والكمية واعتماد البروفة.',
  },
  {
    question: 'هل تقدمون الخدمات في جميع المدن؟',
    answer: 'نعم، تقدم المنصة خدماتها في مختلف مدن المملكة، ويعتمد التنفيذ الميداني والشحن على المدينة ونوع المشروع.',
  },
  {
    question: 'كيف أختار الخدمة المناسبة؟',
    answer: 'يمكنك استخدام المستشار الذكي أو حجز جلسة تشخيص لتحديد الخدمة أو الباقة الأنسب لنشاطك وهدفك وميزانيتك.',
  },
]
