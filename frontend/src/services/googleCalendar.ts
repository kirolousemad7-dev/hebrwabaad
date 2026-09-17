import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './api'

export type GoogleCalendarStatus = {
  connected: boolean
  configured?: boolean
  google_email?: string | null
  calendar_id?: string | null
  meet_enabled?: boolean
  sync_enabled?: boolean
  connected_at?: string | null
  last_synced_at?: string | null
  last_error?: string | null
}

export type TaskGoogleSyncPayload = {
  google_sync_enabled: boolean
  google_sync_status: string
  google_sync_status_label_ar?: string
  google_event_id?: string | null
  google_calendar_id?: string | null
  google_html_link?: string | null
  google_synced_at?: string | null
  google_sync_error?: string | null
  google_meet_enabled?: boolean
  start_at?: string | null
  due_at?: string | null
  timezone?: string | null
  location?: string | null
}

export function getGoogleCalendarStatus() {
  return apiGet<GoogleCalendarStatus>('/google-calendar/status')
}

export function connectGoogleCalendar() {
  return apiPost<{ authorize_url: string; state: string }>('/google-calendar/connect')
}

export function disconnectGoogleCalendar() {
  return apiDelete<{ connected: boolean }>('/google-calendar/disconnect')
}

export function updateGoogleCalendarSettings(payload: {
  meet_enabled?: boolean
  sync_enabled?: boolean
  calendar_id?: string
}) {
  return apiPatch<GoogleCalendarStatus>('/google-calendar/settings', payload)
}

export function getTaskGoogleSync(taskId: number) {
  return apiGet<TaskGoogleSyncPayload>(`/workspace/tasks/${taskId}/google-calendar`)
}

export function enableTaskGoogleSync(
  taskId: number,
  payload: {
    start_at?: string | null
    due_at?: string | null
    timezone?: string
    location?: string
    google_meet_enabled?: boolean
    reminders?: Array<string | number>
  } = {},
) {
  return apiPost<TaskGoogleSyncPayload>(`/workspace/tasks/${taskId}/google-calendar/enable`, payload)
}

export function syncTaskGoogleCalendar(taskId: number) {
  return apiPost<TaskGoogleSyncPayload>(`/workspace/tasks/${taskId}/google-calendar/sync`)
}

export function disableTaskGoogleSync(taskId: number, deleteRemote = true) {
  return apiPost<TaskGoogleSyncPayload>(`/workspace/tasks/${taskId}/google-calendar/disable`, {
    delete_remote: deleteRemote,
  })
}

export function updateTaskReminders(taskId: number, reminders: Array<string | number>) {
  return apiPut<{ reminders: Array<Record<string, unknown>> }>(`/workspace/tasks/${taskId}/reminders`, {
    reminders,
  })
}
