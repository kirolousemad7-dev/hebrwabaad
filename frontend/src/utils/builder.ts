import type { Service, ServiceCategory } from '../types/api'
import { parseSarToHalalas } from './catalog'

export const BUILDER_STEPS = [
  { id: 1, label: 'الخدمات' },
  { id: 2, label: 'التفاصيل' },
  { id: 3, label: 'الإضافات' },
  { id: 4, label: 'المراجعة' },
] as const

export type BuilderStepId = (typeof BUILDER_STEPS)[number]['id']

export const BUILDER_MIN_QUANTITY = 1
export const BUILDER_MAX_QUANTITY = 99

export type BuilderAddon = {
  id: string
  name: string
  description: string
  pricing_mode?: 'FIXED' | 'STARTING_FROM' | 'QUOTE' | 'PER_UNIT' | 'PERCENTAGE'
  price_halalas?: number | null
  is_available?: boolean
  service_ids?: number[]
}

export type SelectedServiceConfig = {
  service: Service
  quantity: number
  addonIds: string[]
  notes: string
}

/** Fallback — prefer API catalog addons when loaded. */
export const BUILDER_ADDONS: BuilderAddon[] = []

export const SERVICE_CATEGORY_BLURBS: Record<ServiceCategory, string> = {
  STRATEGY: 'تموضع وأهداف وخطة تواصل.',
  CONTENT: 'نصوص وجداول نشر جاهزة للتنفيذ.',
  PRODUCTION: 'تصميم وإنتاج بصري.',
  STORES: 'تهيئة المتجر واستقبال الطلبات.',
  CAMPAIGNS: 'إعلانات مدفوعة وتحسين أداء.',
  PRINTING: 'مواد مطبوعة بجودة تجارية.',
  OTHER: 'خدمات مساندة حسب احتياج المشروع.',
}

/** PDF-oriented grouping labels for the builder UI. */
export const BUILDER_CATEGORY_ORDER: ServiceCategory[] = [
  'STRATEGY',
  'PRODUCTION',
  'CONTENT',
  'STORES',
  'CAMPAIGNS',
  'PRINTING',
  'OTHER',
]

export function clampBuilderQuantity(value: number): number {
  if (!Number.isFinite(value)) {
    return BUILDER_MIN_QUANTITY
  }

  return Math.min(BUILDER_MAX_QUANTITY, Math.max(BUILDER_MIN_QUANTITY, Math.trunc(value)))
}

export function serviceLineHalalas(service: Service, quantity: number): number {
  if (!service.is_chargeable) {
    return 0
  }

  return parseSarToHalalas(service.base_price) * clampBuilderQuantity(quantity)
}

export function selectedServicesSubtotalHalalas(configs: SelectedServiceConfig[]): number {
  return configs.reduce(
    (sum, row) => sum + serviceLineHalalas(row.service, row.quantity),
    0,
  )
}

export function addonsSubtotalHalalas(addons: BuilderAddon[], addonIds: string[]): number {
  return addons
    .filter((addon) => addonIds.includes(addon.id) && addon.pricing_mode === 'FIXED' && (addon.price_halalas ?? 0) > 0)
    .reduce((sum, addon) => sum + (addon.price_halalas ?? 0), 0)
}

export function packageHasIncompletePricing(
  configs: SelectedServiceConfig[],
  addons: BuilderAddon[],
  packageAddonIds: string[],
): boolean {
  const serviceIncomplete = configs.some((row) => !row.service.is_chargeable)
  const lineAddonIncomplete = configs.some((row) =>
    row.addonIds.some((id) => {
      const addon = addons.find((item) => item.id === id)
      return !addon || addon.pricing_mode !== 'FIXED' || !(addon.price_halalas && addon.price_halalas > 0)
    }),
  )
  const packageIncomplete = packageAddonIds.some((id) => {
    const addon = addons.find((item) => item.id === id)
    return !addon || addon.pricing_mode !== 'FIXED' || !(addon.price_halalas && addon.price_halalas > 0)
  })

  return serviceIncomplete || lineAddonIncomplete || packageIncomplete
}

/**
 * Parallel-work estimate: longest selected service duration.
 * Null durations ignored — not a contractual deadline.
 */
export function estimatedDurationDays(services: Service[]): number | null {
  const days = services
    .map((service) => service.duration_days)
    .filter((value): value is number => value !== null && value > 0)

  return days.length === 0 ? null : Math.max(...days)
}

export function addonPriceLabel(addon: BuilderAddon): string {
  if (addon.pricing_mode === 'FIXED' && (addon.price_halalas ?? 0) > 0) {
    return `${((addon.price_halalas ?? 0) / 100).toFixed(2)} ر.س`
  }

  if (addon.pricing_mode === 'STARTING_FROM' && (addon.price_halalas ?? 0) > 0) {
    return `يبدأ من ${((addon.price_halalas ?? 0) / 100).toFixed(2)} ر.س`
  }

  return 'طلب تسعير'
}

export function addonsForService(addons: BuilderAddon[], serviceId: number): BuilderAddon[] {
  return addons.filter((addon) => {
    if (!addon.is_available) {
      return false
    }

    const ids = addon.service_ids ?? []
    if (ids.length === 0) {
      return false
    }

    return ids.includes(serviceId)
  })
}

export function packageLevelAddons(addons: BuilderAddon[]): BuilderAddon[] {
  return addons.filter((addon) => addon.is_available !== false && (addon.service_ids?.length ?? 0) === 0)
}
