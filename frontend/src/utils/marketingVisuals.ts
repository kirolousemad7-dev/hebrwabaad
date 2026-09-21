/**
 * Marketing visuals — swap `image` to a photo URL when real assets are ready.
 * Leave `image` empty to use the brand composition fallback.
 *
 * Example later:
 *   marketingVisuals.hero.image = '/uploads/marketing/hero.jpg'
 */
export type MarketingVisual = {
  /** Photo / asset URL. Empty = brand geometric fallback. */
  image: string
  alt: string
  /** Optional local SVG accent (from /public) */
  accentSvg?: string
}

export const marketingVisuals = {
  hero: {
    image: '',
    alt: 'منصة حبر وأبعاد لخدمات الأعمال والنمو',
    accentSvg: '/brand/mark.png',
  } satisfies MarketingVisual,

  services: {
    strategy: {
      image: '',
      alt: 'تشخيص الأعمال والاستراتيجية',
      accentSvg: '/printing/business-cards.svg',
    },
    branding: {
      image: '',
      alt: 'الهوية والتصميم',
      accentSvg: '/printing/business-cards-luxury.svg',
    },
    digital: {
      image: '',
      alt: 'التسويق والمحتوى الرقمي',
      accentSvg: '/printing/flyers.svg',
    },
    ecommerce: {
      image: '',
      alt: 'المتاجر والتجربة الرقمية',
      accentSvg: '/printing/packaging.svg',
    },
    printing: {
      image: '',
      alt: 'الطباعة والتغليف',
      accentSvg: '/printing/boxes.svg',
    },
    events: {
      image: '',
      alt: 'تنظيم الفعاليات والمناسبات',
      accentSvg: '/printing/posters.svg',
    },
  } satisfies Record<string, MarketingVisual>,

  packages: {
    basic: {
      image: '',
      alt: 'الباقة الأساسية',
      accentSvg: '/printing/stickers.svg',
    },
    professional: {
      image: '',
      alt: 'الباقة الاحترافية',
      accentSvg: '/printing/business-cards-premium.svg',
    },
    integrated: {
      image: '',
      alt: 'الباقة المتكاملة',
      accentSvg: '/printing/packaging-branded.svg',
    },
  } satisfies Record<string, MarketingVisual>,

  about: {
    image: '',
    alt: 'من نحن — حبر وأبعاد',
    accentSvg: '/brand/mark.png',
  } satisfies MarketingVisual,

  contact: {
    image: '',
    alt: 'تواصل معنا',
    accentSvg: '/brand/logo.png',
  } satisfies MarketingVisual,
} as const

export type LandingServiceId = keyof typeof marketingVisuals.services
export type LandingPackageId = keyof typeof marketingVisuals.packages
