import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './api'
import type { Package, PackageCategory, PricingMode, Service, ServiceCategory } from '../types/api'

export type ServiceInput = {
  name: string
  slug?: string | null
  summary?: string | null
  short_description?: string | null
  description?: string | null
  scope?: string | null
  deliverables?: string[] | null
  features?: string[] | null
  process_steps?: Array<{ title: string; description?: string | null }> | null
  faq?: Array<{ question: string; answer: string }> | null
  tags?: string[] | null
  gallery?: string[] | null
  hero_image?: string | null
  category: ServiceCategory
  subcategory?: string | null
  base_price: number
  currency?: string
  pricing_mode?: PricingMode
  duration_days?: number | null
  revision_rounds?: number | null
  is_active?: boolean
  is_featured?: boolean
  is_public?: boolean
  sort_order?: number
  department_id?: number | null
  task_title_template?: string | null
  default_task_priority?: string | null
  requires_review?: boolean
  requires_customer_approval?: boolean
  checklist_template?: string[] | null
  seo_title?: string | null
  seo_description?: string | null
  og_title?: string | null
  og_description?: string | null
  og_image?: string | null
  canonical_url?: string | null
  robots?: string | null
  addon_ids?: number[]
  sector_ids?: number[]
  portfolio_item_ids?: number[]
  supplier_ids?: number[]
  product_ids?: number[]
  project_ids?: number[]
  quotation_ids?: number[]
}

export type PackageItemInput = {
  service_id: number
  quantity: number
  sort_order: number
  notes?: string | null
}

export type PackageTierInput = {
  name: string
  slug: string
  description?: string | null
  price?: number | null
  currency?: string
  duration_days?: number | null
  revision_rounds?: number | null
  deliverables?: string[] | null
  is_active?: boolean
  sort_order?: number
}

export type PackageInput = {
  name: string
  slug?: string | null
  description?: string | null
  audience?: string | null
  deliverables?: string[] | null
  category: PackageCategory
  price: number
  discount_amount: number
  currency?: string
  pricing_mode?: PricingMode
  duration_days?: number | null
  revision_rounds?: number | null
  sort_order?: number
  is_active?: boolean
  is_featured?: boolean
  items: PackageItemInput[]
  tiers?: PackageTierInput[]
}

export function getPublicServices(category?: ServiceCategory) {
  const query = category ? `?category=${encodeURIComponent(category)}` : ''

  return apiGet<Service[]>(`/api/services${query}`)
}

export function getPublicService(slugOrId: string | number) {
  return apiGet<Service>(`/api/services/${slugOrId}`)
}

export function getPublicPackages(category?: PackageCategory) {
  const query = category ? `?category=${encodeURIComponent(category)}` : ''

  return apiGet<Package[]>(`/api/packages${query}`)
}

export function getManagedServices() {
  return apiGet<Service[]>('/api/admin/services')
}

export function createService(input: ServiceInput) {
  return apiPost<Service>('/api/admin/services', input)
}

export function updateService(id: number, input: Partial<ServiceInput>) {
  return apiPut<Service>(`/api/admin/services/${id}`, input)
}

export function setServiceActive(id: number, isActive: boolean) {
  return apiPatch<Service>(`/api/admin/services/${id}`, { is_active: isActive })
}

export function deleteService(id: number) {
  return apiDelete<null>(`/api/admin/services/${id}`)
}

export function getManagedPackages() {
  return apiGet<Package[]>('/api/admin/packages')
}

export function createPackage(input: PackageInput) {
  return apiPost<Package>('/api/admin/packages', input)
}

export function updatePackage(id: number, input: Partial<PackageInput>) {
  return apiPut<Package>(`/api/admin/packages/${id}`, input)
}

export function setPackageActive(id: number, isActive: boolean) {
  return apiPatch<Package>(`/api/admin/packages/${id}`, { is_active: isActive })
}

export function deletePackage(id: number) {
  return apiDelete<null>(`/api/admin/packages/${id}`)
}
