import { normalizeApiBaseUrl } from '../utils/apiBaseUrl'
import { apiGet, apiPatch, apiPostForm, getStoredToken } from './api'

export type NeedsDiscoveryQuickReply = {
  id: string
  label: string
  value: string
  service_id?: number
  category?: string
}

export type NeedsDiscoveryStep = {
  id: string
  prompt: string
  type: 'choice' | 'contact' | string
  quick_replies?: NeedsDiscoveryQuickReply[]
  fields?: string[]
}

export type NeedsDiscoveryStepsPayload = {
  title: string
  steps: NeedsDiscoveryStep[]
}

export type NeedsDiscoveryAnswer = {
  label?: string
  value?: string
  id?: string
  service_id?: number
  category?: string
  name?: string
  phone?: string
  email?: string
  company?: string
}

export type NeedsDiscoverySubmitResult = {
  status: string
  reference: string
  updated_lead: boolean
  summary: string
  recommended_services: Array<{ id: number; name: string; slug: string }>
}

export type RequirementAttachment = {
  index: number
  original_name: string
  mime_type?: string | null
  size?: number | null
}

export type RequirementItem = {
  id: number
  reference: string
  name: string
  phone: string | null
  email: string | null
  company: string | null
  service: string | null
  category: string | null
  budget: string | null
  deadline: string | null
  description: string | null
  attachments: RequirementAttachment[]
  source: string
  answers: Record<string, NeedsDiscoveryAnswer | string>
  summary: string | null
  recommended_services: Array<{ id: number; name: string; slug: string }>
  status: string
  status_label: string
  notes: string | null
  qualified: boolean
  qualified_at: string | null
  created_at: string | null
  customer: {
    name: string
    phone: string | null
    email: string | null
    company: string | null
  }
  assigned_team_member: { id: number; name: string; email: string } | null
  crm_lead: { id: number; reference: string; status: string } | null
  task: { id: number; title: string; status: string } | null
  catalog_service: { id: number; name: string; slug: string } | null
}

export type RequirementListData = {
  items: RequirementItem[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

const API_BASE_URL = (() => {
  const configured = import.meta.env.VITE_API_URL as string | undefined
  if (configured) {
    return normalizeApiBaseUrl(configured)
  }
  return import.meta.env.DEV ? 'http://127.0.0.1:8000' : ''
})()

export function getNeedsDiscoverySteps() {
  return apiGet<NeedsDiscoveryStepsPayload>('/api/needs-discovery/steps')
}

export function submitNeedsDiscovery(form: FormData) {
  return apiPostForm<NeedsDiscoverySubmitResult>('/api/needs-discovery', form)
}

export function getOwnerRequirements(filters: {
  page?: number
  per_page?: number
  status?: string
  q?: string
} = {}) {
  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.per_page) params.set('per_page', String(filters.per_page))
  if (filters.status) params.set('status', filters.status)
  if (filters.q) params.set('q', filters.q)
  const query = params.toString()
  return apiGet<RequirementListData>(`/api/owner/requirements${query ? `?${query}` : ''}`)
}

export function getOwnerRequirement(id: number) {
  return apiGet<RequirementItem>(`/api/owner/requirements/${id}`)
}

export function updateOwnerRequirement(
  id: number,
  payload: {
    status?: string | null
    notes?: string | null
    summary?: string | null
    description?: string | null
    assigned_to?: number | null
    qualify?: boolean
  },
) {
  return apiPatch<RequirementItem>(`/api/owner/requirements/${id}`, payload)
}

export function requirementAttachmentUrl(id: number, index: number) {
  return `${API_BASE_URL}/api/owner/requirements/${id}/attachments/${index}`
}

export async function downloadRequirementAttachment(id: number, index: number, filename: string) {
  const token = getStoredToken()
  const response = await fetch(requirementAttachmentUrl(id, index), {
    headers: {
      Accept: '*/*',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  })
  if (!response.ok) {
    throw new Error('تعذر تحميل الملف.')
  }
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
}
