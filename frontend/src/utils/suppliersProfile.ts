import type { Supplier } from '../types/api'

export function supplierPublicSections(supplier: Supplier) {
  return [
    {
      id: 'about',
      title: 'عن المورد',
      content: supplier.description || supplier.short_description || '',
    },
    {
      id: 'services',
      title: 'الخدمات',
      items: supplier.services ?? [],
    },
    {
      id: 'specialties',
      title: 'التخصصات',
      items: supplier.specialties ?? [],
    },
    {
      id: 'areas',
      title: 'مناطق الخدمة',
      items: supplier.service_areas ?? [],
    },
    {
      id: 'certs',
      title: 'الشهادات',
      items: supplier.certifications ?? [],
    },
    {
      id: 'availability',
      title: 'التوفر والتسليم',
      content: [supplier.availability, supplier.delivery_time].filter(Boolean).join(' · '),
    },
  ]
}

export function isSupplierPublicVisibility(visibility: string | null | undefined): boolean {
  return visibility === 'PUBLIC'
}
