import { apiGet, apiPost, apiPostForm, apiPut, publicFetch } from './api'
import type { PlatformSettingsManage, PlatformSettingsPublic } from '../types/platformSettings'

export async function fetchPublicPlatformSettings(): Promise<PlatformSettingsPublic> {
  return publicFetch<PlatformSettingsPublic>('/api/platform-settings')
}

export async function getPlatformSettings(): Promise<{ data: PlatformSettingsManage }> {
  return apiGet<PlatformSettingsManage>('/api/admin/platform-settings')
}

export async function updatePlatformSettings(
  payload: Partial<PlatformSettingsManage>,
): Promise<{ data: PlatformSettingsManage }> {
  return apiPut<PlatformSettingsManage>('/api/admin/platform-settings', payload)
}

export async function uploadPlatformBrandAsset(
  slot: string,
  file: File,
): Promise<{ data: PlatformSettingsManage }> {
  const body = new FormData()
  body.append('slot', slot)
  body.append('file', file)
  return apiPostForm<PlatformSettingsManage>('/api/admin/platform-settings/brand-assets', body)
}

export type PrintingCatalogAdmin = {
  categories: Array<{
    id: number
    slug: string
    name_ar: string
    name_en: string | null
    is_active: boolean
    sort_order: number
  }>
  products: Array<{
    id: number
    slug: string
    name_ar: string
    name_en: string | null
    short_description: string | null
    description: string | null
    image_url: string | null
    pricing_mode: string
    starting_price: string | number | null
    currency: string
    is_active: boolean
    is_public: boolean
    is_featured: boolean
    allows_design_and_print: boolean
    sort_order: number
    category_id: number | null
    option_ids: number[]
  }>
  options: Array<{
    id: number
    type: string
    slug: string
    name_ar: string
    name_en: string | null
    is_active: boolean
    sort_order: number
  }>
}

export async function getPrintingCatalogAdmin(): Promise<{ data: PrintingCatalogAdmin }> {
  return apiGet<PrintingCatalogAdmin>('/api/admin/printing-catalog')
}

export async function savePrintingProduct(
  payload: Record<string, unknown>,
  id?: number,
): Promise<{ data: PrintingCatalogAdmin['products'][number] }> {
  if (id) {
    return apiPut(`/api/admin/printing-catalog/products/${id}`, payload)
  }
  return apiPost(`/api/admin/printing-catalog/products`, payload)
}

export async function savePrintingCategory(
  payload: Record<string, unknown>,
  id?: number,
): Promise<{ data: PrintingCatalogAdmin['categories'][number] }> {
  if (id) {
    return apiPut(`/api/admin/printing-catalog/categories/${id}`, payload)
  }
  return apiPost(`/api/admin/printing-catalog/categories`, payload)
}

export async function savePrintingOption(
  payload: Record<string, unknown>,
  id?: number,
): Promise<{ data: PrintingCatalogAdmin['options'][number] }> {
  if (id) {
    return apiPut(`/api/admin/printing-catalog/options/${id}`, payload)
  }
  return apiPost(`/api/admin/printing-catalog/options`, payload)
}

export async function getPublicPrintingCatalog(): Promise<{
  products: Array<Record<string, unknown>>
}> {
  return publicFetch<{ products: Array<Record<string, unknown>> }>('/api/printing-catalog')
}

export async function getEventTypesAdmin(): Promise<{ data: Array<Record<string, unknown>> }> {
  return apiGet('/api/admin/event-types')
}

export async function saveEventType(
  payload: Record<string, unknown>,
  id?: number,
): Promise<{ data: Record<string, unknown> }> {
  if (id) {
    return apiPut(`/api/admin/event-types/${id}`, payload)
  }
  return apiPost('/api/admin/event-types', payload)
}
