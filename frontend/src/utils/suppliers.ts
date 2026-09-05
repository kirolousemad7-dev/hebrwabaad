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

export function filterSuppliers(
  suppliers: Supplier[],
  filters: { specialty: string | null; service: string | null; q: string },
): Supplier[] {
  const query = filters.q.trim()

  return suppliers.filter((supplier) => {
    if (filters.specialty && !supplier.specialties.includes(filters.specialty)) {
      return false
    }

    if (filters.service && !supplier.services.includes(filters.service)) {
      return false
    }

    if (query === '') {
      return true
    }

    const haystack = [supplier.name, supplier.short_description, supplier.location, ...supplier.specialties, ...supplier.services]
      .join(' ')
      .toLowerCase()

    return haystack.includes(query.toLowerCase())
  })
}
