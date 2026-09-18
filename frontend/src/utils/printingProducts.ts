import type { PrintingCategoryId } from './printing'

export type PrintingProduct = {
  id: string
  slug: string
  category: PrintingCategoryId
  name: string
  summary: string
  image: string
  imageAlt: string
  startingPrice: number
  currency: 'SAR'
  sizes: string[]
  materials: string[]
  isActive: boolean
  requiresQuote?: boolean
}

/**
 * Official Hebr & Ab3ad printing catalog (fallback when the API is unavailable).
 * Prices are quote-based — do not invent Sultan or platform unit prices here.
 */
export const PRINTING_PRODUCTS: PrintingProduct[] = [
  {
    id: 'luxury-business-cards',
    slug: 'luxury-business-cards',
    category: 'business-cards',
    name: 'كروت أعمال فاخرة',
    summary: 'كروت احترافية بخيارات طباعة وتشطيب متعددة تعكس قيمة العلامة من أول لقاء.',
    image: '/printing/business-cards-luxury.svg',
    imageAlt: 'كروت أعمال فاخرة',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['9 × 5 سم', 'مخصص'],
    materials: ['ورق مقوى', 'ورق مطفي', 'ورق لامع'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'company-brochure-printing',
    slug: 'company-brochure-printing',
    category: 'flyers',
    name: 'بروفايل وبروشور تعريفي',
    summary: 'بروشور منظم يعرض خدمات المنشأة ومميزاتها ويستخدم في الاجتماعات والمعارض ونقاط البيع.',
    image: '/printing/flyers-premium.svg',
    imageAlt: 'بروفايل وبروشور تعريفي',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['A4', 'A5', 'مخصص'],
    materials: ['ورق مطفي', 'ورق لامع'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'corporate-stationery-printing',
    slug: 'corporate-stationery-printing',
    category: 'custom-products',
    name: 'مطبوعات الهوية المكتبية',
    summary: 'باقة مؤسسية تشمل الورق الرسمي والأظرف والفولدرات والدفاتر والسندات بما يتوافق مع هوية المنشأة.',
    image: '/printing/custom.svg',
    imageAlt: 'مطبوعات الهوية المكتبية',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['مخصص'],
    materials: ['ورق مطفي', 'ورق مقوى'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'custom-paper-bags',
    slug: 'custom-paper-bags',
    category: 'bags',
    name: 'أكياس ورقية مخصصة',
    summary: 'أكياس مطبوعة بشعارك مناسبة للمتاجر والعطور والهدايا والمنتجات الفاخرة.',
    image: '/printing/bags-luxury.svg',
    imageAlt: 'أكياس ورقية مخصصة',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['صغير', 'متوسط', 'كبير'],
    materials: ['كرافت', 'ورق أبيض'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'printed-plastic-bags',
    slug: 'printed-plastic-bags',
    category: 'bags',
    name: 'أكياس بلاستيكية مطبوعة',
    summary: 'أكياس عملية واقتصادية للمطاعم والصيدليات والمتاجر والطلبات اليومية.',
    image: '/printing/bags.svg',
    imageAlt: 'أكياس بلاستيكية مطبوعة',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['مخصص'],
    materials: [],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'ecommerce-shipping-boxes',
    slug: 'ecommerce-shipping-boxes',
    category: 'boxes',
    name: 'كراتين شحن للمتاجر',
    summary: 'كراتين قوية لحماية الطلبات أثناء النقل، مع إمكانية طباعة الشعار وإضافة تجربة فتح مميزة.',
    image: '/printing/boxes.svg',
    imageAlt: 'كراتين شحن للمتاجر',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['قياسي', 'مخصص'],
    materials: ['كرتون مموج'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'custom-product-boxes',
    slug: 'custom-product-boxes',
    category: 'boxes',
    name: 'علب منتجات مخصصة',
    summary: 'علب مصممة حسب مقاس المنتج، مناسبة للعطور والأغذية والعناية والهدايا.',
    image: '/printing/boxes-product.svg',
    imageAlt: 'علب منتجات مخصصة',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['مخصص'],
    materials: ['كرتون مطوي', 'ورق مقوى'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'luxury-gift-boxes',
    slug: 'luxury-gift-boxes',
    category: 'boxes',
    name: 'بوكسات هدايا فاخرة',
    summary: 'بوكسات صلبة بتشطيبات أنيقة وبطانة أو فواصل داخلية، مناسبة للهدايا والمنتجات الراقية.',
    image: '/printing/boxes-gift.svg',
    imageAlt: 'بوكسات هدايا فاخرة',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['مخصص'],
    materials: ['ورق مقوى'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'food-packaging-boxes',
    slug: 'food-packaging-boxes',
    category: 'packaging',
    name: 'علب مطاعم وحلويات',
    summary: 'تغليف مناسب للتلامس الغذائي للوجبات والبرجر والحلويات والمخبوزات.',
    image: '/printing/packaging-food.svg',
    imageAlt: 'علب مطاعم وحلويات',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['مخصص'],
    materials: [],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'printed-paper-cups',
    slug: 'printed-paper-cups',
    category: 'cups',
    name: 'أكواب ورقية مطبوعة',
    summary: 'أكواب للمشروبات الساخنة والباردة تحمل شعار المقهى أو الفعالية.',
    image: '/printing/custom.svg',
    imageAlt: 'أكواب ورقية مطبوعة',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['4 أونصة', '7 أونصة', '8 أونصة', '12 أونصة', '16 أونصة'],
    materials: ['طبقة واحدة', 'مزدوجة'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'product-stickers-labels',
    slug: 'product-stickers-labels',
    category: 'stickers',
    name: 'استكرات وملصقات المنتجات',
    summary: 'ملصقات تعرض اسم المنتج والمعلومات والباركود وتمنح العبوة مظهرًا جاهزًا للبيع.',
    image: '/printing/stickers-labels.svg',
    imageAlt: 'استكرات وملصقات المنتجات',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['دائري', 'مخصص'],
    materials: ['ورقي', 'شفاف', 'مقاوم للماء'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'roll-label-printing',
    slug: 'roll-label-printing',
    category: 'stickers',
    name: 'رول ليبل للعبوات',
    summary: 'ملصقات على رول مناسبة للإنتاج المتكرر والتطبيق اليدوي أو الآلي.',
    image: '/printing/stickers.svg',
    imageAlt: 'رول ليبل للعبوات',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['مخصص'],
    materials: ['ورقي', 'شفاف', 'مقاوم للرطوبة'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'restaurant-menu-printing',
    slug: 'restaurant-menu-printing',
    category: 'menus',
    name: 'منيو مطاعم ومقاهي',
    summary: 'تصميم وطباعة منيو واضح بخامات تتحمل الاستخدام المتكرر، مع إمكانية إضافة QR.',
    image: '/printing/custom.svg',
    imageAlt: 'منيو مطاعم ومقاهي',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['A4', 'A5', 'مخصص'],
    materials: ['ورق مطفي', 'ورق لامع', 'ورق مقوى'],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'rollup-banner-printing',
    slug: 'rollup-banner-printing',
    category: 'posters',
    name: 'رول أب وبنرات',
    summary: 'حلول عرض للمعارض والافتتاحات ونقاط البيع تشمل الطباعة والتجهيز.',
    image: '/printing/posters-large.svg',
    imageAlt: 'رول أب وبنرات',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['مخصص'],
    materials: [],
    isActive: true,
    requiresQuote: true,
  },
  {
    id: 'promotional-gifts-printing',
    slug: 'promotional-gifts-printing',
    category: 'promo',
    name: 'هدايا دعائية مطبوعة',
    summary: 'أقلام ودفاتر وأكواب وحقائب ومنتجات دعائية تحمل هوية المنشأة.',
    image: '/printing/custom-promo.svg',
    imageAlt: 'هدايا دعائية مطبوعة',
    startingPrice: 0,
    currency: 'SAR',
    sizes: ['مخصص'],
    materials: [],
    isActive: true,
    requiresQuote: true,
  },
]

export function getPrintingProducts(category?: PrintingCategoryId | null): PrintingProduct[] {
  const active = PRINTING_PRODUCTS.filter((product) => product.isActive)

  if (!category) {
    return active
  }

  return active.filter((product) => product.category === category)
}

export function getPrintingProductBySlug(slug: string): PrintingProduct | undefined {
  return PRINTING_PRODUCTS.find((product) => product.slug === slug && product.isActive)
}

export function printingCustomizePath(slug: string): string {
  return `/printing/customize/${encodeURIComponent(slug)}`
}
