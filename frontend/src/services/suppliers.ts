import { apiGet } from './api'
import type { Supplier } from '../types/api'

export type SupplierListQuery = {
  q?: string
  specialty?: string
  service?: string
  featured?: boolean
}

export function getPublicSuppliers(query: SupplierListQuery = {}) {
  const params = new URLSearchParams()

  if (query.q) {
    params.set('q', query.q)
  }
  if (query.specialty) {
    params.set('specialty', query.specialty)
  }
  if (query.service) {
    params.set('service', query.service)
  }
  if (query.featured) {
    params.set('featured', '1')
  }

  const suffix = params.size > 0 ? `?${params.toString()}` : ''

  return apiGet<Supplier[]>(`/api/suppliers${suffix}`)
}

export function getPublicSupplier(slug: string) {
  return apiGet<Supplier>(`/api/suppliers/${encodeURIComponent(slug)}`)
}
