export type PlatformSocialItem = {
  platform: string
  url: string
  order: number
  enabled?: boolean
}

export type PlatformNavItem = {
  id: string
  label: string
  path: string
  order: number
  enabled?: boolean
}

export type PlatformSettingsPublic = {
  brand: {
    name_ar: string
    name_en: string
    tagline: string | null
    logo_url: string
    logo_secondary_url: string | null
    mark_url: string
    favicon_url: string
  }
  business: {
    trade_name: string | null
    short_description: string | null
    country: string | null
    city: string | null
    working_hours: string | null
    working_days: string | null
    address?: string | null
    district?: string | null
    postal_code?: string | null
  }
  contact: {
    phone: string | null
    whatsapp_url: string | null
    email: string | null
    support_email: string | null
    sales_email: string | null
    address: string | null
    maps_url: string | null
  }
  social: PlatformSocialItem[]
  website: {
    footer_description: string | null
    copyright_text: string
    show_contact_in_footer: boolean
    show_social_in_footer: boolean
    show_quick_links: boolean
    features: Record<string, boolean>
    navigation: PlatformNavItem[]
  }
  homepage: {
    hero_heading: string | null
    hero_subheading: string | null
    hero_primary_cta_label: string | null
    hero_primary_cta_path: string | null
    hero_secondary_cta_label: string | null
    hero_secondary_cta_path: string | null
    section_titles: Record<string, string | null>
    section_descriptions: Record<string, string | null>
  }
  cta: Array<{ id: string; label: string; path: string; enabled?: boolean }>
  printing: {
    pickup_enabled: boolean
    manual_delivery_enabled: boolean
    delivery_cities: string[]
  }
  events: { public_intro: string | null }
  customer: {
    welcome_text: string | null
    support_contact: string | null
    show_orders: boolean
    show_quotes: boolean
    show_payments: boolean
    show_files: boolean
    show_approvals: boolean
  }
  seo: {
    site_title: string | null
    default_meta_title: string | null
    default_meta_description: string | null
    default_og_image_url: string | null
    robots_index: boolean
  }
}

/** Full owner manage payload (groups as stored). */
export type PlatformSettingsManage = {
  brand: Record<string, unknown>
  business: Record<string, unknown>
  contact: Record<string, unknown>
  social: { items: Array<PlatformSocialItem & { enabled: boolean }> }
  website: Record<string, unknown>
  homepage: Record<string, unknown>
  cta: { items: Array<{ id: string; label: string; path: string; enabled: boolean }> }
  printing: Record<string, unknown>
  events: Record<string, unknown>
  customer: Record<string, unknown>
  seo: Record<string, unknown>
}

export const PLATFORM_SETTINGS_DEFAULTS: PlatformSettingsPublic = {
  brand: {
    name_ar: 'حبر وأبعاد',
    name_en: 'Hebr & Ab3ad',
    tagline: 'نمنح أعمالك أبعادًا للنمو',
    logo_url: '/brand/logo.png',
    logo_secondary_url: null,
    mark_url: '/brand/mark.png',
    favicon_url: '/brand/favicon-32.png',
  },
  business: {
    trade_name: 'منصة حبر وأبعاد لخدمات الأعمال والنمو',
    short_description: 'منصة متكاملة تبدأ بتشخيص نشاطك، ثم تخطيط النمو واختيار الخدمات والموردين، وصولًا إلى التنفيذ والقياس والمتابعة.',
    country: 'السعودية',
    city: 'المدينة المنورة',
    working_hours: null,
    working_days: null,
  },
  contact: {
    phone: null,
    whatsapp_url: null,
    email: null,
    support_email: null,
    sales_email: null,
    address: null,
    maps_url: null,
  },
  social: [],
  website: {
    footer_description: 'منصة سعودية لخدمات تشخيص الأعمال والتسويق والمحتوى والتصوير والمتاجر والطباعة والتغليف وتنظيم المعارض والافتتاحات في جميع مدن المملكة.',
    copyright_text: `© ${new Date().getFullYear()} حبر وأبعاد. جميع الحقوق محفوظة.`,
    show_contact_in_footer: true,
    show_social_in_footer: true,
    show_quick_links: true,
    features: {
      show_services: true,
      show_packages: true,
      show_build_package: true,
      show_sectors: true,
      show_printing: true,
      show_events: true,
      show_portfolio: true,
      show_suppliers: true,
      show_consultant: true,
    },
    navigation: [
      { id: 'consultant', label: 'اكتشف احتياجك', path: '/consultant', order: 10 },
      { id: 'services', label: 'الخدمات', path: '/services', order: 20 },
      { id: 'packages', label: 'الباقات', path: '/packages', order: 30 },
      { id: 'build-package', label: 'صمّم باقتك', path: '/build-package', order: 40 },
      { id: 'sectors', label: 'القطاعات', path: '/sectors', order: 50 },
      { id: 'printing', label: 'الطباعة والتغليف', path: '/printing-packaging', order: 60 },
      { id: 'events', label: 'الفعاليات', path: '/events', order: 70 },
      { id: 'portfolio', label: 'أعمالنا', path: '/portfolio', order: 80 },
      { id: 'suppliers', label: 'الموردين', path: '/suppliers', order: 90 },
    ],
  },
  homepage: {
    hero_heading: 'نمنح أعمالك أبعادًا للنمو',
    hero_subheading:
      'منصة متكاملة تبدأ بتشخيص نشاطك، ثم تخطيط النمو واختيار الخدمات والموردين، وصولًا إلى التنفيذ والقياس والمتابعة.',
    hero_primary_cta_label: 'اكتشف احتياجك',
    hero_primary_cta_path: '/consultant',
    hero_secondary_cta_label: 'تصفح الخدمات',
    hero_secondary_cta_path: '/services',
    section_titles: {
      services: 'خدماتنا',
      packages: 'حلول النمو',
      build_package: 'صمّم باقتك',
      printing: 'الطباعة والتغليف',
      events: 'الفعاليات',
      portfolio: 'أعمالنا',
      suppliers: 'الموردون',
    },
    section_descriptions: {},
  },
  cta: [
    { id: 'contact', label: 'تواصل معنا', path: '/contact' },
    { id: 'consultant', label: 'احجز استشارة', path: '/consultant' },
    { id: 'whatsapp', label: 'واتساب', path: 'whatsapp' },
    { id: 'start', label: 'ابدأ مشروعك', path: '/register' },
  ],
  printing: {
    pickup_enabled: true,
    manual_delivery_enabled: true,
    delivery_cities: [],
  },
  events: { public_intro: null },
  customer: {
    welcome_text: 'أهلاً بك في مساحة عملك',
    support_contact: null,
    show_orders: true,
    show_quotes: true,
    show_payments: true,
    show_files: true,
    show_approvals: true,
  },
  seo: {
    site_title: 'حبر وأبعاد | خدمات التسويق والطباعة وتطوير الأعمال',
    default_meta_title: 'حبر وأبعاد | خدمات التسويق والطباعة وتطوير الأعمال',
    default_meta_description:
      'منصة سعودية لخدمات تشخيص الأعمال والتسويق والمحتوى والتصوير والمتاجر والطباعة والتغليف وتنظيم المعارض والافتتاحات في جميع مدن المملكة.',
    default_og_image_url: '/brand/logo.png',
    robots_index: true,
  },
}

const FEATURE_BY_NAV_ID: Record<string, string> = {
  consultant: 'show_consultant',
  services: 'show_services',
  packages: 'show_packages',
  'build-package': 'show_build_package',
  sectors: 'show_sectors',
  printing: 'show_printing',
  events: 'show_events',
  portfolio: 'show_portfolio',
  suppliers: 'show_suppliers',
}

export function filterPublicNavigation(settings: PlatformSettingsPublic): PlatformNavItem[] {
  const features = settings.website.features ?? {}
  return [...settings.website.navigation]
    .filter((item) => {
      const featureKey = FEATURE_BY_NAV_ID[item.id]
      if (featureKey && features[featureKey] === false) {
        return false
      }
      return true
    })
    .sort((a, b) => a.order - b.order)
}
