import {
  API_BASE_URL,
  apiDelete,
  apiDownload,
  apiGet,
  apiPatch,
  apiPost,
  apiPostForm,
  apiPut,
  getStoredToken,
  publicFetch,
} from './api'
import type { EmployeeListMeta } from '../types/api'

export type CrmLeadStatus =
  | 'NEW'
  | 'ATTEMPTED_CONTACT'
  | 'CONTACTED'
  | 'QUALIFIED'
  | 'UNQUALIFIED'
  | 'FOLLOW_UP'
  | 'INTERESTED'
  | 'PROPOSAL_SENT'
  | 'NEGOTIATION'
  | 'WON'
  | 'LOST'

export type CrmLeadPriority = 'LOW' | 'MEDIUM' | 'HIGH' | 'URGENT'

export type CrmFollowUpType = 'CALL' | 'WHATSAPP' | 'EMAIL' | 'MEETING' | 'DEMO' | 'PROPOSAL' | 'OTHER'

export type CrmFollowUpStatus =
  | 'SCHEDULED'
  | 'COMPLETED'
  | 'MISSED'
  | 'OVERDUE'
  | 'RESCHEDULED'
  | 'CANCELLED'

export type CrmActivityType =
  | 'CALL'
  | 'WHATSAPP'
  | 'EMAIL'
  | 'MEETING'
  | 'VIDEO_MEETING'
  | 'DEMO'
  | 'PROPOSAL'
  | 'NOTE'
  | 'FOLLOW_UP'
  | 'TASK'
  | 'STAGE_CHANGE'
  | 'ASSIGNMENT'
  | 'CONVERSION'

export type CrmQuotationStatus =
  | 'DRAFT'
  | 'PENDING_APPROVAL'
  | 'APPROVED'
  | 'SENT'
  | 'VIEWED'
  | 'ACCEPTED'
  | 'REJECTED'
  | 'EXPIRED'

export type CrmStage = {
  id: number
  name: string
  slug: string
  probability: number
  is_won: boolean
  is_lost: boolean
  sort_order?: number
  is_active?: boolean
}

export type CrmSource = {
  id: number
  name: string
  slug: string
  is_active?: boolean
  sort_order?: number
}

export type CrmLostReason = {
  id: number
  name: string
  slug?: string
  is_active?: boolean
  sort_order?: number
}

export type CrmTag = {
  id: number
  name: string
  slug?: string
  color?: string | null
}

export type CrmPerson = {
  id: number
  name: string
  email: string
  role?: string
}

export type CrmLead = {
  id: number
  reference: string
  full_name: string
  company_name: string | null
  company_id?: number | null
  company?: { id: number; name: string; status?: string } | null
  job_title: string | null
  phone: string | null
  alt_phone: string | null
  whatsapp: string | null
  email: string | null
  country: string | null
  city: string | null
  status: CrmLeadStatus | string
  priority: CrmLeadPriority | string
  estimated_budget: string | number | null
  deal_value: string | number | null
  score: number | null
  tags: string[] | null
  notes: string | null
  next_follow_up_at: string | null
  expected_close_at: string | null
  last_contacted_at: string | null
  first_contacted_at?: string | null
  archived_at?: string | null
  needs_attention?: boolean
  age_days?: number | null
  days_in_stage?: number | null
  days_since_contact?: number | null
  converted_at: string | null
  lost_notes?: string | null
  competitor_name?: string | null
  stage?: CrmStage | null
  source?: CrmSource | null
  assignee?: CrmPerson | null
  lost_reason?: { id: number; name: string } | null
  customer_id: number | null
  won_order_id: number | null
  won_project_id: number | null
  created_at: string | null
  updated_at?: string | null
}

export type CrmFollowUp = {
  id: number
  lead_id: number
  opportunity_id: number | null
  assigned_to: number | null
  type: CrmFollowUpType | string
  scheduled_at: string
  priority: CrmLeadPriority | string
  notes: string | null
  status: CrmFollowUpStatus | string
  completed_at: string | null
  lead?: { id: number; reference: string; full_name: string; assigned_to?: number | null } | null
  assignee?: CrmPerson | null
}

export type CrmActivity = {
  id: number
  lead_id: number | null
  opportunity_id: number | null
  user_id: number
  type: CrmActivityType | string
  occurred_at: string
  duration_minutes: number | null
  call_result: string | null
  result: string | null
  notes: string | null
  next_action: string | null
  user?: CrmPerson | null
  lead?: { id: number; reference: string; full_name: string } | null
}

export type CrmQuotationItem = {
  id?: number
  description: string
  quantity: number | string
  unit_price: number | string
  discount_amount?: number | string | null
  line_total?: number | string
  service_id?: number | null
  package_id?: number | null
  sort_order?: number
}

export type CrmQuotation = {
  id: number
  number: string
  lead_id: number
  opportunity_id: number | null
  customer_id: number | null
  created_by: number
  status: CrmQuotationStatus | string
  subtotal: string | number
  discount_amount: string | number
  discount_percent?: string | number | null
  tax_amount: string | number
  total: string | number
  currency: string
  valid_until: string | null
  notes: string | null
  terms: string | null
  delivery_time?: string | null
  public_token?: string | null
  sent_at: string | null
  approved_at?: string | null
  rejected_at?: string | null
  rejection_notes?: string | null
  created_at?: string | null
  lead?: { id: number; reference: string; full_name: string } | null
  creator?: CrmPerson | null
  items?: CrmQuotationItem[]
}

export type CrmCompany = {
  id: number
  name: string
  industry: string | null
  website: string | null
  country: string | null
  city: string | null
  address: string | null
  phone: string | null
  email: string | null
  company_size: string | null
  source_id: number | null
  assigned_to: number | null
  notes: string | null
  status: string
  archived_at?: string | null
  contacts_count?: number
  leads_count?: number
  opportunities_count?: number
  source?: CrmSource | null
  assignee?: CrmPerson | null
  contacts?: CrmContact[]
  leads?: Array<{
    id: number
    reference: string
    full_name: string
    status: string
    deal_value?: string | number | null
    assigned_to?: number | null
  }>
  opportunities?: Array<{
    id: number
    reference: string
    name: string | null
    deal_value?: string | number | null
    probability?: number | null
  }>
  created_at?: string | null
  updated_at?: string | null
}

export type CrmContact = {
  id: number
  company_id: number
  lead_id: number | null
  customer_id: number | null
  name: string
  phone: string | null
  whatsapp: string | null
  email: string | null
  job_title: string | null
  department: string | null
  is_primary: boolean
  notes: string | null
  company?: { id: number; name: string } | null
  lead?: { id: number; reference: string; full_name: string } | null
  created_at?: string | null
}

export type CrmOpportunity = {
  id: number
  reference: string
  name: string | null
  lead_id: number | null
  company_id: number | null
  customer_id: number | null
  deal_value: string | number | null
  probability: number | null
  stage_id: number | null
  assigned_to: number | null
  expected_close_at: string | null
  notes: string | null
  won_at?: string | null
  lost_at?: string | null
  stage?: CrmStage | null
  lead?: { id: number; reference: string; full_name: string } | null
  company?: { id: number; name: string } | null
  assignee?: CrmPerson | null
  created_at?: string | null
}

export type CrmSalesTarget = {
  id: number
  user_id: number | null
  period_type: string | null
  period_start: string
  period_end: string
  target_type: string
  target_value: string | number
  user?: CrmPerson | null
}

export type CrmTargetProgress = {
  id: number
  user: CrmPerson | null
  period_type: string | null
  period_start: string | null
  period_end: string | null
  target_type: string
  target_value: number
  actual_value: number
  progress_percent: number
}

export type CrmForecastData = {
  pipeline_total: number
  weighted: number
  commit: number
  best_case: number
  expected_this_month: number
  expected_next_month: number
  open_count: number
}

export type CrmCalendarEvent = {
  id: string
  type: string
  title: string
  starts_at: string | null
  related_type?: string
  related_id?: number
  lead_id?: number | null
  href?: string | null
}

export type CrmInboxItem = {
  id: string
  type: string
  title: string
  body: string | null
  href: string | null
  read_at: string | null
  created_at: string | null
  source?: string
  related_id?: number
}

export type CrmSavedFilter = {
  id: number
  user_id: number
  name: string
  entity: string
  filters: Record<string, unknown>
  created_at?: string | null
}

export type CrmAuditLog = {
  id: number
  user_id: number | null
  action: string
  auditable_type: string | null
  auditable_id: number | null
  old_values?: Record<string, unknown> | null
  new_values?: Record<string, unknown> | null
  created_at: string | null
  user?: CrmPerson | null
}

export type CrmConfig = {
  discount_max_percent: number
  stale_lead_days: number
  new_lead_sla_minutes: number
  assignment_mode: string
  round_robin_cursor?: number
  [key: string]: unknown
}

export type CrmDashboardData = {
  range: { from: string; to: string }
  view?: 'manager' | 'rep' | string
  kpis: {
    new_leads: number
    open_leads: number
    won_leads: number
    lost_leads: number
    win_rate: number
    pipeline_value: number
    won_revenue: number
    weighted_pipeline: number
    follow_ups_scheduled: number
    follow_ups_overdue: number
    activities: number
    quotations: number
    quotations_accepted: number
    stale_leads?: number
    unassigned_leads?: number
  }
  funnel?: Array<{
    id: number
    name: string
    slug: string
    count: number
    value: number
  }>
  manager?: {
    rep_performance: Array<{
      user: { id: number; name: string; email: string }
      won_deals: number
      won_revenue: number
      open_leads: number
      activities: number
    }>
    stale_leads: number
    unassigned_count: number
    sla_target_minutes: number
    avg_first_contact_minutes: number | null
  }
  rep?: {
    my_open_leads: number
    my_overdue_follow_ups: number
    my_needs_attention: number
    my_won_revenue: number
  }
}

export type CrmLeadListData = {
  items: CrmLead[]
  meta: EmployeeListMeta
}

export type CrmFollowUpListData = {
  items: CrmFollowUp[]
  meta: EmployeeListMeta
}

export type CrmActivityListData = {
  items: CrmActivity[]
  meta: EmployeeListMeta
}

export type CrmQuotationListData = {
  items: CrmQuotation[]
  meta: EmployeeListMeta
}

export type CrmSettingsData = {
  sources: CrmSource[]
  stages: CrmStage[]
  lost_reasons: CrmLostReason[]
  tags: CrmTag[]
  config?: CrmConfig
}

export type CrmTeamMember = {
  id: number
  name: string
  email: string
  role: string
}

export type CrmLeadFilters = {
  page?: number
  per_page?: number
  q?: string
  status?: string
  stage_id?: number | string
  assigned_to?: number | string
  unassigned?: boolean | '1'
  needs_attention?: boolean | '1'
  stale?: boolean | '1'
  priority?: string
}

export type StoreCrmLeadPayload = {
  full_name: string
  company_name?: string
  company_id?: number
  job_title?: string
  phone?: string
  alt_phone?: string
  whatsapp?: string
  email?: string
  country?: string
  city?: string
  source_id?: number
  estimated_budget?: number
  deal_value?: number
  status?: string
  stage_id?: number
  priority?: string
  assigned_to?: number
  next_follow_up_at?: string
  expected_close_at?: string
  score?: number
  tags?: string[]
  notes?: string
}

export type StoreCrmQuotationPayload = {
  lead_id: number
  opportunity_id?: number
  customer_id?: number
  discount_amount?: number
  tax_amount?: number
  currency?: string
  valid_until?: string
  notes?: string
  terms?: string
  items: Array<{
    description: string
    quantity?: number
    unit_price: number
    discount_amount?: number
    service_id?: number
    package_id?: number
    sort_order?: number
  }>
}

export type PublicQuotationPayload = {
  number: string
  status: string
  subtotal: string | number
  discount_amount: string | number
  discount_percent?: string | number | null
  tax_amount: string | number
  total: string | number
  currency: string
  valid_until: string | null
  notes: string | null
  terms: string | null
  delivery_time?: string | null
  items: Array<{
    description: string
    quantity: number | string
    unit_price: number | string
    discount_amount?: number | string | null
    line_total?: number | string
  }>
}

export type CrmCustomer360 = {
  customer: {
    id: number
    name: string
    email: string
    phone?: string | null
    created_at?: string | null
  }
  metrics: {
    leads: number
    won_leads: number
    orders: number
    projects: number
    quotations: number
    opportunities: number
    lifetime_deal_value: number
  }
  leads: CrmLead[]
  orders: unknown[]
  projects: unknown[]
  quotations: CrmQuotation[]
  opportunities: CrmOpportunity[]
  recent_activities: CrmActivity[]
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

export function whatsappUrl(phone: string | null | undefined): string | null {
  if (!phone) {
    return null
  }

  const digits = phone.replace(/\D/g, '')
  return digits ? `https://wa.me/${digits}` : null
}

export function getCrmDashboard(from?: string, to?: string) {
  return apiGet<CrmDashboardData>(`/api/crm/dashboard${queryString({ from, to })}`)
}

export function getCrmLeads(filters: CrmLeadFilters = {}) {
  return apiGet<CrmLeadListData>(
    `/api/crm/leads${queryString({
      ...filters,
      unassigned: filters.unassigned === true ? '1' : filters.unassigned === false ? undefined : filters.unassigned,
      needs_attention:
        filters.needs_attention === true
          ? '1'
          : filters.needs_attention === false
            ? undefined
            : filters.needs_attention,
      stale: filters.stale === true ? '1' : filters.stale === false ? undefined : filters.stale,
    })}`,
  )
}

export function getCrmLead(id: number) {
  return apiGet<CrmLead>(`/api/crm/leads/${id}`)
}

export function createCrmLead(payload: StoreCrmLeadPayload) {
  return apiPost<CrmLead>('/api/crm/leads', payload)
}

export function updateCrmLead(id: number, payload: Partial<StoreCrmLeadPayload>) {
  return apiPut<CrmLead>(`/api/crm/leads/${id}`, payload)
}

export function assignCrmLead(id: number, assigned_to: number) {
  return apiPatch<CrmLead>(`/api/crm/leads/${id}/assign`, { assigned_to })
}

export function moveCrmLeadStage(id: number, stage_id: number) {
  return apiPatch<CrmLead>(`/api/crm/leads/${id}/stage`, { stage_id })
}

export function convertCrmLead(
  id: number,
  payload: { deal_value?: number; create_project?: boolean; customer_id?: number; title?: string } = {},
) {
  return apiPost<{
    lead: CrmLead
    customer: { id: number; name: string; email: string }
    order: unknown
    project: unknown
  }>(`/api/crm/leads/${id}/convert`, payload)
}

export function loseCrmLead(
  id: number,
  payload: { lost_reason_id: number; notes?: string; competitor?: string },
) {
  return apiPost<CrmLead>(`/api/crm/leads/${id}/lose`, payload)
}

export function getCrmLeadDuplicates(params: {
  phone?: string
  email?: string
  whatsapp?: string
  except_id?: number
}) {
  return apiGet<{ items: CrmLead[] }>(`/api/crm/leads/duplicates${queryString(params)}`)
}

export function importCrmLeads(file: File, mapping?: Record<string, string>) {
  const form = new FormData()
  form.append('file', file)
  if (mapping) {
    form.append('mapping', JSON.stringify(mapping))
  }
  return apiPostForm<{ created: number; duplicates: number; leads: CrmLead[] }>('/api/crm/leads/import', form)
}

export function bulkCrmLeads(payload: {
  lead_ids: number[]
  action: 'assign' | 'stage' | 'priority' | 'tags' | 'schedule_follow_up' | 'archive'
  assigned_to?: number
  stage_id?: number
  priority?: string
  tags?: string[]
  scheduled_at?: string
  follow_up_type?: string
  notes?: string
}) {
  return apiPost<{ updated: number; leads: CrmLead[] }>('/api/crm/leads/bulk', payload)
}

export function mergeCrmLeads(primary_id: number, secondary_id: number) {
  return apiPost<CrmLead>('/api/crm/leads/merge', { primary_id, secondary_id })
}

export async function exportCrmEntity(
  entity: 'leads' | 'opportunities' | 'companies' | 'contacts' | 'quotations',
  format: 'csv' | 'xlsx' = 'csv',
) {
  await apiDownload(`/api/crm/export/${entity}${queryString({ format })}`, `${entity}.${format}`)
}

export function getCrmPipelineStages() {
  return apiGet<{ items: CrmStage[] }>('/api/crm/pipeline/stages')
}

export function getCrmFollowUps(filters: {
  page?: number
  per_page?: number
  status?: string
  overdue?: boolean | '1'
} = {}) {
  return apiGet<CrmFollowUpListData>(
    `/api/crm/follow-ups${queryString({
      ...filters,
      overdue: filters.overdue === true ? '1' : filters.overdue === false ? undefined : filters.overdue,
    })}`,
  )
}

export function createCrmFollowUp(payload: {
  lead_id: number
  type: string
  scheduled_at: string
  notes?: string
  priority?: string
  assigned_to?: number
}) {
  return apiPost<CrmFollowUp>('/api/crm/follow-ups', payload)
}

export function completeCrmFollowUp(id: number, notes?: string) {
  return apiPost<CrmFollowUp>(`/api/crm/follow-ups/${id}/complete`, notes ? { notes } : {})
}

export function rescheduleCrmFollowUp(id: number, scheduled_at: string, notes?: string) {
  return apiPatch<CrmFollowUp>(`/api/crm/follow-ups/${id}/reschedule`, {
    scheduled_at,
    notes,
  })
}

export function cancelCrmFollowUp(id: number) {
  return apiPost<CrmFollowUp>(`/api/crm/follow-ups/${id}/cancel`, {})
}

export function getCrmActivities(filters: { lead_id?: number; page?: number; per_page?: number } = {}) {
  return apiGet<CrmActivityListData>(`/api/crm/activities${queryString(filters)}`)
}

export function createCrmActivity(payload: {
  lead_id: number
  type: string
  notes?: string
  call_result?: string
  duration_minutes?: number
  occurred_at?: string
  next_action?: string
}) {
  return apiPost<CrmActivity>('/api/crm/activities', payload)
}

export function getCrmQuotations(filters: { page?: number; per_page?: number; status?: string } = {}) {
  return apiGet<CrmQuotationListData>(`/api/crm/quotations${queryString(filters)}`)
}

export function getCrmQuotation(id: number) {
  return apiGet<CrmQuotation>(`/api/crm/quotations/${id}`)
}

export function createCrmQuotation(payload: StoreCrmQuotationPayload) {
  return apiPost<CrmQuotation>('/api/crm/quotations', payload)
}

export function updateCrmQuotationStatus(id: number, status: string) {
  return apiPatch<CrmQuotation>(`/api/crm/quotations/${id}/status`, { status })
}

export async function downloadCrmQuotationPdf(id: number, numberHint = 'quotation') {
  await apiDownload(`/api/crm/quotations/${id}/pdf`, `${numberHint}.pdf`)
}

export function approveCrmQuotation(id: number) {
  return apiPost<CrmQuotation>(`/api/crm/quotations/${id}/approve`, {})
}

export function rejectCrmQuotation(id: number, notes?: string) {
  return apiPost<CrmQuotation>(`/api/crm/quotations/${id}/reject`, notes ? { notes } : {})
}

export function sendCrmQuotation(id: number) {
  return apiPost<CrmQuotation>(`/api/crm/quotations/${id}/send`, {})
}

export function getCrmCompanies(filters: {
  page?: number
  per_page?: number
  q?: string
  status?: string
} = {}) {
  return apiGet<{ items: CrmCompany[]; meta: EmployeeListMeta }>(
    `/api/crm/companies${queryString(filters)}`,
  )
}

export function getCrmCompany(id: number) {
  return apiGet<CrmCompany>(`/api/crm/companies/${id}`)
}

export function createCrmCompany(payload: Partial<CrmCompany> & { name: string }) {
  return apiPost<CrmCompany>('/api/crm/companies', payload)
}

export function updateCrmCompany(id: number, payload: Partial<CrmCompany>) {
  return apiPut<CrmCompany>(`/api/crm/companies/${id}`, payload)
}

export function archiveCrmCompany(id: number) {
  return apiPost<CrmCompany>(`/api/crm/companies/${id}/archive`, {})
}

export function deleteCrmCompany(id: number) {
  return apiDelete<{ status?: string } | CrmCompany>(`/api/crm/companies/${id}`)
}

export function getCrmContacts(filters: {
  page?: number
  per_page?: number
  q?: string
  company_id?: number | string
} = {}) {
  return apiGet<{ items: CrmContact[]; meta: EmployeeListMeta }>(
    `/api/crm/contacts${queryString(filters)}`,
  )
}

export function getCrmContact(id: number) {
  return apiGet<CrmContact>(`/api/crm/contacts/${id}`)
}

export function createCrmContact(payload: {
  company_id: number
  name: string
  lead_id?: number
  customer_id?: number
  phone?: string
  whatsapp?: string
  email?: string
  job_title?: string
  department?: string
  is_primary?: boolean
  notes?: string
}) {
  return apiPost<CrmContact>('/api/crm/contacts', payload)
}

export function updateCrmContact(id: number, payload: Partial<CrmContact>) {
  return apiPut<CrmContact>(`/api/crm/contacts/${id}`, payload)
}

export function getCrmOpportunities(filters: { page?: number; per_page?: number; q?: string } = {}) {
  return apiGet<{ items: CrmOpportunity[]; meta: EmployeeListMeta; weighted_revenue?: number }>(
    `/api/crm/opportunities${queryString(filters)}`,
  )
}

export function getCrmOpportunity(id: number) {
  return apiGet<CrmOpportunity>(`/api/crm/opportunities/${id}`)
}

export function createCrmOpportunity(payload: {
  lead_id: number
  name?: string
  deal_value?: number
  stage_id?: number
  assigned_to?: number
  expected_close_at?: string
  notes?: string
}) {
  return apiPost<CrmOpportunity>('/api/crm/opportunities', payload)
}

export function moveCrmOpportunityStage(id: number, stage_id: number) {
  return apiPatch<CrmOpportunity>(`/api/crm/opportunities/${id}/stage`, { stage_id })
}

export function getCrmTargets(filters: { page?: number; per_page?: number; user_id?: number } = {}) {
  return apiGet<{ items: CrmSalesTarget[]; meta: EmployeeListMeta }>(
    `/api/crm/targets${queryString(filters)}`,
  )
}

export function getCrmTargetsProgress(filters: { user_id?: number } = {}) {
  return apiGet<{ items: CrmTargetProgress[] }>(`/api/crm/targets/progress${queryString(filters)}`)
}

export function createCrmTarget(payload: {
  user_id?: number
  period_type?: string
  period_start: string
  period_end: string
  target_type: string
  target_value: number
}) {
  return apiPost<CrmSalesTarget>('/api/crm/targets', payload)
}

export function updateCrmTarget(
  id: number,
  payload: Partial<{
    user_id: number
    period_type: string
    period_start: string
    period_end: string
    target_type: string
    target_value: number
  }>,
) {
  return apiPut<CrmSalesTarget>(`/api/crm/targets/${id}`, payload)
}

export function deleteCrmTarget(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/crm/targets/${id}`)
}

export function getCrmForecast() {
  return apiGet<CrmForecastData>('/api/crm/forecast')
}

export function getCrmCalendar(from: string, to: string) {
  return apiGet<{ events: CrmCalendarEvent[] }>(`/api/crm/calendar${queryString({ from, to })}`)
}

export function getCrmInbox() {
  return apiGet<{ items: CrmInboxItem[]; unread_count: number }>('/api/crm/inbox')
}

export function markCrmInboxRead(payload: { ids?: string[]; all?: boolean } = {}) {
  return apiPost<{ marked: number }>('/api/crm/inbox/mark-read', payload)
}

export function getCrmAuditLogs(filters: {
  page?: number
  per_page?: number
  action?: string
  user_id?: number | string
} = {}) {
  return apiGet<{ items: CrmAuditLog[]; meta: EmployeeListMeta }>(
    `/api/crm/audit-logs${queryString(filters)}`,
  )
}

export function getCrmCustomer360(userId: number) {
  return apiGet<CrmCustomer360>(`/api/crm/customers/${userId}`)
}

export function getCrmSavedFilters() {
  return apiGet<{ items: CrmSavedFilter[] }>('/api/crm/saved-filters')
}

export function createCrmSavedFilter(payload: {
  name: string
  entity?: string
  filters: Record<string, unknown>
}) {
  return apiPost<CrmSavedFilter>('/api/crm/saved-filters', payload)
}

export function deleteCrmSavedFilter(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/crm/saved-filters/${id}`)
}

export function getCrmReportSources() {
  return apiGet<{ items: Array<{ source_id: number | null; total: number; source?: CrmSource | null }> }>(
    '/api/crm/reports/sources',
  )
}

export function getCrmReportReps() {
  return apiGet<{
    items: Array<{
      user: CrmTeamMember | null
      total_leads: number
      won_leads: number
      lost_leads: number
      deal_value_sum: number
    }>
  }>('/api/crm/reports/reps')
}

export function getCrmReportLostReasons() {
  return apiGet<{
    items: Array<{ reason: CrmLostReason | null; total: number }>
  }>('/api/crm/reports/lost-reasons')
}

export function getCrmReportServices() {
  return apiGet<{
    items: Array<{
      service_id: number | null
      total: number
      deal_value_sum: number
      service?: { id: number; name: string; slug: string } | null
    }>
  }>('/api/crm/reports/services')
}

export function getCrmSettings() {
  return apiGet<CrmSettingsData>('/api/crm/settings')
}

export function updateCrmSettingsConfig(payload: Partial<CrmConfig>) {
  return apiPut<{ config: CrmConfig }>('/api/crm/settings/config', payload)
}

export type CrmAssignmentRule = {
  id: number
  name: string
  is_active: boolean
  match_type: 'source' | 'service'
  match_value: string
  assign_to_user_id: number
  sort_order: number
  assignee?: CrmPerson | null
}

export function getCrmAssignmentRules() {
  return apiGet<{ items: CrmAssignmentRule[] }>('/api/crm/assignment-rules')
}

export function createCrmAssignmentRule(payload: {
  name: string
  match_type: 'source' | 'service'
  match_value: string
  assign_to_user_id: number
  is_active?: boolean
  sort_order?: number
}) {
  return apiPost<CrmAssignmentRule>('/api/crm/assignment-rules', payload)
}

export function updateCrmAssignmentRule(id: number, payload: Partial<CrmAssignmentRule>) {
  return apiPut<CrmAssignmentRule>(`/api/crm/assignment-rules/${id}`, payload)
}

export function deleteCrmAssignmentRule(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/crm/assignment-rules/${id}`)
}

export function createCrmSource(payload: { name: string; slug?: string; is_active?: boolean; sort_order?: number }) {
  return apiPost<CrmSource>('/api/crm/settings/sources', payload)
}

export function updateCrmSource(id: number, payload: Partial<CrmSource>) {
  return apiPut<CrmSource>(`/api/crm/settings/sources/${id}`, payload)
}

export function createCrmStage(payload: {
  name: string
  slug?: string
  is_won?: boolean
  is_lost?: boolean
  probability?: number
  sort_order?: number
  is_active?: boolean
}) {
  return apiPost<CrmStage>('/api/crm/settings/stages', payload)
}

export function updateCrmStage(id: number, payload: Partial<CrmStage>) {
  return apiPut<CrmStage>(`/api/crm/settings/stages/${id}`, payload)
}

export function createCrmLostReason(payload: {
  name: string
  slug?: string
  is_active?: boolean
  sort_order?: number
}) {
  return apiPost<CrmLostReason>('/api/crm/settings/lost-reasons', payload)
}

export function updateCrmLostReason(id: number, payload: Partial<CrmLostReason>) {
  return apiPut<CrmLostReason>(`/api/crm/settings/lost-reasons/${id}`, payload)
}

export function createCrmTag(payload: { name: string; slug?: string; color?: string }) {
  return apiPost<CrmTag>('/api/crm/settings/tags', payload)
}

export function updateCrmTag(id: number, payload: Partial<CrmTag>) {
  return apiPut<CrmTag>(`/api/crm/settings/tags/${id}`, payload)
}

export function getCrmTeam() {
  return apiGet<{ items: CrmTeamMember[] }>('/api/crm/team')
}

export function getPublicQuotation(token: string) {
  return publicFetch<PublicQuotationPayload>(`/api/public/quotations/${encodeURIComponent(token)}`)
}

export function acceptPublicQuotation(token: string) {
  return publicFetch<PublicQuotationPayload>(`/api/public/quotations/${encodeURIComponent(token)}/accept`, {
    method: 'POST',
    body: JSON.stringify({}),
  })
}

export function rejectPublicQuotation(token: string, notes?: string) {
  return publicFetch<PublicQuotationPayload>(`/api/public/quotations/${encodeURIComponent(token)}/reject`, {
    method: 'POST',
    body: JSON.stringify(notes ? { notes } : {}),
  })
}

/** Convenience when a blob download needs an Authorization header (apiDownload already does this). */
export async function downloadAuthorizedBlob(path: string, fallbackName: string): Promise<void> {
  const token = getStoredToken()
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: {
      Accept: '*/*',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  })

  if (!response.ok) {
    throw new Error('Download failed.')
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = fallbackName
  link.rel = 'noopener'
  document.body.append(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

/** Load open pipeline leads across pages (API caps per_page at 50). */
export async function getAllOpenCrmLeads(): Promise<CrmLead[]> {
  const items: CrmLead[] = []
  let page = 1
  let lastPage = 1

  do {
    const response = await getCrmLeads({ page, per_page: 50 })
    items.push(...response.data.items)
    lastPage = response.data.meta.last_page
    page += 1
  } while (page <= lastPage)

  return items.filter((lead) => lead.status !== 'WON' && lead.status !== 'LOST')
}
