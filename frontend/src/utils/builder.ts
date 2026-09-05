import type { Service, ServiceCategory } from '../types/api'
import { parseSarToHalalas } from './catalog'

export const BUILDER_STEPS = [
  { id: 1, label: 'الفئة' },
  { id: 2, label: 'الخدمات' },
  { id: 3, label: 'الكميات' },
  { id: 4, label: 'الإضافات' },
  { id: 5, label: 'الملخص' },
] as const

export type BuilderStepId = (typeof BUILDER_STEPS)[number]['id']

export const BUILDER_MIN_QUANTITY = 1
export const BUILDER_MAX_QUANTITY = 99

/**
 * MVP UI-level add-on estimates. Not catalog rows, not contractual prices.
 * Later checkout work can replace this with real commercial items.
 */
export type BuilderAddon = {
  id: string
  name: string
  description: string
  priceHalalas: number
}

export const BUILDER_ADDONS: BuilderAddon[] = [
  {
    id: 'priority',
    name: 'أولوية التنفيذ',
    description: 'تقدير لترتيب العمل في مقدمة قائمة التنفيذ.',
    priceHalalas: 80_000,
  },
  {
    id: 'rush',
    name: 'تسليم عاجل',
    description: 'تقدير لتسريع الجدول التشغيلي عند الإمكان.',
    priceHalalas: 150_000,
  },
  {
    id: 'extra-review',
    name: 'مراجعة إضافية',
    description: 'تقدير لجولة مراجعة إضافية قبل التسليم.',
    priceHalalas: 40_000,
  },
]

export const SERVICE_CATEGORY_BLURBS: Record<ServiceCategory, string> = {
  STRATEGY: 'تموضع وأهداف وخطة تواصل.',
  CONTENT: 'نصوص وجداول نشر جاهزة للتنفيذ.',
  PRODUCTION: 'تصميم وإنتاج بصري.',
  STORES: 'تهيئة المتجر واستقبال الطلبات.',
  CAMPAIGNS: 'إعلانات مدفوعة وتحسين أداء.',
  PRINTING: 'مواد مطبوعة بجودة تجارية.',
  OTHER: 'خدمات مساندة حسب احتياج المشروع.',
}

export function clampBuilderQuantity(value: number): number {
  if (!Number.isFinite(value)) {
    return BUILDER_MIN_QUANTITY
  }

  return Math.min(BUILDER_MAX_QUANTITY, Math.max(BUILDER_MIN_QUANTITY, Math.trunc(value)))
}

export function serviceLineHalalas(service: Service, quantity: number): number {
  return parseSarToHalalas(service.base_price) * clampBuilderQuantity(quantity)
}

export function servicesSubtotalHalalas(
  services: Service[],
  quantities: Record<number, number>,
): number {
  return services.reduce(
    (sum, service) => sum + serviceLineHalalas(service, quantities[service.id] ?? BUILDER_MIN_QUANTITY),
    0,
  )
}

export function addonsSubtotalHalalas(addonIds: string[]): number {
  return BUILDER_ADDONS.filter((addon) => addonIds.includes(addon.id)).reduce(
    (sum, addon) => sum + addon.priceHalalas,
    0,
  )
}

/**
 * Parallel-work estimate: the longest selected service duration.
 * Null durations are ignored. This is not a contractual deadline.
 */
export function estimatedDurationDays(services: Service[]): number | null {
  const days = services
    .map((service) => service.duration_days)
    .filter((value): value is number => value !== null && value > 0)

  return days.length === 0 ? null : Math.max(...days)
}
