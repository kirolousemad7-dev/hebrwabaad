import { apiDelete, apiGet, apiPost, apiPut } from './api'
import type { ContentStatus, Paginated } from './work'

export type SupplierContentRow = {
  id: number
  type: 'profile' | 'profile_version' | 'portfolio' | 'product'
  title: string
  status: ContentStatus
  updated_at: string | null
  review_notes: string | null
  supplier_id?: number
  supplier_name?: string
  submitted_at?: string | null
}

export type AdminSupplier = {
  id: number
  user_id: number | null
  name: string
  slug: string
  logo: string
  cover_image: string | null
  short_description: string
  description: string | null
  specialties: string[]
  services: string[]
  location: string
  address: string | null
  email: string | null
  phone: string | null
  website: string | null
  category: string | null
  brand_colors: string[] | null
  brand_description: string | null
  years_experience: number | null
  min_order_info: string | null
  is_active: boolean
  is_featured: boolean
  is_published: boolean
  profile_status: ContentStatus
  review_notes: string | null
  seo_title: string | null
  seo_description: string | null
  og_title: string | null
  og_description: string | null
  og_image: string | null
  canonical_url: string | null
  robots: string | null
  updated_at: string | null
  content_count: number
  products_count: number
  pending_profile?: { id: number; status: ContentStatus; payload: Record<string, unknown>; review_notes: string | null }
}

export function getSupplierProfile() {
  return apiGet<AdminSupplier>('/api/supplier/profile')
}

export function updateSupplierProfile(payload: Partial<AdminSupplier>) {
  return apiPut<AdminSupplier>('/api/supplier/profile', payload)
}

export function submitSupplierProfile() {
  return apiPost<AdminSupplier>('/api/supplier/profile/submit')
}

export function getSupplierContent() {
  return apiGet<{ items: SupplierContentRow[]; counts: { drafts: number; under_review: number; published: number; needs_changes: number } }>(
    '/api/supplier/content',
  )
}

export function createSupplierPortfolio(payload: Record<string, unknown>) {
  return apiPost<{ id: number; status: string }>('/api/supplier/content/portfolio', payload)
}

export function submitSupplierPortfolio(id: number) {
  return apiPost<{ id: number; status: string }>(`/api/supplier/content/portfolio/${id}/submit`)
}

export function resubmitSupplierPortfolio(id: number) {
  return apiPost<{ id: number; status: string }>(`/api/supplier/content/portfolio/${id}/resubmit`)
}

export function createSupplierProduct(payload: Record<string, unknown>) {
  return apiPost<{ id: number; status: string; slug: string }>('/api/supplier/content/products', payload)
}

export function submitSupplierProduct(id: number) {
  return apiPost<{ id: number; status: string }>(`/api/supplier/content/products/${id}/submit`)
}

export function resubmitSupplierProduct(id: number) {
  return apiPost<{ id: number; status: string }>(`/api/supplier/content/products/${id}/resubmit`)
}

export function getAdminSuppliers(query: Record<string, string | undefined> = {}) {
  const params = new URLSearchParams()
  Object.entries(query).forEach(([key, value]) => {
    if (value) params.set(key, value)
  })
  const suffix = params.size ? `?${params}` : ''
  return apiGet<Paginated<AdminSupplier>>(`/api/admin/suppliers${suffix}`)
}

export function createAdminSupplier(payload: Record<string, unknown>) {
  return apiPost<AdminSupplier>('/api/admin/suppliers', payload)
}

export function getAdminSupplier(id: number) {
  return apiGet<{
    supplier: AdminSupplier
    portfolio: Array<Record<string, unknown>>
    products: Array<Record<string, unknown>>
    reviews: Array<Record<string, unknown>>
  }>(`/api/admin/suppliers/${id}`)
}

export function updateAdminSupplier(id: number, payload: Record<string, unknown>) {
  return apiPut<AdminSupplier>(`/api/admin/suppliers/${id}`, payload)
}

export function deleteAdminSupplier(id: number) {
  return apiDelete<null>(`/api/admin/suppliers/${id}`)
}

export function activateAdminSupplier(id: number) {
  return apiPost<AdminSupplier>(`/api/admin/suppliers/${id}/activate`)
}

export function deactivateAdminSupplier(id: number) {
  return apiPost<AdminSupplier>(`/api/admin/suppliers/${id}/deactivate`)
}

export function publishAdminSupplier(id: number) {
  return apiPost<AdminSupplier>(`/api/admin/suppliers/${id}/publish`)
}

export function unpublishAdminSupplier(id: number) {
  return apiPost<AdminSupplier>(`/api/admin/suppliers/${id}/unpublish`)
}

export function getSupplierReviews() {
  return apiGet<Paginated<SupplierContentRow>>('/api/admin/supplier-reviews')
}

export function approveSupplierReview(type: string, id: number) {
  return apiPost<{ status: string }>(`/api/admin/supplier-reviews/${type}/${id}/approve-publish`)
}

export function rejectSupplierReview(type: string, id: number, notes: string) {
  return apiPost<{ status: string }>(`/api/admin/supplier-reviews/${type}/${id}/reject`, { notes })
}

export function requestSupplierReviewChanges(type: string, id: number, notes: string) {
  return apiPost<{ status: string }>(`/api/admin/supplier-reviews/${type}/${id}/request-changes`, { notes })
}
