import {
  SERVICE_CATEGORIES,
  type Package,
  type PackageCategory,
  type PackageTier,
  type PricingMode,
  type Service,
  type ServiceCategory,
} from '../types/api'

export const SERVICE_CATEGORY_LABELS: Record<ServiceCategory, string> = {
  STRATEGY: 'استراتيجية',
  CONTENT: 'محتوى',
  PRODUCTION: 'إنتاج',
  STORES: 'متاجر',
  CAMPAIGNS: 'حملات',
  PRINTING: 'طباعة',
  OTHER: 'أخرى',
}

export const PACKAGE_CATEGORY_LABELS: Record<PackageCategory, string> = {
  GENERAL: 'عامة',
  MARKETING: 'تسويق',
  EVENTS: 'فعاليات',
}

export function formatEnglishAmount(amount: string | number): string {
  const value = typeof amount === 'string' ? Number.parseFloat(amount) : amount

  if (Number.isNaN(value)) {
    return String(amount)
  }

  return value.toLocaleString('en-US', { maximumFractionDigits: 2 })
}

export function formatMoney(amount: string | number, currency = 'SAR'): string {
  const formatted = formatEnglishAmount(amount)
  if (Number.isNaN(typeof amount === 'string' ? Number.parseFloat(amount) : amount)) {
    return currency === 'SAR' ? `${amount} \u20C1` : `${amount} ${currency}`
  }

  return currency === 'SAR' ? `${formatted} \u20C1` : `${formatted} ${currency}`
}

/** Integer SAR halalas (1/100) so line totals avoid float drift. */
export function parseSarToHalalas(amount: string | number): number {
  if (typeof amount === 'number') {
    return Number.isFinite(amount) ? Math.round(amount * 100) : 0
  }

  const match = amount.trim().match(/^(-?)(\d+)(?:\.(\d{1,2}))?/)

  if (!match) {
    return 0
  }

  const sign = match[1] === '-' ? -1 : 1
  const whole = Number.parseInt(match[2], 10)
  const fraction = Number.parseInt((match[3] ?? '').padEnd(2, '0').slice(0, 2) || '0', 10)

  return sign * (whole * 100 + fraction)
}

export function formatHalalas(halalas: number, currency = 'SAR'): string {
  return formatMoney(halalas / 100, currency)
}

export function formatDuration(days: number | null): string | null {
  return days === null ? null : `${days} يوم`
}

export function packageHasDiscount(pkg: Package): boolean {
  return Number.parseFloat(pkg.discount_amount) > 0
}

export type DiscountPresentation = {
  amount: number
  percent: number | null
}

/** Whole percents use a percent label. Other discounts stay as an amount. */
export function discountPresentation(before: number, current: number): DiscountPresentation | null {
  if (!Number.isFinite(before) || !Number.isFinite(current) || before <= current || current < 0) {
    return null
  }

  const amount = Math.round((before - current) * 100) / 100
  const rawPercent = (amount / before) * 100
  const percent = Math.round(rawPercent)

  return {
    amount,
    percent: percent > 0 && Math.abs(rawPercent - percent) < 0.05 ? percent : null,
  }
}

export type ResolvedListPrice = {
  current: number
  price: number
  discountAmount: number
}

export function resolveListPrice(input: {
  current: string
  before: string
  discount: string
  requireCurrent: boolean
}): { ok: true; value: ResolvedListPrice } | { ok: false; message: string } {
  const currentRaw = input.current.trim()

  if (currentRaw === '') {
    if (input.requireCurrent) {
      return { ok: false, message: 'السعر الحالي مطلوب.' }
    }

    return resolveListPrice({ ...input, current: '0', requireCurrent: false })
  }

  const current = Number.parseFloat(currentRaw)
  if (!Number.isFinite(current) || current < 0) {
    return { ok: false, message: 'السعر الحالي يجب أن يكون رقماً غير سالب.' }
  }

  const beforeRaw = input.before.trim()
  const discountRaw = input.discount.trim()
  const before = beforeRaw === '' ? null : Number.parseFloat(beforeRaw)
  const discount = discountRaw === '' ? null : Number.parseFloat(discountRaw)

  if (before !== null && (!Number.isFinite(before) || before < 0)) {
    return { ok: false, message: 'السعر قبل الخصم يجب أن يكون رقماً غير سالب.' }
  }

  if (discount !== null && (!Number.isFinite(discount) || discount < 0)) {
    return { ok: false, message: 'قيمة الخصم يجب أن تكون رقماً غير سالب.' }
  }

  if (before !== null && before < current) {
    return { ok: false, message: 'السعر قبل الخصم يجب ألا يقل عن السعر الحالي.' }
  }

  if (before !== null && discount !== null && discount > 0 && before > current) {
    const expected = Math.round((before - current) * 100) / 100
    if (Math.abs(expected - discount) > 0.01) {
      return { ok: false, message: 'قيمة الخصم لا تطابق الفرق بين السعر قبل الخصم والسعر الحالي.' }
    }
  }

  if (before !== null && before > current) {
    const amount = discount !== null && discount > 0 ? discount : Math.round((before - current) * 100) / 100

    return { ok: true, value: { current, price: before, discountAmount: amount } }
  }

  if (discount !== null && discount > 0) {
    return {
      ok: true,
      value: { current, price: Math.round((current + discount) * 100) / 100, discountAmount: discount },
    }
  }

  return { ok: true, value: { current, price: current, discountAmount: 0 } }
}

export const PRICING_MODE_LABELS: Record<PricingMode, string> = {
  FIXED: 'سعر ثابت',
  STARTING_FROM: 'يبدأ من',
  QUOTE: 'طلب تسعير',
}

/**
 * Price shown on catalog cards. A package the owner has not priced is
 * displayed as a quote request instead of a fabricated number.
 */
export function packagePriceLabel(pkg: Pick<Package, 'pricing_mode' | 'final_price' | 'currency'>): string {
  const value = Number.parseFloat(pkg.final_price)

  if (pkg.pricing_mode === 'QUOTE' || !Number.isFinite(value) || value <= 0) {
    return PRICING_MODE_LABELS.QUOTE
  }

  const money = formatMoney(pkg.final_price, pkg.currency)

  return pkg.pricing_mode === 'STARTING_FROM' ? `يبدأ من ${money}` : money
}

export function tierPriceLabel(tier: Pick<PackageTier, 'price' | 'currency'>): string {
  const value = tier.price === null ? Number.NaN : Number.parseFloat(tier.price)

  if (!Number.isFinite(value) || value <= 0) {
    return PRICING_MODE_LABELS.QUOTE
  }

  return formatMoney(tier.price as string, tier.currency)
}

export function servicePriceLabel(
  service: Pick<Service, 'pricing_mode' | 'base_price' | 'currency'>,
): string {
  const value = Number.parseFloat(service.base_price)

  if (service.pricing_mode === 'QUOTE' || !Number.isFinite(value) || value <= 0) {
    return PRICING_MODE_LABELS.QUOTE
  }

  const money = formatMoney(service.base_price, service.currency)

  return service.pricing_mode === 'STARTING_FROM' ? `يبدأ من ${money}` : money
}

/**
 * Unique service categories actually present in a package's included items.
 * Used by marketing/event landing pages — no extra backend aggregation needed.
 */
export function packageServiceCategories(pkg: Package): ServiceCategory[] {
  const seen = new Set<ServiceCategory>()

  for (const item of pkg.items) {
    if (item.service) {
      seen.add(item.service.category)
    }
  }

  return SERVICE_CATEGORIES.filter((category) => seen.has(category))
}

export function uniquePackageServiceCategories(packages: Package[]): ServiceCategory[] {
  const seen = new Set<ServiceCategory>()

  for (const pkg of packages) {
    for (const category of packageServiceCategories(pkg)) {
      seen.add(category)
    }
  }

  return SERVICE_CATEGORIES.filter((category) => seen.has(category))
}

export function filterPackagesByServiceCategory(
  packages: Package[],
  category: ServiceCategory | null,
): Package[] {
  if (category === null) {
    return packages
  }

  return packages.filter((pkg) => packageServiceCategories(pkg).includes(category))
}
