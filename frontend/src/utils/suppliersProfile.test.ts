import { describe, expect, it } from 'vitest'
import { isSupplierPublicVisibility, supplierPublicSections } from './suppliersProfile'
import type { Supplier } from '../types/api'

describe('suppliersProfile', () => {
  it('marks only PUBLIC visibility as public', () => {
    expect(isSupplierPublicVisibility('PUBLIC')).toBe(true)
    expect(isSupplierPublicVisibility('PRIVATE')).toBe(false)
    expect(isSupplierPublicVisibility('INTERNAL')).toBe(false)
  })

  it('builds reusable profile sections from supplier payload', () => {
    const supplier = {
      id: 1,
      name: 'مورد',
      slug: 'm',
      logo: '/logo.png',
      short_description: 'قصير',
      description: 'وصف كامل',
      specialties: ['طباعة'],
      services: ['كروت'],
      location: 'الرياض',
      service_areas: ['جدة'],
      certifications: ['ISO'],
      availability: 'AVAILABLE',
      delivery_time: '3 أيام',
      featured: false,
      portfolio_count: 0,
      portfolio_preview: [],
    } as Supplier

    const sections = supplierPublicSections(supplier)
    expect(sections.find((s) => s.id === 'about')?.content).toContain('وصف')
    expect(sections.find((s) => s.id === 'services')?.items).toEqual(['كروت'])
    expect(sections.find((s) => s.id === 'areas')?.items).toEqual(['جدة'])
    expect(sections.find((s) => s.id === 'availability')?.content).toContain('3 أيام')
  })
})
