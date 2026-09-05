import { printingCustomPath } from './orderIntent'

/**
 * Frontend-only printing/packaging categories.
 * Product listings live in `printingProducts.ts` and map to these ids.
 */
export const PRINTING_CATEGORY_IDS = [
  'business-cards',
  'flyers',
  'stickers',
  'boxes',
  'bags',
  'packaging',
  'posters',
  'custom-products',
] as const

export type PrintingCategoryId = (typeof PRINTING_CATEGORY_IDS)[number]

export type PrintingCategory = {
  id: PrintingCategoryId
  name: string
  description: string
  href: string
}

export const PRINTING_CUSTOM_PATH = printingCustomPath()

export function printingCategoryPath(id: PrintingCategoryId): string {
  return `/printing-packaging?category=${id}#printing-products`
}

export function isPrintingCategoryId(value: string | null): value is PrintingCategoryId {
  return value !== null && (PRINTING_CATEGORY_IDS as readonly string[]).includes(value)
}

export const PRINTING_CATEGORIES: PrintingCategory[] = [
  {
    id: 'business-cards',
    name: 'كروت شخصية',
    description: 'كروت تعريف أنيقة بخيارات خامات وتشطيب تناسب الهوية.',
    href: printingCategoryPath('business-cards'),
  },
  {
    id: 'flyers',
    name: 'فلايرز',
    description: 'منشورات ترويجية للمناسبات والعروض بنسخ واضحة وجاهزة للتوزيع.',
    href: printingCategoryPath('flyers'),
  },
  {
    id: 'stickers',
    name: 'استيكرات',
    description: 'ملصقات للمنتجات والتغليف والفعاليات بقصّات متعددة.',
    href: printingCategoryPath('stickers'),
  },
  {
    id: 'boxes',
    name: 'علب',
    description: 'علب جاهزة لهدايا المنتجات والشحن بهوية علامتك.',
    href: printingCategoryPath('boxes'),
  },
  {
    id: 'bags',
    name: 'أكياس',
    description: 'أكياس تسوّق وكرتون تحمل شعارك وتُكمل تجربة الاستلام.',
    href: printingCategoryPath('bags'),
  },
  {
    id: 'packaging',
    name: 'تغليف',
    description: 'حلول تغليف متكاملة تحمي المنتج وتُظهره بشكل احترافي.',
    href: printingCategoryPath('packaging'),
  },
  {
    id: 'posters',
    name: 'بوسترات',
    description: 'بوسترات ولافتات داخلية وخارجية بأحجام تناسب المساحة.',
    href: printingCategoryPath('posters'),
  },
  {
    id: 'custom-products',
    name: 'منتجات مخصصة',
    description: 'تنفيذ خاص عندما لا تكفي الخيارات الجاهزة.',
    href: printingCategoryPath('custom-products'),
  },
]
