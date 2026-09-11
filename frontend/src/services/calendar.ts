import { apiDelete, apiDownload, apiGet, apiPost, apiPostForm, apiPut } from './api'

export type CalendarItemType =
  | 'TASK'
  | 'MEETING'
  | 'APPOINTMENT'
  | 'FOLLOW_UP'
  | 'CALL'
  | 'DEADLINE'
  | 'REMINDER'
  | 'EVENT'
  | 'OTHER'

export type CalendarItemStatus = 'SCHEDULED' | 'IN_PROGRESS' | 'COMPLETED' | 'CANCELLED' | 'OVERDUE'

export type CalendarItemPriority = 'LOW' | 'MEDIUM' | 'HIGH' | 'URGENT'

export type CalendarVisibility = 'PRIVATE' | 'PARTICIPANTS' | 'TEAM'

export type CalendarSource =
  | 'MANUAL'
  | 'CRM'
  | 'ORDER'
  | 'PROJECT'
  | 'PRINTING'
  | 'PAYMENT'
  | 'CONTENT'
  | 'SUPPLIER'

export type CalendarReminderOffset =
  | 'AT_START'
  | 'MINUTES_5'
  | 'MINUTES_10'
  | 'MINUTES_15'
  | 'MINUTES_30'
  | 'HOUR_1'
  | 'HOURS_2'
  | 'DAY_1'
  | 'DAY_2'
  | 'WEEK_1'

export type CalendarRecurrenceScope = 'this' | 'future' | 'all'

export type CalendarRecurrenceFreq = 'DAILY' | 'WEEKLY' | 'MONTHLY' | 'YEARLY'

export type CalendarAssignee = {
  id: number
  name: string
  role?: string | null
}

export type CalendarReminder = {
  id?: number
  offset: CalendarReminderOffset | string
  remind_at?: string | null
  sent_at?: string | null
}

export type CalendarChecklistItem = {
  id?: string
  text: string
  done?: boolean
}

export type CalendarItem = {
  id: number | string
  master_id?: number | null
  is_linked: boolean
  is_occurrence?: boolean
  occurrence_at?: string | null
  title: string
  description: string | null
  type: CalendarItemType | string
  status: CalendarItemStatus | string
  priority: CalendarItemPriority | string
  visibility: CalendarVisibility | string
  source: CalendarSource | string
  starts_at: string
  ends_at: string | null
  all_day: boolean
  created_by?: number | null
  creator: { id: number; name: string } | null
  assignees: CalendarAssignee[]
  reminders: CalendarReminder[]
  related_type: string | null
  related_id: number | null
  related_label: string | null
  related_href: string | null
  department_id?: number | null
  completed_at?: string | null
  can_edit: boolean
  location?: string | null
  meeting_url?: string | null
  blocked_by_id?: number | null
  checklist?: CalendarChecklistItem[]
  recurrence_rule?: string | null
  recurrence_until?: string | null
  recurrence_count?: number | null
  recurrence_parent_id?: number | null
  recurrence_exceptions?: string[]
  comments_count?: number
  attachments_count?: number
  activities?: CalendarActivity[]
}

export type CalendarSummary = {
  today_tasks: number
  today_meetings: number
  overdue: number
  upcoming: number
}

export type CalendarListData = {
  items: CalendarItem[]
  summary: CalendarSummary
}

export type CalendarSummaryData = {
  summary: CalendarSummary
  upcoming: CalendarItem[]
}

export type CalendarAssigneesData = {
  items: CalendarAssignee[]
}

export type CalendarListFilters = {
  from: string
  to: string
  scope?: 'mine' | 'team'
  type?: string
  status?: string
  priority?: string
  source?: string
  assignee_id?: number | string
  department_id?: number | string | null
  q?: string
  include_linked?: '0' | '1'
}

export type CalendarTaskFilters = {
  scope?: 'mine' | 'team'
  status?: string
  priority?: string
  assignee_id?: number | string
  department_id?: number | string | null
  q?: string
  overdue?: '0' | '1' | boolean
  from?: string
  to?: string
  sort?: string
  page?: number
  per_page?: number
}

export type CalendarPaginationMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type CalendarTasksData = {
  items: CalendarItem[]
  meta: CalendarPaginationMeta
}

export type CalendarWorkloadAssignee = {
  id: number | null
  name: string
  count: number
  tasks: number
}

export type CalendarWorkloadData = {
  from: string
  to: string
  by_assignee: CalendarWorkloadAssignee[]
  totals: {
    items: number
    tasks: number
    meetings: number
    overdue: number
  }
}

export type CalendarConflictPayload = {
  starts_at: string
  ends_at: string
  assignee_ids?: number[]
  exclude_id?: number | null
}

export type CalendarBulkChanges = {
  status?: string
  priority?: string
  assignee_ids?: number[]
}

export type CalendarTemplate = {
  id: number
  name: string
  type: string
  priority?: string | null
  title_pattern?: string | null
  description?: string | null
  default_duration_minutes?: number | null
  default_reminders?: string[] | null
  is_active?: boolean
  created_by?: number | null
}

export type CalendarTemplatePayload = {
  name: string
  type: string
  priority?: string
  title_pattern?: string | null
  description?: string | null
  default_duration_minutes?: number
  default_reminders?: string[]
}

export type CalendarUserSettings = {
  id?: number
  user_id?: number
  default_view?: string
  week_starts_on?: number
  workday_start?: string
  workday_end?: string
  daily_digest?: boolean
  end_of_day_digest?: boolean
  show_completed?: boolean
  default_scope?: 'mine' | 'team'
  default_reminders?: string[] | null
}

export type CalendarSavedFilter = {
  id: number
  user_id?: number
  name: string
  filters: Record<string, unknown>
}

export type CalendarComment = {
  id: number
  calendar_item_id: number
  body: string
  edited_at?: string | null
  created_at?: string | null
  user?: { id: number; name: string } | null
}

export type CalendarActivity = {
  id: number
  action: string
  summary: string | null
  meta?: Record<string, unknown> | null
  user_id?: number | null
  user?: { id: number; name: string } | null
  created_at?: string | null
}

export type CalendarFileItem = {
  id: number
  original_name: string
  mime_type?: string | null
  size?: number | null
  uploaded_by?: number | null
  created_at?: string | null
}

export type CalendarItemPayload = {
  title: string
  description?: string | null
  type: string
  status?: string
  priority?: string
  visibility?: string
  source?: string
  starts_at: string
  ends_at?: string | null
  all_day?: boolean
  assignee_ids?: number[]
  related_type?: string | null
  related_id?: number | null
  department_id?: number | null
  reminders?: string[]
  recurrence_rule?: string | null
  recurrence_until?: string | null
  recurrence_count?: number | null
  location?: string | null
  meeting_url?: string | null
  checklist?: CalendarChecklistItem[]
  blocked_by_id?: number | null
  scope?: CalendarRecurrenceScope
  occurrence_at?: string | null
}

function queryString(params: Record<string, string | number | boolean | undefined | null>): string {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') {
      continue
    }

    search.set(key, String(value))
  }

  const query = search.toString()
  return query === '' ? '' : `?${query}`
}

export function resolveCalendarItemId(item: Pick<CalendarItem, 'id' | 'master_id' | 'is_occurrence'>): number {
  if (item.is_occurrence && item.master_id != null) {
    return Number(item.master_id)
  }
  if (typeof item.id === 'string' && item.id.includes(':')) {
    return Number(item.id.split(':')[0])
  }
  return Number(item.id)
}

export function resolveOccurrenceAt(
  item: Pick<CalendarItem, 'occurrence_at' | 'starts_at' | 'is_occurrence' | 'id'>,
): string | null {
  if (item.occurrence_at) return item.occurrence_at
  if (item.is_occurrence && typeof item.id === 'string' && item.id.includes(':')) {
    const datePart = item.id.split(':')[1]
    return datePart ? `${datePart}T00:00:00.000Z` : null
  }
  if (item.is_occurrence && item.starts_at) return item.starts_at
  return null
}

export function buildRecurrenceRule(freq: CalendarRecurrenceFreq | '' | null, interval = 1): string | null {
  if (!freq) return null
  const safeInterval = Math.max(1, interval)
  return safeInterval === 1 ? `FREQ=${freq}` : `FREQ=${freq};INTERVAL=${safeInterval}`
}

export function parseRecurrenceFreq(rule: string | null | undefined): CalendarRecurrenceFreq | '' {
  if (!rule) return ''
  const match = rule.toUpperCase().match(/FREQ=(DAILY|WEEKLY|MONTHLY|YEARLY)/)
  return (match?.[1] as CalendarRecurrenceFreq | undefined) ?? ''
}

export function getCalendarItems(filters: CalendarListFilters) {
  return apiGet<CalendarListData>(`/api/calendar${queryString(filters)}`)
}

export function getCalendarSummary(scope: 'mine' | 'team' = 'mine') {
  return apiGet<CalendarSummaryData>(`/api/calendar/summary${queryString({ scope })}`)
}

export function getCalendarAssignees() {
  return apiGet<CalendarAssigneesData>('/api/calendar/assignees')
}

export function getCalendarTasks(filters: CalendarTaskFilters = {}) {
  return apiGet<CalendarTasksData>(
    `/api/calendar/tasks${queryString({
      scope: filters.scope,
      status: filters.status,
      priority: filters.priority,
      assignee_id: filters.assignee_id,
      department_id: filters.department_id,
      q: filters.q,
      overdue: filters.overdue === true ? '1' : filters.overdue === false ? '0' : filters.overdue,
      from: filters.from,
      to: filters.to,
      sort: filters.sort,
      page: filters.page,
      per_page: filters.per_page,
    })}`,
  )
}

export function getCalendarWorkload(from: string, to: string, departmentId?: number | string | null) {
  return apiGet<CalendarWorkloadData>(
    `/api/calendar/workload${queryString({ from, to, department_id: departmentId })}`,
  )
}

export function checkCalendarConflicts(payload: CalendarConflictPayload) {
  return apiPost<{ items: CalendarItem[] }>('/api/calendar/conflicts', payload)
}

export function bulkUpdateCalendarItems(ids: Array<number | string>, changes: CalendarBulkChanges) {
  return apiPost<{ items: CalendarItem[] }>('/api/calendar/bulk', {
    ids: ids.map((id) => Number(String(id).split(':')[0])),
    changes,
  })
}

export function getCalendarTemplates() {
  return apiGet<{ items: CalendarTemplate[] }>('/api/calendar/templates')
}

export function createCalendarTemplate(payload: CalendarTemplatePayload) {
  return apiPost<CalendarTemplate>('/api/calendar/templates', payload)
}

export function getCalendarSettings() {
  return apiGet<CalendarUserSettings>('/api/calendar/settings')
}

export function updateCalendarSettings(payload: Partial<CalendarUserSettings>) {
  return apiPut<CalendarUserSettings>('/api/calendar/settings', payload)
}

export function getCalendarSavedFilters() {
  return apiGet<{ items: CalendarSavedFilter[] }>('/api/calendar/saved-filters')
}

export function createCalendarSavedFilter(name: string, filters: Record<string, unknown>) {
  return apiPost<CalendarSavedFilter>('/api/calendar/saved-filters', { name, filters })
}

export function deleteCalendarSavedFilter(id: number | string) {
  return apiDelete<{ deleted: boolean }>(`/api/calendar/saved-filters/${id}`)
}

export function exportCalendarIcs(filters: { from: string; to: string; scope?: 'mine' | 'team' }) {
  return apiDownload(
    `/api/calendar/export.ics${queryString(filters)}`,
    `calendar-${filters.from}-${filters.to}.ics`,
  )
}

export function getCalendarItem(id: number | string) {
  return apiGet<CalendarItem>(`/api/calendar/${id}`)
}

export function createCalendarItem(payload: CalendarItemPayload) {
  return apiPost<CalendarItem>('/api/calendar', payload)
}

export function updateCalendarItem(id: number | string, payload: Partial<CalendarItemPayload>) {
  return apiPut<CalendarItem>(`/api/calendar/${id}`, payload)
}

export function deleteCalendarItem(
  id: number | string,
  options?: { scope?: CalendarRecurrenceScope; occurrence_at?: string | null },
) {
  return apiDelete<null>(
    `/api/calendar/${id}${queryString({
      scope: options?.scope,
      occurrence_at: options?.occurrence_at,
    })}`,
  )
}

export function completeCalendarItem(id: number | string) {
  return apiPost<CalendarItem>(`/api/calendar/${id}/complete`, {})
}

export function rescheduleCalendarItem(
  id: number | string,
  payload: {
    starts_at: string
    ends_at?: string | null
    scope?: CalendarRecurrenceScope
    occurrence_at?: string | null
  },
) {
  return apiPost<CalendarItem>(`/api/calendar/${id}/reschedule`, payload)
}

export function duplicateCalendarItem(id: number | string, startsAt: string) {
  return apiPost<CalendarItem>(`/api/calendar/${id}/duplicate`, { starts_at: startsAt })
}

export function updateCalendarChecklist(id: number | string, checklist: CalendarChecklistItem[]) {
  return apiPut<CalendarItem>(`/api/calendar/${id}/checklist`, { checklist })
}

export function getCalendarActivities(id: number | string) {
  return apiGet<{ items: CalendarActivity[] }>(`/api/calendar/${id}/activities`)
}

export function getCalendarComments(id: number | string) {
  return apiGet<{ items: CalendarComment[] }>(`/api/calendar/${id}/comments`)
}

export function createCalendarComment(id: number | string, body: string) {
  return apiPost<CalendarComment>(`/api/calendar/${id}/comments`, { body })
}

export function updateCalendarComment(commentId: number | string, body: string) {
  return apiPut<CalendarComment>(`/api/calendar/comments/${commentId}`, { body })
}

export function deleteCalendarComment(commentId: number | string) {
  return apiDelete<{ deleted: boolean }>(`/api/calendar/comments/${commentId}`)
}

export function getCalendarFiles(id: number | string) {
  return apiGet<{ items: CalendarFileItem[] }>(`/api/calendar/${id}/files`)
}

export function uploadCalendarFile(id: number | string, file: File) {
  const body = new FormData()
  body.append('file', file)
  return apiPostForm<CalendarFileItem>(`/api/calendar/${id}/files`, body)
}

export function deleteCalendarFile(id: number | string, fileId: number | string) {
  return apiDelete<{ detached: boolean }>(`/api/calendar/${id}/files/${fileId}`)
}

export function downloadCalendarItemIcs(id: number | string, fallbackName = 'calendar-item.ics') {
  return apiDownload(`/api/calendar/${id}/ics`, fallbackName)
}
