import { apiDelete, apiGet, apiPost, apiPut } from './api'

export const PORTFOLIO_CATEGORIES = ['web', 'branding', 'social', 'video', 'marketing', 'events', 'printing'] as const

export type PortfolioCategory = (typeof PORTFOLIO_CATEGORIES)[number]

export const PORTFOLIO_MEDIA_TYPES = [
  'IMAGE',
  'UPLOADED_VIDEO',
  'EXTERNAL_VIDEO',
  'WEBSITE_LINK',
  'EXTERNAL_PROJECT_LINK',
] as const

export type PortfolioMediaType = (typeof PORTFOLIO_MEDIA_TYPES)[number]

export type PortfolioMediaItem = {
  id?: number
  type: PortfolioMediaType
  title?: string | null
  caption?: string | null
  url?: string | null
  thumbnail_url?: string | null
  display_order?: number
  is_featured?: boolean
  is_public?: boolean
  embed?: { provider: string; embed_url: string; watch_url: string } | null
  provider?: string | null
}

export type PortfolioLinkRef = {
  id: number
  name: string
  slug: string
  summary?: string | null
  pricing_mode?: string
}

export type PortfolioCta = {
  similar_path: string
  consultant_path: string
  build_package_path: string
  quote_path: string | null
  show_quote_cta: boolean
  context: {
    portfolio_id: number
    portfolio_slug: string
    sector_slug?: string | null
    service_slugs: string[]
    package_slug?: string | null
  }
}

export type PortfolioItem = {
  id: number
  slug?: string
  title: string
  brand_name?: string | null
  category: PortfolioCategory
  description: string | null
  short_description?: string | null
  challenge?: string | null
  solution?: string | null
  execution?: string | null
  deliverables?: string[]
  results?: string | null
  tags: string[]
  image_url: string
  cover_url?: string | null
  project_url?: string | null
  video_url?: string | null
  media_type?: PortfolioMediaType | null
  primary_media_type?: PortfolioMediaType | null
  has_video?: boolean
  is_sample: boolean
  is_featured?: boolean
  sort_order: number
  is_published?: boolean
  sectors?: PortfolioLinkRef[]
  services?: PortfolioLinkRef[]
  package?: PortfolioLinkRef | null
  package_id?: number | null
  service_ids?: number[]
  sector_ids?: number[]
  media?: PortfolioMediaItem[]
  cta?: PortfolioCta
  related?: PortfolioItem[]
  updated_at?: string
}

export type Testimonial = {
  id: number
  author_name: string
  author_role: string | null
  quote: string
  sort_order: number
  is_published?: boolean
}

export function getPublicPortfolio(category?: PortfolioCategory) {
  const query = category ? `?category=${encodeURIComponent(category)}` : ''

  return apiGet<PortfolioItem[]>(`/api/portfolio${query}`)
}

export function getPublicPortfolioDetail(slug: string) {
  return apiGet<PortfolioItem>(`/api/portfolio/${encodeURIComponent(slug)}`)
}

export function getPublicTestimonials() {
  return apiGet<Testimonial[]>('/api/testimonials')
}

export function submitContactInquiry(payload: {
  name: string
  email: string
  phone?: string
  message: string
}) {
  return apiPost<{ status: string }>('/api/contact', payload)
}

export function getAdminPortfolio() {
  return apiGet<PortfolioItem[]>('/api/admin/portfolio')
}

export function createAdminPortfolioItem(payload: Partial<PortfolioItem> & Record<string, unknown>) {
  return apiPost<PortfolioItem>('/api/admin/portfolio', payload)
}

export function updateAdminPortfolioItem(id: number, payload: Partial<PortfolioItem> & Record<string, unknown>) {
  return apiPut<PortfolioItem>(`/api/admin/portfolio/${id}`, payload)
}

export function deleteAdminPortfolioItem(id: number) {
  return apiDelete<null>(`/api/admin/portfolio/${id}`)
}

export function getAdminTestimonials() {
  return apiGet<Testimonial[]>('/api/admin/testimonials')
}

export function createAdminTestimonial(payload: Partial<Testimonial>) {
  return apiPost<Testimonial>('/api/admin/testimonials', payload)
}

export function updateAdminTestimonial(id: number, payload: Partial<Testimonial>) {
  return apiPut<Testimonial>(`/api/admin/testimonials/${id}`, payload)
}

export function deleteAdminTestimonial(id: number) {
  return apiDelete<null>(`/api/admin/testimonials/${id}`)
}
