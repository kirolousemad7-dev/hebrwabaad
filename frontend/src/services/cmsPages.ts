import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './api'

export type CmsPageType =
  | 'GENERAL'
  | 'ABOUT'
  | 'CONTACT'
  | 'POLICY'
  | 'TERMS'
  | 'WARRANTY'
  | 'SHIPPING'
  | 'RETURNS'
  | 'SERVICES_POLICY'

export type CmsPage = {
  id: number
  title: string
  slug: string
  content: string
  page_type: CmsPageType | string
  page_type_label?: string
  meta_title: string | null
  meta_description: string | null
  meta_keywords: string | null
  og_title: string | null
  og_description: string | null
  og_image: string | null
  /** Owner-only fields — omitted on public page responses. */
  is_published?: boolean
  show_in_footer?: boolean
  footer_group?: string | null
  footer_order?: number
  path: string
  /** Core seeded pages that cannot be deleted. */
  is_system?: boolean
  updated_at: string | null
  created_at?: string | null
}

export type CmsFooterPage = {
  id: number
  title: string
  slug: string
  page_type: CmsPageType | string
  path: string
  show_in_footer: boolean
  footer_group: string | null
  footer_order: number
  updated_at: string | null
}

export type UpsertCmsPagePayload = {
  title: string
  slug?: string | null
  content: string
  page_type: string
  meta_title?: string | null
  meta_description?: string | null
  meta_keywords?: string | null
  og_title?: string | null
  og_description?: string | null
  og_image?: string | null
  is_published?: boolean
  show_in_footer?: boolean
  footer_group?: string | null
  footer_order?: number | null
}

export type UpdateCmsPageFooterPayload = {
  show_in_footer: boolean
  footer_group?: string | null
  footer_order?: number | null
}

export const CMS_PAGE_TYPE_OPTIONS: Array<{ value: CmsPageType; label: string }> = [
  { value: 'GENERAL', label: 'عامة' },
  { value: 'ABOUT', label: 'من نحن' },
  { value: 'CONTACT', label: 'تواصل' },
  { value: 'POLICY', label: 'سياسة' },
  { value: 'TERMS', label: 'شروط' },
  { value: 'WARRANTY', label: 'ضمان' },
  { value: 'SHIPPING', label: 'شحن' },
  { value: 'RETURNS', label: 'استرجاع' },
  { value: 'SERVICES_POLICY', label: 'سياسات الخدمات' },
]

export function getPublicCmsPage(slug: string) {
  return apiGet<CmsPage>(`/api/public/pages/${encodeURIComponent(slug)}`)
}

export function listPublicCmsFooterPages() {
  return apiGet<CmsFooterPage[]>('/api/public/pages?footer=1')
}

export function listOwnerCmsPages() {
  return apiGet<CmsPage[]>('/api/owner/pages')
}

export function getOwnerCmsPage(id: number) {
  return apiGet<CmsPage>(`/api/owner/pages/${id}`)
}

export function createOwnerCmsPage(payload: UpsertCmsPagePayload) {
  return apiPost<CmsPage>('/api/owner/pages', payload)
}

export function updateOwnerCmsPage(id: number, payload: UpsertCmsPagePayload) {
  return apiPut<CmsPage>(`/api/owner/pages/${id}`, payload)
}

export function deleteOwnerCmsPage(id: number) {
  return apiDelete<null>(`/api/owner/pages/${id}`)
}

export function publishOwnerCmsPage(id: number, isPublished: boolean) {
  return apiPatch<CmsPage>(`/api/owner/pages/${id}/publish`, { is_published: isPublished })
}

export function updateOwnerCmsPageFooter(id: number, payload: UpdateCmsPageFooterPayload) {
  return apiPatch<CmsPage>(`/api/owner/pages/${id}/footer`, payload)
}
