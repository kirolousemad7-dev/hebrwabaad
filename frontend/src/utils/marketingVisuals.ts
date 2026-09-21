/**
 * Marketing visuals — swap `image` to a photo URL when real assets are ready.
 *
 * Storefront photo: `/marketing/storefront.jpg` (physical branch reference).
 * Example later:
 *   marketingVisuals.services.printing.image = '/marketing/printing.jpg'
 */
export type MarketingVisual = {
  /** Photo / asset URL. Empty = brand geometric fallback. */
  image: string
  alt: string
  /** Optional local SVG/PNG accent (from /public) */
  accentSvg?: string
}

export const marketingVisuals = {
  hero: {
    image: '/marketing/storefront.jpg',
    alt: 'واجهة فرع حبر وأبعاد',
    accentSvg: '/brand/mark.png',
  } satisfies MarketingVisual,

  services: {
    strategy: {
      image: '/marketing/storefront.jpg',
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
    image: '/marketing/storefront.jpg',
    alt: 'من نحن — حبر وأبعاد',
    accentSvg: '/brand/mark.png',
  } satisfies MarketingVisual,

  contact: {
    image: '/marketing/storefront.jpg',
    alt: 'تواصل معنا',
    accentSvg: '/brand/logo.png',
  } satisfies MarketingVisual,
} as const

export type LandingServiceId = keyof typeof marketingVisuals.services
export type LandingPackageId = keyof typeof marketingVisuals.packages
