import { apiGet, apiPut } from './api'
import type { SeoPage } from '../utils/seo'

export function getPublicSeo(page: string) {
  return apiGet<SeoPage>(`/api/seo/${encodeURIComponent(page)}`)
}

export function getAdminSeoPages() {
  return apiGet<SeoPage[]>('/api/admin/seo')
}

export function updateAdminSeoPage(page: string, payload: Partial<SeoPage>) {
  return apiPut<SeoPage>(`/api/admin/seo/${encodeURIComponent(page)}`, payload)
}
