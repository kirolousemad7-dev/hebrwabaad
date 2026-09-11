import type { Supplier } from '../types/api'

export const SUPPLIER_SPECIALTY_OPTIONS = [
  'الطباعة التجارية',
  'التغليف',
  'العلب',
  'الأكياس الورقية',
  'الاستيكرات',
  'الطباعة الرقمية',
  'الطباعة الفاخرة',
  'المواد الدعائية',
  'الهدايا المؤسسية',
] as const

export const SUPPLIER_SERVICE_OPTIONS = [
  'كروت شخصية',
  'فلايرز',
  'بوسترات',
  'استيكرات',
  'علب',
  'أكياس',
  'تغليف',
  'منتجات دعائية',
] as const

export function supplierPath(slug: string): string {
  return `/suppliers/${encodeURIComponent(slug)}`
}

export function uniqueSupplierValues(suppliers: Supplier[], key: 'specialties' | 'services'): string[] {
  return [...new Set(suppliers.flatMap((supplier) => supplier[key]))]
}

export function uniqueSupplierLocations(suppliers: Supplier[]): string[] {
  return [...new Set(suppliers.map((supplier) => supplier.location).filter(Boolean))]
}

export function uniqueSupplierCategories(suppliers: Supplier[]): string[] {
  return [...new Set(suppliers.map((supplier) => supplier.category).filter((value): value is string => Boolean(value)))]
}

export function filterSuppliers(
  suppliers: Supplier[],
  filters: {
    specialty: string | null
    service: string | null
    q: string
    location?: string | null
    category?: string | null
    featured?: boolean
  },
): Supplier[] {
  const query = filters.q.trim()

  return suppliers.filter((supplier) => {
    if (filters.specialty && !supplier.specialties.includes(filters.specialty)) {
      return false
    }

    if (filters.service && !supplier.services.includes(filters.service)) {
      return false
    }

    if (filters.location && supplier.location !== filters.location) {
      return false
    }

    if (filters.category && supplier.category !== filters.category) {
      return false
    }

    if (filters.featured && !supplier.featured) {
      return false
    }

    if (query === '') {
      return true
    }

    const haystack = [supplier.name, supplier.short_description, supplier.location, supplier.category ?? '', ...supplier.specialties, ...supplier.services]
      .join(' ')
      .toLowerCase()

    return haystack.includes(query.toLowerCase())
  })
}
