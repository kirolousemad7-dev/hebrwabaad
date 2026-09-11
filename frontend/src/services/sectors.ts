import { apiGet } from './api'
import type { Package, Service } from '../types/api'

export type PublicSector = {
  id: number
  name_ar: string
  name_en?: string | null
  slug: string
  description?: string | null
  needs?: string[] | null
  cover_image?: string | null
  sort_order?: number
  services?: Service[]
  packages?: Package[]
  case_studies?: Array<{
    id: number
    slug?: string
    title: string
    description?: string | null
    image_url?: string
  }>
}

export function getPublicSectors() {
  return apiGet<PublicSector[]>('/api/sectors')
}

export function getPublicSector(slug: string) {
  return apiGet<PublicSector>(`/api/sectors/${encodeURIComponent(slug)}`)
}
