import { describe, expect, it } from 'vitest'
import type { Package, PackageTier, Service } from '../types/api'
import {
  PRICING_MODE_LABELS,
  packagePriceLabel,
  servicePriceLabel,
  tierPriceLabel,
} from './catalog'

function pkg(overrides: Partial<Package> = {}): Package {
  return {
    id: 1,
    name: 'باقة إطلاق مشروع',
    slug: 'foundation-package',
    description: null,
    audience: 'مشروع جديد',
    deliverables: ['هوية بصرية'],
    category: 'GENERAL',
    price: '9000.00',
    discount_amount: '0.00',
    final_price: '9000.00',
    currency: 'SAR',
    pricing_mode: 'FIXED',
    pricing_label: 'سعر ثابت',
    is_chargeable: true,
    duration_days: 30,
    revision_rounds: 2,
    is_featured: false,
    sort_order: 0,
    items: [],
    tiers: [],
    ...overrides,
  }
}

function tier(overrides: Partial<PackageTier> = {}): PackageTier {
  return {
    id: 1,
    name: 'أساسية',
    slug: 'basic',
    description: null,
    price: null,
    currency: 'SAR',
    duration_days: null,
    revision_rounds: null,
    deliverables: [],
    sort_order: 0,
    is_priced: false,
    ...overrides,
  }
}

function service(overrides: Partial<Service> = {}): Service {
  return {
    id: 1,
    name: 'هوية بصرية',
    slug: 'brand-identity',
    summary: null,
    description: null,
    category: 'STRATEGY',
    base_price: '0.00',
    currency: 'SAR',
    pricing_mode: 'QUOTE',
    pricing_label: 'طلب تسعير',
    is_chargeable: false,
    duration_days: null,
    is_featured: false,
    ...overrides,
  }
}

describe('catalog pricing labels', () => {
  it('shows a fixed catalog price as-is', () => {
    expect(packagePriceLabel(pkg())).toContain('SAR')
    expect(packagePriceLabel(pkg())).not.toContain('يبدأ من')
  })

  it('labels a starting-from package without hiding the floor price', () => {
    const label = packagePriceLabel(pkg({ pricing_mode: 'STARTING_FROM' }))

    expect(label).toContain('يبدأ من')
    expect(label).toContain('SAR')
  })

  it('never invents a price for a package the owner has not priced', () => {
    expect(packagePriceLabel(pkg({ pricing_mode: 'QUOTE' }))).toBe(PRICING_MODE_LABELS.QUOTE)
    expect(packagePriceLabel(pkg({ pricing_mode: 'FIXED', final_price: '0.00' }))).toBe(
      PRICING_MODE_LABELS.QUOTE,
    )
    expect(packagePriceLabel(pkg({ pricing_mode: 'QUOTE' }))).not.toMatch(/\d/)
  })

  it('asks for a quote on an unpriced package level', () => {
    expect(tierPriceLabel(tier())).toBe(PRICING_MODE_LABELS.QUOTE)
    expect(tierPriceLabel(tier({ price: '0.00' }))).toBe(PRICING_MODE_LABELS.QUOTE)
    expect(tierPriceLabel(tier({ price: '4500.00', is_priced: true }))).toContain('SAR')
  })

  it('asks for a quote on an unpriced service', () => {
    expect(servicePriceLabel(service())).toBe(PRICING_MODE_LABELS.QUOTE)
    expect(
      servicePriceLabel(service({ pricing_mode: 'FIXED', base_price: '2500.00', is_chargeable: true })),
    ).toContain('SAR')
    expect(servicePriceLabel(service({ pricing_mode: 'STARTING_FROM', base_price: '2500.00' }))).toContain(
      'يبدأ من',
    )
  })
})
