import { apiDelete, apiGet, apiPost, apiPostForm, apiPut } from './api'
import type { PortfolioCategory } from './marketing'

export const CONTENT_STATUSES = [
  'DRAFT',
  'SUBMITTED',
  'UNDER_REVIEW',
  'APPROVED',
  'REJECTED',
  'CHANGES_REQUESTED',
  'PUBLISHED',
  'ARCHIVED',
] as const

export type ContentStatus = (typeof CONTENT_STATUSES)[number]

export type WorkSubmission = {
  id: number
  title: string
  description: string | null
  category: PortfolioCategory
  service_id: number | null
  cover_media_id: string | null
  cover_url: string | null
  gallery_media_ids: string[]
  tags: string[]
  tools: string[]
  project_url: string | null
  video_url: string | null
  client_label: string | null
  employee_notes: string | null
  status: ContentStatus
  review_notes: string | null
  reviewed_at: string | null
  published_at: string | null
  created_at: string | null
  updated_at: string | null
  employee?: { id: number; name: string; role: string }
}

export type Paginated<T> = {
  items: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type WorkList = Paginated<WorkSubmission> & {
  counts: { drafts: number; under_review: number; published: number; needs_changes: number }
}

export const CONTENT_STATUS_LABELS: Record<ContentStatus, string> = {
  DRAFT: 'مسودة',
  SUBMITTED: 'مُرسل',
  UNDER_REVIEW: 'قيد المراجعة',
  APPROVED: 'معتمد',
  REJECTED: 'مرفوض',
  CHANGES_REQUESTED: 'يحتاج تعديلات',
  PUBLISHED: 'منشور',
  ARCHIVED: 'مؤرشف',
}

export function getEmployeeWork(status?: string) {
  const query = status ? `?status=${encodeURIComponent(status)}` : ''
  return apiGet<WorkList>(`/api/employee/work${query}`)
}

export function createEmployeeWork(payload: Partial<WorkSubmission>) {
  return apiPost<WorkSubmission>('/api/employee/work', payload)
}

export function updateEmployeeWork(id: number, payload: Partial<WorkSubmission>) {
  return apiPut<WorkSubmission>(`/api/employee/work/${id}`, payload)
}

export function submitEmployeeWork(id: number) {
  return apiPost<WorkSubmission>(`/api/employee/work/${id}/submit`)
}

export function resubmitEmployeeWork(id: number) {
  return apiPost<WorkSubmission>(`/api/employee/work/${id}/resubmit`)
}

export function deleteEmployeeWork(id: number) {
  return apiDelete<null>(`/api/employee/work/${id}`)
}

export function getWorkReviews(query: Record<string, string | undefined> = {}) {
  const params = new URLSearchParams()
  Object.entries(query).forEach(([key, value]) => {
    if (value) params.set(key, value)
  })
  const suffix = params.size ? `?${params}` : ''
  return apiGet<Paginated<WorkSubmission>>(`/api/admin/work-reviews${suffix}`)
}

export function getWorkReview(id: number) {
  return apiGet<WorkSubmission>(`/api/admin/work-reviews/${id}`)
}

export function approvePublishWork(id: number) {
  return apiPost<WorkSubmission>(`/api/admin/work-reviews/${id}/approve-publish`)
}

export function rejectWork(id: number, notes: string) {
  return apiPost<WorkSubmission>(`/api/admin/work-reviews/${id}/reject`, { notes })
}

export function requestWorkChanges(id: number, notes: string) {
  return apiPost<WorkSubmission>(`/api/admin/work-reviews/${id}/request-changes`, { notes })
}

export function unpublishWork(id: number) {
  return apiPost<WorkSubmission>(`/api/admin/work-reviews/${id}/unpublish`)
}

export function archiveWork(id: number) {
  return apiPost<WorkSubmission>(`/api/admin/work-reviews/${id}/archive`)
}

export function uploadContentMedia(file: File, collection = 'gallery') {
  const body = new FormData()
  body.append('file', file)
  body.append('collection', collection)
  return apiPostForm<{ id: string; url: string; thumb_url: string }>('/api/content-media', body)
}
