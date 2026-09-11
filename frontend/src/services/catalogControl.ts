import { apiGet } from './api'
import type { PublicSector } from './sectors'

export type CatalogReadinessRow = {
  type: string
  id: number
  slug: string
  name?: string
  name_ar?: string
  pricing_mode?: string
  is_price_complete: boolean
  is_purchasable: boolean
  missing: string[]
}

export type CatalogReadinessReport = {
  items: CatalogReadinessRow[]
  summary: {
    total: number
    purchasable: number
    price_incomplete: number
  }
}

export type ManagedAddon = {
  id: number
  name: string
  slug: string
  pricing_mode: string
  is_active: boolean
  is_public: boolean
  capacity_available?: boolean
}

export type ManagedRecommendationGoal = {
  id: number
  slug: string
  name_ar: string
  is_active: boolean
}

export function getCatalogReadiness() {
  return apiGet<CatalogReadinessReport>('/api/admin/catalog-readiness')
}

export function getManagedSectors() {
  return apiGet<PublicSector[]>('/api/admin/sectors')
}

export function getManagedAddons() {
  return apiGet<ManagedAddon[]>('/api/admin/addons')
}

export function getManagedRecommendationGoals() {
  return apiGet<ManagedRecommendationGoal[]>('/api/admin/recommendation-goals')
}
