import { APP_NAME } from './constants'

export const SEO_PAGE_KEYS = [
  'home',
  'services',
  'packages',
  'portfolio',
  'about',
  'contact',
  'suppliers',
  'marketing-packages',
  'event-packages',
  'printing-packaging',
  'consultant',
  'build-package',
] as const

export type SeoPageKey = (typeof SEO_PAGE_KEYS)[number]

export type SeoPage = {
  page_key: SeoPageKey | string
  label?: string
  title: string | null
  description: string | null
  keywords: string | null
  canonical_url: string | null
  og_title: string | null
  og_description: string | null
  og_image: string | null
  twitter_title: string | null
  twitter_description: string | null
  twitter_image: string | null
  robots: string
  schema_json: Record<string, unknown> | unknown[] | null
  updated_at?: string | null
}

export type PageSeoDefaults = {
  title: string
  description: string
  keywords?: string
  robots?: string
  breadcrumbs?: Array<{ name: string; path: string }>
  schemaType?: 'home' | 'services' | 'printing' | 'marketing' | 'events' | 'packages' | 'generic'
}

export const SEO_PAGE_PATHS: Record<SeoPageKey, string> = {
  home: '/',
  services: '/services',
  packages: '/packages',
  portfolio: '/portfolio',
  about: '/about',
  contact: '/contact',
  suppliers: '/suppliers',
  'marketing-packages': '/marketing-packages',
  'event-packages': '/event-packages',
  'printing-packaging': '/printing-packaging',
  consultant: '/consultant',
  'build-package': '/build-package',
}

/** Static public paths included in sitemap (dynamic supplier URLs come from the API). */
export const PUBLIC_SITEMAP_PATHS = [
  '/',
  '/services',
  '/packages',
  '/marketing-packages',
  '/event-packages',
  '/printing-packaging',
  '/build-package',
  '/consultant',
  '/suppliers',
  '/portfolio',
  '/about',
  '/contact',
] as const

export const DEFAULT_PAGE_SEO: Record<SeoPageKey, PageSeoDefaults> = {
  home: {
    title: 'حبر وأبعاد | برمجة، تسويق، تصميم، طباعة وتنظيم فعاليات',
    description:
      'حبر وأبعاد وكالة متكاملة تقدم خدمات البرمجة وتطوير المواقع، التسويق الرقمي، التصميم والهوية البصرية، الطباعة والتغليف وتنظيم الفعاليات من الفكرة حتى التنفيذ.',
    keywords: 'حبر وأبعاد, برمجة مواقع, تسويق رقمي, هوية بصرية, طباعة, تغليف, تنظيم فعاليات',
    breadcrumbs: [{ name: 'الرئيسية', path: '/' }],
    schemaType: 'home',
  },
  services: {
    title: 'خدمات حبر وأبعاد | حلول متكاملة للأعمال والعلامات التجارية',
    description:
      'اكتشف خدمات حبر وأبعاد في البرمجة، تطوير المواقع، التسويق الرقمي، التصميم، الهوية البصرية، الطباعة، التغليف وتنظيم الفعاليات.',
    keywords: 'خدمات حبر وأبعاد, تصميم مواقع, برمجة مواقع, تسويق رقمي, طباعة, فعاليات',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'الخدمات', path: '/services' },
    ],
    schemaType: 'services',
  },
  packages: {
    title: 'باقات حبر وأبعاد | حلول جاهزة لمشروعك',
    description: 'تصفح الباقات الجاهزة من حبر وأبعاد أو صمّم باقتك بنفسك مع تسعير واضح ومسار تنفيذ منظم.',
    keywords: 'باقات تسويق, باقات تصميم, باقات حبر وأبعاد',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'الباقات', path: '/packages' },
    ],
    schemaType: 'packages',
  },
  'marketing-packages': {
    title: 'خدمات التسويق الرقمي وإدارة الحملات | حبر وأبعاد',
    description:
      'نساعد علامتك التجارية على النمو من خلال استراتيجيات التسويق الرقمي، صناعة المحتوى وإدارة الحملات الإعلانية على منصات التواصل.',
    keywords: 'التسويق الرقمي, إدارة الحملات, محتوى, سوشيال ميديا',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'الباقات التسويقية', path: '/marketing-packages' },
    ],
    schemaType: 'marketing',
  },
  'event-packages': {
    title: 'تنظيم الفعاليات والإيفنتات | حبر وأبعاد',
    description: 'خدمات تخطيط وتنظيم الفعاليات والإيفنتات من الفكرة والتجهيز وحتى التنفيذ والإنتاج النهائي.',
    keywords: 'تنظيم فعاليات, إيفنتات, إنتاج فعاليات',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'باقات الفعاليات', path: '/event-packages' },
    ],
    schemaType: 'events',
  },
  'printing-packaging': {
    title: 'خدمات الطباعة والتغليف | حبر وأبعاد',
    description:
      'حلول متكاملة للطباعة والتغليف تشمل الكروت، الفلاير، الاستيكرات، البوكسات، الأكياس، البوسترات وحلول الطباعة المخصصة.',
    keywords: 'طباعة, تغليف, كروت شخصية, فلايرز, بوكسات, باكدجنج',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'الطباعة والتغليف', path: '/printing-packaging' },
    ],
    schemaType: 'printing',
  },
  consultant: {
    title: 'المستشار الذكي | حبر وأبعاد',
    description: 'أجب عن أسئلة بسيطة واحصل على توصية مناسبة من خدمات وباقات حبر وأبعاد لمشروعك.',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'المستشار الذكي', path: '/consultant' },
    ],
    schemaType: 'generic',
  },
  'build-package': {
    title: 'صمّم باقتك | حبر وأبعاد',
    description: 'اختر الخدمات والكميات والإضافات وشاهد السعر التقديري ومدة التنفيذ لباقة مخصّصة لمشروعك.',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'صمّم باقتك', path: '/build-package' },
    ],
    schemaType: 'packages',
  },
  portfolio: {
    title: 'أعمال حبر وأبعاد | معرض الأعمال',
    description: 'اطّلع على نماذج من أعمال حبر وأبعاد في الهوية، المواقع، التسويق، الطباعة والفعاليات.',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'أعمالنا', path: '/portfolio' },
    ],
    schemaType: 'generic',
  },
  about: {
    title: 'من نحن | حبر وأبعاد',
    description:
      'حبر وأبعاد شريكك في البرمجة والتسويق والطباعة والتغليف وتنظيم الفعاليات، بمسار عمل واضح من الفكرة حتى التسليم.',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'من نحن', path: '/about' },
    ],
    schemaType: 'generic',
  },
  contact: {
    title: 'تواصل معنا | حبر وأبعاد',
    description: 'أرسل فكرتك أو استفسارك لفريق حبر وأبعاد وابدأ مشروعك بمسار واضح.',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'تواصل معنا', path: '/contact' },
    ],
    schemaType: 'generic',
  },
  suppliers: {
    title: 'شركاؤنا من الموردين | حبر وأبعاد',
    description: 'استكشف موردين موثوقين للطباعة والتغليف والمواد الدعائية بعد اعتمادهم من حبر وأبعاد.',
    breadcrumbs: [
      { name: 'الرئيسية', path: '/' },
      { name: 'الموردون', path: '/suppliers' },
    ],
    schemaType: 'generic',
  },
}

const PRIVATE_PREFIXES = [
  '/dashboard',
  '/customer',
  '/owner',
  '/workspace',
  '/supplier',
  '/crm',
  '/printing-requests',
  '/printing/customize',
  '/login',
  '/register',
  '/forgot-password',
  '/reset-password',
] as const

export function siteOrigin(fallbackOrigin?: string): string {
  const configured = import.meta.env.VITE_PUBLIC_SITE_URL?.replace(/\/$/, '')
  if (configured && !/localhost|127\.0\.0\.1/i.test(configured)) {
    return configured
  }

  if (import.meta.env.PROD && configured) {
    return configured
  }

  const runtime = fallbackOrigin?.replace(/\/$/, '')
  if (runtime && !import.meta.env.PROD) {
    return runtime
  }

  return configured || runtime || ''
}

export function absoluteUrl(pathname: string, origin: string): string {
  const path = pathname.startsWith('/') ? pathname : `/${pathname}`
  return `${origin.replace(/\/$/, '')}${path === '/' ? '/' : path}`
}

export function seoKeyFromPath(pathname: string): SeoPageKey | null {
  if (pathname === '/') {
    return 'home'
  }

  if (pathname === '/suppliers') {
    return 'suppliers'
  }

  if (pathname.startsWith('/suppliers/')) {
    return null
  }

  if (pathname.startsWith('/services/') && pathname !== '/services') {
    return null
  }

  const match = (Object.entries(SEO_PAGE_PATHS) as Array<[SeoPageKey, string]>).find(
    ([key, path]) => key !== 'home' && key !== 'suppliers' && (pathname === path || pathname.startsWith(`${path}/`)),
  )

  return match?.[0] ?? null
}

export function isPrivateSeoPath(pathname: string): boolean {
  return PRIVATE_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`))
}

export function isSupplierPublicPath(pathname: string): boolean {
  return pathname.startsWith('/suppliers/') && pathname !== '/suppliers'
}

export function titleLengthHint(value: string): { count: number; tone: 'ok' | 'warn' } {
  return { count: value.length, tone: value.length > 60 ? 'warn' : 'ok' }
}

export function descriptionLengthHint(value: string): { count: number; tone: 'ok' | 'warn' } {
  return { count: value.length, tone: value.length > 160 ? 'warn' : 'ok' }
}

export function absoluteAssetUrl(value: string | null | undefined, origin: string): string | undefined {
  if (!value) {
    return undefined
  }

  if (value.startsWith('http://') || value.startsWith('https://')) {
    return value
  }

  return `${origin}${value.startsWith('/') ? value : `/${value}`}`
}

export function breadcrumbSchema(
  items: Array<{ name: string; path: string }>,
  origin: string,
): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: item.name,
      item: absoluteUrl(item.path, origin),
    })),
  }
}

export function organizationSchema(origin: string): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'Hebr & Ab3ad',
    alternateName: APP_NAME,
    url: origin,
    logo: `${origin}/brand/logo.png`,
  }
}

export function websiteSchema(origin: string): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: APP_NAME,
    alternateName: 'Hebr & Ab3ad',
    url: origin,
    inLanguage: 'ar',
  }
}

export function serviceCatalogSchema(origin: string, pagePath: string, name: string, description: string): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'Service',
    name,
    description,
    provider: {
      '@type': 'Organization',
      name: 'Hebr & Ab3ad',
      alternateName: APP_NAME,
      url: origin,
    },
    areaServed: 'SA',
    url: absoluteUrl(pagePath, origin),
  }
}

export function buildPageSchema(
  key: SeoPageKey,
  origin: string,
  defaults: PageSeoDefaults,
  override?: Record<string, unknown> | unknown[] | null,
): unknown {
  if (override) {
    return override
  }

  const crumbs = defaults.breadcrumbs?.length
    ? breadcrumbSchema(defaults.breadcrumbs, origin)
    : null

  if (defaults.schemaType === 'home') {
    return {
      '@context': 'https://schema.org',
      '@graph': [organizationSchema(origin), websiteSchema(origin), crumbs].filter(Boolean),
    }
  }

  const serviceName =
    defaults.schemaType === 'printing'
      ? 'خدمات الطباعة والتغليف'
      : defaults.schemaType === 'marketing'
        ? 'خدمات التسويق الرقمي'
        : defaults.schemaType === 'events'
          ? 'تنظيم الفعاليات والإيفنتات'
          : defaults.schemaType === 'services'
            ? 'خدمات حبر وأبعاد'
            : null

  if (serviceName) {
    return {
      '@context': 'https://schema.org',
      '@graph': [
        serviceCatalogSchema(origin, SEO_PAGE_PATHS[key], serviceName, defaults.description),
        crumbs,
      ].filter(Boolean),
    }
  }

  return crumbs
}
