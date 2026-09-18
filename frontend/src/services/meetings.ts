import { apiDelete, apiGet, apiPatch, apiPost } from './api'

export type MeetingProvider = 'GOOGLE_MEET' | 'ZOOM' | 'NONE'

export type VideoMeeting = {
  id: number
  provider: MeetingProvider | string
  provider_label_ar?: string
  meeting_id?: string | null
  join_url?: string | null
  host_url?: string | null
  start_at?: string | null
  end_at?: string | null
  timezone?: string | null
  title: string
  description?: string | null
  status: string
  status_label_ar?: string
  external_event_id?: string | null
  calendar_event_url?: string | null
  task_id?: number | null
  project_id?: number | null
  can_manage?: boolean
  include_customer?: boolean
}

export type MeetingProviderInfo = {
  provider: MeetingProvider | string
  label_ar: string
  configured: boolean
}

export function listMeetingProviders() {
  return apiGet<{ providers: MeetingProviderInfo[] }>('/meetings/providers')
}

export function listMeetings(query = '') {
  return apiGet<{ items: VideoMeeting[] }>(`/meetings${query}`)
}

export function createMeeting(payload: {
  provider: MeetingProvider | string
  title: string
  description?: string
  start_at: string
  end_at?: string
  timezone?: string
  task_id?: number
  project_id?: number
  commercial_quotation_id?: number
  supplier_id?: number
  customer_id?: number
  include_customer?: boolean
  participant_ids?: number[]
}) {
  return apiPost<VideoMeeting>('/meetings', payload)
}

export function updateMeeting(id: number, payload: Record<string, unknown>) {
  return apiPatch<VideoMeeting>(`/meetings/${id}`, payload)
}

export function cancelMeeting(id: number) {
  return apiDelete<VideoMeeting>(`/meetings/${id}`)
}
