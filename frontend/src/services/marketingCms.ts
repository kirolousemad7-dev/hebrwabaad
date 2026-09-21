import { apiDelete, apiGet, apiPostForm, apiPut } from './api'

export type MarketingSectionType =
  | 'hero'
  | 'services'
  | 'packages'
  | 'about'
  | 'why-us'
  | 'process'
  | 'portfolio'
  | 'suppliers'
  | 'build-package'
  | 'final-cta'
  | 'contact'
  | 'custom'

export type MarketingMediaUsage = {
  section_key: string
  content_key: string
  content_id: number
}

export type MarketingMedia = {
  id: number
  original_name: string
  mime_type: string
  extension: string | null
  size: number
  url: string | null
  alt_text: string
  title: string
  width: number | null
  height: number | null
  is_active: boolean
  local_public_path: string | null
  source_key: string | null
  is_registry_only: boolean
  usage_count: number
  usages: MarketingMediaUsage[]
  created_at: string | null
  updated_at: string | null
}

export type MarketingContentMedia = {
  id?: number
  url: string | null
  alt: string
  title: string
  width: number | null
  height: number | null
  original_name?: string
  mime_type?: string
  size?: number
  is_active?: boolean
  local_public_path?: string | null
  source_key?: string | null
  is_registry_only?: boolean
}

export type MarketingContent = {
  id: number
  key: string
  content_key: string
  text: string | null
  value_text: string | null
  html: string | null
  value_html: string | null
  media_id: number | null
  media: MarketingContentMedia | null
  sort_order: number
  is_enabled: boolean
  metadata: Record<string, unknown> | null
}

export type MarketingSection = {
  id: number
  key: string
  type: MarketingSectionType | string
  type_label: string | null
  admin_title: string
  is_enabled: boolean
  sort_order: number
  config: Record<string, unknown> | null
  contents: MarketingContent[]
  created_at: string | null
  updated_at: string | null
}

export type MarketingMediaListResponse = {
  items: MarketingMedia[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type UpdateMarketingSectionPayload = {
  admin_title?: string
  type?: string
  is_enabled?: boolean
  sort_order?: number
  config?: Record<string, unknown> | null
  contents?: Array<{
    content_key: string
    value_text?: string | null
    value_html?: string | null
    media_id?: number | null
    sort_order?: number
    is_enabled?: boolean
    metadata?: Record<string, unknown> | null
  }>
}

export type MarketingMediaReplaceResponse = {
  new: MarketingMedia
  previous: MarketingMedia
  note: string
}

export function listOwnerMarketingSections() {
  return apiGet<MarketingSection[]>('/api/owner/marketing/sections')
}

export function getOwnerMarketingSection(key: string) {
  return apiGet<MarketingSection>(`/api/owner/marketing/sections/${encodeURIComponent(key)}`)
}

export function updateOwnerMarketingSection(key: string, payload: UpdateMarketingSectionPayload) {
  return apiPut<MarketingSection>(`/api/owner/marketing/sections/${encodeURIComponent(key)}`, payload)
}

export function listOwnerMarketingMedia(params?: { page?: number; per_page?: number; is_active?: boolean }) {
  const query = new URLSearchParams()
  if (params?.page) query.set('page', String(params.page))
  if (params?.per_page) query.set('per_page', String(params.per_page))
  if (params?.is_active !== undefined) query.set('is_active', params.is_active ? '1' : '0')
  const suffix = query.toString() ? `?${query.toString()}` : ''
  return apiGet<MarketingMediaListResponse>(`/api/owner/marketing/media${suffix}`)
}

export function listOwnerMarketingMediaOrphans() {
  return apiGet<MarketingMedia[]>('/api/owner/marketing/media/orphans')
}

export function getOwnerMarketingMedia(id: number) {
  return apiGet<MarketingMedia>(`/api/owner/marketing/media/${id}`)
}

export function uploadOwnerMarketingMedia(form: FormData) {
  return apiPostForm<MarketingMedia>('/api/owner/marketing/media', form)
}

export function updateOwnerMarketingMedia(
  id: number,
  payload: { alt_text?: string | null; title?: string | null; is_active?: boolean },
) {
  return apiPut<MarketingMedia>(`/api/owner/marketing/media/${id}`, payload)
}

export function replaceOwnerMarketingMedia(id: number, form: FormData) {
  return apiPostForm<MarketingMediaReplaceResponse>(`/api/owner/marketing/media/${id}/replace`, form)
}

export function deleteOwnerMarketingMedia(id: number) {
  return apiDelete<null>(`/api/owner/marketing/media/${id}`)
}
