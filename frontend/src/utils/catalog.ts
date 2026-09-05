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

export function formatMoney(amount: string | number, currency = 'SAR'): string {
  const value = typeof amount === 'string' ? Number.parseFloat(amount) : amount

  if (Number.isNaN(value)) {
    return `${amount} ${currency}`
  }

  return `${value.toLocaleString('ar-SA', { maximumFractionDigits: 2 })} ${currency}`
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
