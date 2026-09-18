import { apiDelete, apiGet, apiPost, apiPut } from './api'

export type BlogAuthor = {
  id: number
  name: string
  slug: string
  bio: string | null
  avatar_url: string | null
  email: string | null
  is_active: boolean
  sort_order: number
  posts_count?: number
}

export type BlogCategory = {
  id: number
  name: string
  slug: string
  description: string | null
  is_active?: boolean
  sort_order?: number
  posts_count?: number
}

export type BlogTag = {
  id: number
  name: string
  slug: string
  posts_count?: number
}

export type BlogPost = {
  id: number
  title: string
  slug: string
  excerpt: string | null
  content?: string
  featured_image: string | null
  status: string
  status_label: string
  published_at: string | null
  created_at?: string | null
  updated_at?: string | null
  author: Pick<BlogAuthor, 'id' | 'name' | 'slug' | 'bio' | 'avatar_url'> | null
  category: Pick<BlogCategory, 'id' | 'name' | 'slug'> | null
  tags: BlogTag[]
  author_id?: number | null
  category_id?: number | null
  tag_ids?: number[]
  seo: {
    title: string | null
    meta_description: string | null
    og_image: string | null
    canonical_url: string | null
  }
  seo_title?: string | null
  meta_description?: string | null
  og_image?: string | null
  canonical_url?: string | null
  related?: BlogPost[]
}

export type BlogListData = {
  items: BlogPost[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters?: {
    categories: BlogCategory[]
    tags: BlogTag[]
  }
}

export type BlogCategoryPageData = {
  category: BlogCategory
  items: BlogPost[]
  meta: BlogListData['meta']
}

export type UpsertBlogPostPayload = {
  title: string
  slug?: string | null
  excerpt?: string | null
  content: string
  featured_image?: string | null
  author_id?: number | null
  category_id?: number | null
  tag_ids?: number[]
  status: string
  published_at?: string | null
  seo_title?: string | null
  meta_description?: string | null
  og_image?: string | null
  canonical_url?: string | null
}

function listQuery(filters: Record<string, string | number | undefined>) {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      params.set(key, String(value))
    }
  })
  const query = params.toString()
  return query ? `?${query}` : ''
}

export function getPublicBlog(filters: { page?: number; q?: string; category?: string; tag?: string; per_page?: number } = {}) {
  return apiGet<BlogListData>(`/api/blog${listQuery(filters)}`)
}

export function getPublicBlogPost(slug: string) {
  return apiGet<BlogPost>(`/api/blog/${encodeURIComponent(slug)}`)
}

export function getPublicBlogCategory(slug: string, filters: { page?: number; q?: string } = {}) {
  return apiGet<BlogCategoryPageData>(`/api/blog/category/${encodeURIComponent(slug)}${listQuery(filters)}`)
}

export function getAdminBlogPosts(filters: { page?: number; q?: string; status?: string } = {}) {
  return apiGet<BlogListData>(`/api/admin/blog/posts${listQuery(filters)}`)
}

export function getAdminBlogPost(id: number) {
  return apiGet<BlogPost>(`/api/admin/blog/posts/${id}`)
}

export function createAdminBlogPost(payload: UpsertBlogPostPayload) {
  return apiPost<BlogPost>('/api/admin/blog/posts', payload)
}

export function updateAdminBlogPost(id: number, payload: UpsertBlogPostPayload) {
  return apiPut<BlogPost>(`/api/admin/blog/posts/${id}`, payload)
}

export function deleteAdminBlogPost(id: number) {
  return apiDelete<null>(`/api/admin/blog/posts/${id}`)
}

export function getAdminBlogCategories() {
  return apiGet<BlogCategory[]>('/api/admin/blog/categories')
}

export function createAdminBlogCategory(payload: Partial<BlogCategory> & { name: string }) {
  return apiPost<BlogCategory>('/api/admin/blog/categories', payload)
}

export function updateAdminBlogCategory(id: number, payload: Partial<BlogCategory> & { name: string }) {
  return apiPut<BlogCategory>(`/api/admin/blog/categories/${id}`, payload)
}

export function deleteAdminBlogCategory(id: number) {
  return apiDelete<null>(`/api/admin/blog/categories/${id}`)
}

export function getAdminBlogTags() {
  return apiGet<BlogTag[]>('/api/admin/blog/tags')
}

export function createAdminBlogTag(payload: { name: string; slug?: string }) {
  return apiPost<BlogTag>('/api/admin/blog/tags', payload)
}

export function updateAdminBlogTag(id: number, payload: { name: string; slug?: string }) {
  return apiPut<BlogTag>(`/api/admin/blog/tags/${id}`, payload)
}

export function deleteAdminBlogTag(id: number) {
  return apiDelete<null>(`/api/admin/blog/tags/${id}`)
}

export function getAdminBlogAuthors() {
  return apiGet<BlogAuthor[]>('/api/admin/blog/authors')
}

export function createAdminBlogAuthor(payload: Partial<BlogAuthor> & { name: string }) {
  return apiPost<BlogAuthor>('/api/admin/blog/authors', payload)
}

export function updateAdminBlogAuthor(id: number, payload: Partial<BlogAuthor> & { name: string }) {
  return apiPut<BlogAuthor>(`/api/admin/blog/authors/${id}`, payload)
}

export function deleteAdminBlogAuthor(id: number) {
  return apiDelete<null>(`/api/admin/blog/authors/${id}`)
}
