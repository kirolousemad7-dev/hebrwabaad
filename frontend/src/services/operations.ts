import { apiDelete, apiDownload, apiGet, apiPost, apiPut } from './api'
import type { CalendarItem } from './calendar'

export type Department = {
  id: number
  name: string
  slug: string
  description: string | null
  manager_id: number | null
  manager: { id: number; name: string } | null
  is_active: boolean
  sort_order: number
  employees_count: number
  created_at?: string | null
  updated_at?: string | null
}

export type DepartmentOption = {
  id: number
  name: string
  slug: string
}

export type DepartmentPayload = {
  name: string
  slug?: string | null
  description?: string | null
  manager_id?: number | null
  is_active?: boolean
  sort_order?: number
}

export type ProjectHealth = {
  status: 'on_track' | 'needs_attention' | 'overdue' | string
  label: string
  overdue_workspace_tasks?: number
  overdue_calendar_tasks?: number
  days_to_deadline?: number | null
}

export type OperationsProjectListItem = {
  id: number
  title: string
  status: string
  deadline: string | null
  progress: number
  health: ProjectHealth
  account_manager: { id: number; name: string } | null
  customer: { id: number; name: string } | null
}

export type ProjectWorkspaceMember = {
  id: number
  user_id: number
  role: string
  user: { id: number; name: string; role?: string | null } | null
}

export type ProjectPhaseStatus = 'PENDING' | 'IN_PROGRESS' | 'DONE' | string

export type ProjectPhase = {
  id: number
  project_id: number
  title: string
  description: string | null
  status: ProjectPhaseStatus
  starts_at: string | null
  ends_at: string | null
  sort_order: number
  is_client_visible: boolean
  responsible_user_id: number | null
  responsible?: { id: number; name: string } | null
  progress?: {
    total: number
    completed: number
    in_progress: number
    pending: number
    overdue: number
    percent: number
  }
  milestones?: ProjectStructureMilestone[]
  tasks?: ProjectStructureTask[]
}

export type ProjectStructureTask = {
  id: number
  title: string
  status: string
  priority?: string | null
  phase_id: number | null
  milestone_id: number | null
  assigned_to: number | null
  assignee?: { id: number; name: string } | null
  deadline: string | null
  start_at: string | null
  due_at: string | null
  calendar_item_id: number | null
  is_client_visible: boolean
}

export type ProjectStructureMilestone = {
  id: number
  project_id: number
  phase_id: number | null
  title: string
  description?: string | null
  starts_at?: string | null
  due_date: string | null
  status: string
  sort_order?: number
  is_client_visible?: boolean
  responsible_user_id?: number | null
  responsible?: { id: number; name: string } | null
  progress?: { total: number; completed: number; percent: number }
  tasks?: ProjectStructureTask[]
}

export type ProjectStructure = {
  project_id: number
  phases: ProjectPhase[]
  unassigned_milestones: ProjectStructureMilestone[]
  unassigned_tasks: ProjectStructureTask[]
  progress: {
    total: number
    completed: number
    in_progress: number
    pending: number
    overdue: number
    percent: number
  }
}

export type ProjectReference = {
  id: number
  project_id: number
  title: string
  description: string | null
  url: string | null
  type: string
  category: string | null
  is_client_visible: boolean
  file_id: number | null
  file?: { id: number; original_name: string } | null
  created_by: number | null
  creator?: { id: number; name: string } | null
  created_at?: string | null
  updated_at?: string | null
}

export type ProjectTeamStat = {
  user_id: number
  name: string
  role: string
  assigned: number
  completed: number
  open: number
  overdue: number
}

export type ProjectWorkspace = {
  project: {
    id: number
    title: string
    description: string | null
    status: string
    deadline: string | null
    started_at: string | null
    customer: { id: number; name: string; email?: string | null } | null
    account_manager: { id: number; name: string } | null
  }
  brief: Record<string, unknown>
  client_profile: Record<string, unknown>
  requirements: Record<string, unknown>
  scope: Record<string, unknown>
  references: ProjectReference[]
  phases: ProjectPhase[]
  structure: ProjectStructure
  current_phase: ProjectPhase | null
  next_milestone: ProjectMilestone | null
  next_task: {
    id: number
    title: string
    status: string
    deadline: string | null
    assignee: { id: number; name: string } | null
  } | null
  next_deadline?: string | null
  risks?: {
    overdue_tasks: number
    overdue_milestones: number
    due_soon: boolean
  }
  progress: {
    total: number
    todo: number
    in_progress: number
    review: number
    revision: number
    completed: number
    overdue: number
    percent: number
  }
  team_stats?: ProjectTeamStat[]
  required_services?: Array<{
    order_item_id: number
    service_id: number
    service_name: string
    quantity: number
    department_id: number | null
    department_name: string | null
    task_id: number | null
    task_status: string | null
    status_label: string
    status_key: string
    assigned_to: number | null
    requires_review: boolean
    requires_customer_approval: boolean
    revision_rounds: number | null
  }>
  calendar_tasks: {
    total: number
    open: number
    completed: number
    overdue: number
  }
  unified_work?: { open: number; overdue: number }
  members: ProjectWorkspaceMember[]
  files_count: number
  health: ProjectHealth
  upcoming_calendar_items: CalendarItem[]
  timeline_preview?: ProjectTimelineEvent[]
  recent_activity?: ProjectTimelineEvent[]
  milestones?: ProjectMilestone[]
  execution_summary?: ProjectExecutionSummary
  attention?: ProjectAttention
  deliverables?: ProjectDeliverableRow[]
  pending_approvals?: Array<{
    id: number
    type: string
    title: string
    status: string
    requested_by: { id: number; name: string } | null
    assigned_to: { id: number; name: string } | null
    created_at: string | null
  }>
  closure_readiness?: ProjectClosureReadiness
}

export type ProjectExecutionSummary = {
  current_phase: { id: number; title: string; status: string } | null
  active_milestone: {
    id: number
    title: string
    due_date: string | null
    status: string
  } | null
  open_tasks: number
  overdue_tasks: number
  in_review_tasks: number
  next_deadline: string | null
  completion_percent: number
  attention_count: number
  recent_activity_count: number | null
  health?: ProjectHealth
  risks?: { overdue_tasks: number; attention_count: number }
  closure?: ProjectClosureReadiness
}

export type ProjectAttentionItem = {
  kind: string
  related_type: string
  related_id: number
  title: string
  due_date: string | null
  assignee_name: string | null
}

export type ProjectAttention = {
  items: ProjectAttentionItem[]
  counts: {
    overdue: number
    due_soon: number
    unassigned: number
    waiting: number
  }
}

export type ProjectClosureReadiness = {
  state: 'ready' | 'attention' | string
  label: string
  issues: Array<{ key: string; label: string; count: number }>
}

export type ProjectDeliverableRow = {
  source: string
  id: string
  name: string
  quantity: number
  status_key: string
  status_label: string
  task_id?: number | null
  milestone_id: number | null
  due_date: string | null
  is_client_visible: boolean
  requires_customer_approval: boolean
  url: string | null
  file_id?: number | null
}

export type ProjectTimelineEvent = {
  type: string
  related_type: string
  related_id: number | null
  title: string
  occurred_at: string
  meta: Record<string, unknown>
}

export type ProjectMilestoneStatus = 'PENDING' | 'DONE' | 'MISSED' | string

export type ProjectMilestone = {
  id: number
  project_id: number
  phase_id?: number | null
  phase?: { id: number; title: string } | null
  title: string
  description?: string | null
  starts_at?: string | null
  due_date: string | null
  status: ProjectMilestoneStatus
  sort_order?: number
  is_client_visible?: boolean
  is_overdue?: boolean
  open_tasks?: number
  responsible_user_id?: number | null
  responsible?: { id: number; name: string } | null
  notes: string | null
  created_by: number | null
  completed_at: string | null
  progress?: { total: number; completed: number; overdue?: number; percent: number }
  created_at?: string | null
  updated_at?: string | null
}

export type ProjectMilestonePayload = {
  title: string
  description?: string | null
  phase_id?: number | null
  starts_at?: string | null
  due_date?: string | null
  status?: ProjectMilestoneStatus
  sort_order?: number
  is_client_visible?: boolean
  responsible_user_id?: number | null
  notes?: string | null
}

export type ProjectPhasePayload = {
  title: string
  description?: string | null
  status?: ProjectPhaseStatus
  starts_at?: string | null
  ends_at?: string | null
  sort_order?: number
  is_client_visible?: boolean
  responsible_user_id?: number | null
}

export type ProjectBriefPayload = {
  brief?: Record<string, unknown> | null
  client_profile?: Record<string, unknown> | null
  requirements?: Record<string, unknown> | null
  scope?: Record<string, unknown> | null
}

export type ProjectReferencePayload = {
  title: string
  description?: string | null
  url?: string | null
  type?: string
  category?: string | null
  is_client_visible?: boolean
  file_id?: number | null
}

export type WorkflowTrigger =
  | 'order.created'
  | 'order.status_changed'
  | 'crm.opportunity.won'
  | 'crm.lead.created'
  | 'calendar.task.overdue'
  | 'calendar.task.created'
  | 'calendar.task.completed'
  | 'project.deadline_approaching'
  | 'project.status_changed'
  | 'crm.quotation.expiring'
  | 'printing.required_date_approaching'
  | 'approval.approved'
  | 'approval.rejected'
  | string

export type WorkflowActionType =
  | 'create_task'
  | 'create_reminder'
  | 'create_event'
  | 'notify_user'
  | 'assign_task'
  | 'set_priority'
  | 'add_checklist'
  | string

export type WorkflowAutomation = {
  id: number
  name: string
  trigger: WorkflowTrigger
  conditions: unknown[] | Record<string, unknown>
  actions: Array<Record<string, unknown>>
  is_active: boolean
  is_template: boolean
  created_by: number | null
  creator: { id: number; name: string } | null
  last_run_at: string | null
  created_at?: string | null
  updated_at?: string | null
}

export type WorkflowAutomationPayload = {
  name: string
  trigger: string
  conditions?: unknown[] | Record<string, unknown>
  actions: Array<Record<string, unknown>>
  is_active?: boolean
  is_template?: boolean
}

export type WorkflowAutomationRun = {
  id: number
  trigger: string
  idempotency_key: string
  source_type: string | null
  source_id: number | null
  status: 'success' | 'skipped' | 'failed' | string
  result: Record<string, unknown> | null
  executed_at: string | null
}

export type AutomationDryRunResult = {
  automation_id: number
  trigger: string
  depth: number
  trigger_chain: number[]
  max_depth: number
  conditions_pass: boolean
  would_skip: null | 'loop_detected' | 'blocked_depth' | 'conditions_not_met' | string
  planned_actions: Array<
    | { type: string; action: Record<string, unknown>; would_execute: true }
    | { type: string; skipped: string }
  >
}

export type ApprovalRequest = {
  id: number | string
  type: string
  related_type: string
  related_id: number
  title: string
  notes: string | null
  status: 'pending' | 'approved' | 'rejected' | 'PENDING' | string
  decision_notes: string | null
  requested_by: number | null
  assigned_to: number
  requester: { id: number; name: string } | null
  assignee: { id: number; name: string } | null
  approved_at: string | null
  rejected_at: string | null
  created_at?: string | null
  synthetic?: boolean
  action_href?: string | null
  href?: string | null
}

export type ApprovalCreatePayload = {
  type: string
  related_type: string
  related_id: number
  title: string
  notes?: string | null
  assigned_to: number
}

export type AttentionItem = {
  attention_key?: string
  type: string
  severity: 'critical' | 'high' | 'medium' | 'low' | string
  title: string
  description?: string | null
  source?: string
  related_type?: string | null
  related_id?: number | null
  href?: string | null
  work_id?: string
  count?: number
  sample_titles?: string[]
  days_late?: number
}

export type UnifiedWorkCapabilities = {
  can_edit: boolean
  can_complete: boolean
  can_assign: boolean
  can_reschedule: boolean
  can_start: boolean
  can_link_calendar?: boolean
  can_link_task?: boolean
}

export type UnifiedWorkItem = {
  id: string
  source_type: 'task' | 'calendar' | string
  source_id: number
  title: string
  description: string | null
  status: 'open' | 'in_progress' | 'review' | 'completed' | 'cancelled' | 'overdue' | string
  priority: 'LOW' | 'MEDIUM' | 'HIGH' | 'URGENT' | string
  due_at: string | null
  starts_at: string | null
  assignee_ids: number[]
  department_id: number | null
  project_id: number | null
  related_type: string | null
  related_id: number | null
  created_by: number | null
  is_overdue: boolean
  is_completed: boolean
  is_blocked: boolean
  blocked_label: string | null
  href: string | null
  capabilities: UnifiedWorkCapabilities
  source_badge: 'task' | 'calendar' | string
  calendar_item_id?: number | null
  linked_task_id?: number | null
}

export type UnifiedWorkFilters = {
  page?: number
  per_page?: number
  source?: 'task' | 'calendar' | string
  scope?: 'mine' | 'team' | string
  project_id?: number
  assigned_to?: number
  created_by?: number
  priority?: string
  department_id?: number
  q?: string
  from?: string
  to?: string
  bucket?: 'all' | 'today' | 'overdue' | 'this_week' | 'upcoming' | 'completed' | 'today_overdue' | string
  status?: string
  sort?: 'overdue_first' | 'priority' | 'due' | 'newest' | string
}

export type UnifiedWorkListResponse = {
  items: UnifiedWorkItem[]
  meta: { current_page: number; per_page: number; total: number; last_page: number }
}

export type UnifiedWorkKanban = {
  columns: {
    open: UnifiedWorkItem[]
    in_progress: UnifiedWorkItem[]
    review: UnifiedWorkItem[]
    overdue: UnifiedWorkItem[]
    completed: UnifiedWorkItem[]
    cancelled: UnifiedWorkItem[]
  }
}

export type PrintingOpsCategory = 'today' | 'approaching' | 'overdue' | 'pending_other' | 'all' | string

export type PrintingRequestStatusValue =
  | 'PENDING'
  | 'IN_PROGRESS'
  | 'READY_FOR_DELIVERY'
  | 'COMPLETED'
  | 'CANCELLED'
  | string

export const PRINTING_STATUS_ORDER: PrintingRequestStatusValue[] = [
  'PENDING',
  'IN_PROGRESS',
  'READY_FOR_DELIVERY',
  'COMPLETED',
  'CANCELLED',
]

export const PRINTING_OPS_STATUS_LABELS: Record<string, string> = {
  PENDING: 'قيد الانتظار',
  IN_PROGRESS: 'قيد التنفيذ',
  READY_FOR_DELIVERY: 'جاهز للتسليم',
  COMPLETED: 'مكتمل',
  CANCELLED: 'ملغي',
}

export type PrintingOpsItem = {
  id: number
  product_name: string
  product_slug: string
  status: PrintingRequestStatusValue
  status_label?: string
  status_changed_at?: string | null
  required_date: string | null
  category: 'overdue' | 'today' | 'approaching' | 'pending_other' | string
  quantity: number | null
  pricing_type?: string | null
  assigned_to?: { id: number; name: string; email?: string | null } | null
  assigned_department_id?: number | null
  assigned_department?: { id: number; name: string; slug?: string | null } | null
  allowed_transitions?: PrintingRequestStatusValue[]
  user: { id: number; name: string; email: string } | null
  quoted_by: { id: number; name: string } | null
  created_at: string | null
}

export type PrintingSummaryCounts = {
  today: number
  approaching: number
  overdue: number
  pending_other: number
  total_pending: number
  total_open?: number
  [key: string]: number | undefined
}

export type PrintingBoardResponse = {
  columns: Record<string, PrintingOpsItem[]>
  summary: PrintingSummaryCounts
  approaching_days: number
}

export type PrintingStatusHistoryItem = {
  id: number
  from_status: string | null
  to_status: string
  note: string | null
  actor: { id: number; name: string } | null
  created_at: string | null
}

export type PrintingAssignPayload = {
  assigned_to?: number | null
  assigned_department_id?: number | null
}

export type OutboundWebhook = {
  id: number
  name: string
  url: string
  events: string[]
  secret_hint: string | null
  secret?: string
  is_active: boolean
  created_by: number | null
  last_delivery_at: string | null
  created_at?: string | null
  updated_at?: string | null
}

export type OutboundWebhookPayload = {
  name: string
  url: string
  events: string[]
  is_active?: boolean
}

export type WebhookDelivery = {
  id: number
  delivery_id: string
  outbound_webhook_id: number
  event: string
  idempotency_key: string
  status: string
  attempt_count: number
  response_status: number | null
  response_summary: string | null
  delivered_at: string | null
  next_retry_at: string | null
  failed_at: string | null
  created_at?: string | null
}

export type BusinessCalendarHoliday = {
  id: number
  date: string
  name: string
}

export type BusinessCalendar = {
  id: number
  name: string
  timezone: string
  week_start: number
  working_days: number[]
  work_start: string
  work_end: string
  is_default: boolean
  is_active: boolean
  created_by: number | null
  holidays_count?: number
  holidays?: BusinessCalendarHoliday[]
  created_at?: string | null
  updated_at?: string | null
}

export type BusinessCalendarPayload = {
  name: string
  timezone?: string
  week_start?: number
  working_days?: number[]
  work_start?: string
  work_end?: string
  is_default?: boolean
  is_active?: boolean
}

export type PayTabsConfigStatus = {
  configured?: boolean
  enabled?: boolean
  environment?: string | null
  base_host?: string | null
  profile_id_hint?: string | null
  has_server_key?: boolean
  callback_url_ok?: boolean
  return_url_ok?: boolean
  issues?: string[]
}

export type PayTabsCallbackMetrics = {
  callback_received?: number
  verified?: number
  rejected?: number
  mismatch?: number
  duplicate?: number
}

export type OperationsSettings = {
  business_calendar: BusinessCalendar | null
  printing_approaching_days: number
  webhook_count?: number
  automation_failure_24h?: number
  unified_work_driver?: string | null
  quiet_hours_note?: string | null
  paytabs_available?: boolean
  paytabs_config?: PayTabsConfigStatus | null
  paytabs_callback_metrics?: PayTabsCallbackMetrics | null
  paytabs_docs_path?: string | null
  [key: string]: unknown
}

export type OperationsSettingsPayload = {
  printing_approaching_days?: number
  default_business_calendar_id?: number | null
  business_calendar_id?: number | null
}

export type CommandCenterSystemHealth = {
  failed_automations_24h?: number
  failed_webhooks_24h?: number
  delayed_notifications?: number
  [key: string]: number | string | null | undefined
}

export type TaskCalendarLinkResult = {
  task: Record<string, unknown>
  calendar_item: CalendarItem
}

export type LinkTaskToCalendarPayload = {
  starts_at: string
  ends_at?: string | null
  all_day?: boolean
  reminders?: string[]
}

export type OperationsExportEntity = 'work' | 'printing' | 'sla'

export type OperationalSavedView = {
  id: number
  user_id: number
  name: string
  view_type: string
  filters: Record<string, unknown>
  sort: Record<string, unknown> | null
  is_pinned: boolean
  is_shared: boolean
  created_at?: string | null
  updated_at?: string | null
}

export type OperationalSavedViewPayload = {
  name: string
  view_type?: string
  filters: Record<string, unknown>
  sort?: Record<string, unknown> | null
  is_pinned?: boolean
  is_shared?: boolean
}

export type OperationalSlaRule = {
  id: number
  name: string
  module: string
  event_type: string
  target_minutes: number
  department_id: number | null
  department: { id: number; name: string } | null
  is_active: boolean
  created_by: number | null
  creator: { id: number; name: string } | null
  created_at?: string | null
}

export type OperationalSlaRulePayload = {
  name: string
  module: string
  event_type: string
  target_minutes: number
  department_id?: number | null
  is_active?: boolean
}

export type CommandCenterData = {
  summary: {
    today_tasks: number
    overdue: number
    today_meetings: number
    upcoming_deliveries: number
    projects_need_attention: number
    open_work?: number
    in_progress_work?: number
    sla_breached?: number
  }
  attention: AttentionItem[]
  today_timeline: CalendarItem[]
  workload_snapshot: Array<{
    id?: number | null
    name?: string
    user_id?: number
    count: number
    tasks?: number
    overdue?: number
    urgent?: number
  }>
  project_health: Array<{
    id: number
    title: string
    health: ProjectHealth
    deadline: string | null
  }>
  focus?: UnifiedWorkItem[]
  system_health?: CommandCenterSystemHealth | null
  revenue_ops?: {
    quotations_draft?: number
    quotations_sent?: number
    quotations_accepted?: number
    quotations_pending_payment?: number
    payments_recorded?: number
    amount_collected?: number | string
    amount_outstanding?: number | string
    currency?: string
    [key: string]: unknown
  } | null
}

export type MyDayData = {
  date: string
  items: Array<UnifiedWorkItem | CalendarItem>
  work_items?: UnifiedWorkItem[]
  overdue: Array<UnifiedWorkItem | CalendarItem>
  upcoming: CalendarItem[]
  counts: {
    today: number
    overdue: number
    upcoming: number
    meetings_today: number
    work_today_overdue?: number
  }
}

export type NotificationPreferences = {
  calendar_assignments: boolean
  task_reminders: boolean
  overdue_alerts: boolean
  mentions: boolean
  automation_notifications: boolean
  project_alerts: boolean
  daily_digest: boolean
  approval_requests: boolean
  printing_alerts: boolean
  crm_alerts: boolean
  quiet_hours_enabled: boolean
  quiet_hours_start: string | null
  quiet_hours_end: string | null
  quiet_hours_timezone: string | null
}

export type OperationsSearchResult = {
  calendar: Array<{
    id: number
    title: string
    type: string
    starts_at: string | null
    status: string
  }>
  projects: Array<{
    id: number
    title: string
    status: string
    deadline: string | null
  }>
  crm_leads: Array<{
    id: number
    title: string
    email: string | null
    status: string
  }>
  orders: Array<{
    id: number
    title: string
    reference: string | null
    status: string
  }>
}

export type OperationsInsights = {
  period_days?: number | null
  from: string
  to: string
  completed_work?: number
  tasks_completed: number
  workspace_tasks_completed?: number
  tasks_overdue: number
  workspace_tasks_overdue?: number
  overdue_rate?: number
  tasks_total: number
  work_total?: number
  by_department: Array<{
    department_id: number
    name: string
    completed: number
    overdue: number
    total: number
  }>
  by_employee?: Array<{
    user_id: number
    name: string
    completed: number
    overdue: number
    total: number
  }>
  without_department: number
  project_health?: {
    on_track: number
    needs_attention: number
    overdue: number
  }
  printing_lateness?: number
  printing?: {
    quotations_sent?: number
    quotations_accepted?: number
    quotations_rejected?: number
    payments_count?: number
    revenue?: number | string
    outstanding?: number | string
    currency?: string
    [key: string]: unknown
  } | null
  automation?: { success: number; failed: number }
  sla?: {
    on_time: number
    approaching: number
    breached: number
    unknown: number
    rules: number
  }
}

export type TeamDashboardData = {
  department_id: number | null
  employees: number[] | null
  today: { count: number; items: UnifiedWorkItem[] }
  overdue: { count: number; items: UnifiedWorkItem[] }
  upcoming: { count: number; items: UnifiedWorkItem[] }
  workload: {
    from: string
    to: string
    by_assignee: Array<{ user_id: number; count: number; overdue: number; urgent: number }>
    totals: { items: number; overdue: number }
  }
}

export type AttentionSnoozePreset = '1h' | 'today' | 'tomorrow'

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

export function isUnifiedWorkItem(item: UnifiedWorkItem | CalendarItem): item is UnifiedWorkItem {
  return typeof (item as UnifiedWorkItem).source_type === 'string' && typeof (item as UnifiedWorkItem).capabilities === 'object'
}

export function getDepartments(activeOnly = false) {
  return apiGet<{ items: Department[] }>(
    `/api/operations/departments${queryString({ active_only: activeOnly ? '1' : undefined })}`,
  )
}

export function getDepartmentOptions() {
  return apiGet<{ items: DepartmentOption[] }>('/api/operations/departments/options')
}

export function getDepartment(id: number) {
  return apiGet<Department>(`/api/operations/departments/${id}`)
}

export function createDepartment(payload: DepartmentPayload) {
  return apiPost<Department>('/api/operations/departments', payload)
}

export function updateDepartment(id: number, payload: Partial<DepartmentPayload>) {
  return apiPut<Department>(`/api/operations/departments/${id}`, payload)
}

export function deleteDepartment(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/operations/departments/${id}`)
}

export function assignEmployeeToDepartment(userId: number, departmentId: number | null) {
  return apiPost<{
    id: number
    name: string
    department_id: number | null
    department: { id: number; name: string } | null
  }>('/api/operations/departments/assign-employee', {
    user_id: userId,
    department_id: departmentId,
  })
}

export function getOperationsProjects(page = 1) {
  return apiGet<{
    items: OperationsProjectListItem[]
    meta: { current_page: number; last_page: number; per_page: number; total: number }
  }>(`/api/operations/projects${queryString({ page })}`)
}

export function getProjectWorkspace(projectId: number | string) {
  return apiGet<ProjectWorkspace>(`/api/operations/projects/${projectId}/workspace`)
}

export function syncProjectMembers(
  projectId: number | string,
  members: Array<{ user_id: number; role?: string }>,
) {
  return apiPut<{ members: ProjectWorkspaceMember[] }>(`/api/operations/projects/${projectId}/members`, {
    members,
  })
}

export function getProjectCalendarItems(projectId: number | string, from: string, to: string) {
  return apiGet<{ items: CalendarItem[] }>(
    `/api/operations/projects/${projectId}/calendar-items${queryString({ from, to })}`,
  )
}

export function getProjectTimeline(projectId: number | string, filter?: string) {
  return apiGet<{ items: ProjectTimelineEvent[] }>(
    `/api/operations/projects/${projectId}/timeline${queryString({ filter })}`,
  )
}

export function getProjectStructure(projectId: number | string) {
  return apiGet<ProjectStructure>(`/api/operations/projects/${projectId}/structure`)
}

export type ProjectWorkspaceTask = {
  id: number
  project_id: number
  phase_id?: number | null
  milestone_id?: number | null
  title: string
  description: string | null
  priority: string
  status: string
  deadline: string | null
  start_at?: string | null
  due_at?: string | null
  calendar_item_id?: number | null
  is_client_visible?: boolean
  is_overdue?: boolean
  assigned_to: number
  assignee?: { id: number; name: string; role?: string | null } | null
  created_at?: string | null
}

export type ProjectWorkspaceTaskPayload = {
  title: string
  description?: string | null
  assigned_to: number
  priority: string
  status?: string
  deadline?: string | null
  start_at?: string | null
  due_at?: string | null
  phase_id?: number | null
  milestone_id?: number | null
  is_client_visible?: boolean
  link_to_calendar?: boolean
}

export function getProjectWorkspaceTasks(projectId: number | string, page = 1) {
  return apiGet<{
    items: ProjectWorkspaceTask[]
    meta: { current_page: number; last_page: number; per_page: number; total: number }
  }>(`/api/operations/projects/${projectId}/tasks${queryString({ page })}`)
}

export function createProjectWorkspaceTask(
  projectId: number | string,
  payload: ProjectWorkspaceTaskPayload,
) {
  return apiPost<{ task: ProjectWorkspaceTask; calendar_item_id: number | null }>(
    `/api/operations/projects/${projectId}/tasks`,
    payload,
  )
}

export function updateProjectWorkspaceTask(
  projectId: number | string,
  taskId: number,
  payload: ProjectWorkspaceTaskPayload & { status: string },
) {
  return apiPut<{ task: ProjectWorkspaceTask; calendar_item_id: number | null }>(
    `/api/operations/projects/${projectId}/tasks/${taskId}`,
    payload,
  )
}

export function linkProjectWorkspaceTaskCalendar(
  projectId: number | string,
  taskId: number,
  payload?: { starts_at?: string | null; ends_at?: string | null; all_day?: boolean },
) {
  return apiPost<{ task: ProjectWorkspaceTask; calendar_item_id: number | null }>(
    `/api/operations/projects/${projectId}/tasks/${taskId}/link-calendar`,
    payload ?? {},
  )
}

export function updateProjectBrief(projectId: number | string, payload: ProjectBriefPayload) {
  return apiPut<ProjectBriefPayload>(`/api/operations/projects/${projectId}/brief`, payload)
}

export function getProjectReferences(projectId: number | string) {
  return apiGet<{ items: ProjectReference[] }>(`/api/operations/projects/${projectId}/references`)
}

export function createProjectReference(projectId: number | string, payload: ProjectReferencePayload) {
  return apiPost<ProjectReference>(`/api/operations/projects/${projectId}/references`, payload)
}

export function updateProjectReference(
  projectId: number | string,
  referenceId: number,
  payload: Partial<ProjectReferencePayload>,
) {
  return apiPut<ProjectReference>(
    `/api/operations/projects/${projectId}/references/${referenceId}`,
    payload,
  )
}

export function deleteProjectReference(projectId: number | string, referenceId: number) {
  return apiDelete<{ deleted: boolean }>(
    `/api/operations/projects/${projectId}/references/${referenceId}`,
  )
}

export function getProjectPhases(projectId: number | string) {
  return apiGet<{ items: ProjectPhase[] }>(`/api/operations/projects/${projectId}/phases`)
}

export function createProjectPhase(projectId: number | string, payload: ProjectPhasePayload) {
  return apiPost<ProjectPhase>(`/api/operations/projects/${projectId}/phases`, payload)
}

export function updateProjectPhase(
  projectId: number | string,
  phaseId: number,
  payload: Partial<ProjectPhasePayload>,
) {
  return apiPut<ProjectPhase>(`/api/operations/projects/${projectId}/phases/${phaseId}`, payload)
}

export function deleteProjectPhase(projectId: number | string, phaseId: number) {
  return apiDelete<{ deleted: boolean }>(`/api/operations/projects/${projectId}/phases/${phaseId}`)
}

export function getProjectMilestones(projectId: number | string) {
  return apiGet<{ items: ProjectMilestone[] }>(`/api/operations/projects/${projectId}/milestones`)
}

export function createProjectMilestone(projectId: number | string, payload: ProjectMilestonePayload) {
  return apiPost<ProjectMilestone>(`/api/operations/projects/${projectId}/milestones`, payload)
}

export function updateProjectMilestone(
  projectId: number | string,
  milestoneId: number,
  payload: Partial<ProjectMilestonePayload>,
) {
  return apiPut<ProjectMilestone>(`/api/operations/projects/${projectId}/milestones/${milestoneId}`, payload)
}

export function deleteProjectMilestone(projectId: number | string, milestoneId: number) {
  return apiDelete<{ deleted: boolean }>(`/api/operations/projects/${projectId}/milestones/${milestoneId}`)
}

export function getAutomations() {
  return apiGet<{ items: WorkflowAutomation[] }>('/api/operations/automations')
}

export function getAutomation(id: number) {
  return apiGet<WorkflowAutomation>(`/api/operations/automations/${id}`)
}

export function createAutomation(payload: WorkflowAutomationPayload) {
  return apiPost<WorkflowAutomation>('/api/operations/automations', payload)
}

export function updateAutomation(id: number, payload: Partial<WorkflowAutomationPayload>) {
  return apiPut<WorkflowAutomation>(`/api/operations/automations/${id}`, payload)
}

export function deleteAutomation(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/operations/automations/${id}`)
}

export function activateAutomation(id: number) {
  return apiPost<WorkflowAutomation>(`/api/operations/automations/${id}/activate`, {})
}

export function deactivateAutomation(id: number) {
  return apiPost<WorkflowAutomation>(`/api/operations/automations/${id}/deactivate`, {})
}

export function dryRunAutomation(id: number, context?: Record<string, unknown>) {
  return apiPost<AutomationDryRunResult>(`/api/operations/automations/${id}/dry-run`, {
    context: context ?? {},
  })
}

export function getAutomationRuns(id: number) {
  return apiGet<{ items: WorkflowAutomationRun[] }>(`/api/operations/automations/${id}/runs`)
}

export function seedAutomationTemplates() {
  return apiPost<{ seeded: boolean }>('/api/operations/automations/templates/seed', {})
}

export function getPrintingOperations(filters?: {
  category?: PrintingOpsCategory
  approaching_days?: number
  q?: string
  status?: string
}) {
  return apiGet<{
    items: PrintingOpsItem[]
    summary: PrintingSummaryCounts
    approaching_days: number
  }>(
    `/api/operations/printing${queryString({
      category: filters?.category,
      approaching_days: filters?.approaching_days,
      q: filters?.q,
      status: filters?.status,
    })}`,
  )
}

export function getPrintingSummary(approachingDays = 2) {
  return apiGet<{ summary: PrintingSummaryCounts; approaching_days: number }>(
    `/api/operations/printing/summary${queryString({ approaching_days: approachingDays })}`,
  )
}

export function getPrintingBoard(approachingDays = 2) {
  return apiGet<PrintingBoardResponse>(
    `/api/operations/printing/board${queryString({ approaching_days: approachingDays })}`,
  )
}

export function updatePrintingStatus(id: number, status: string, note?: string | null) {
  return apiPost<PrintingOpsItem>(`/api/operations/printing/${id}/status`, {
    status,
    note: note ?? null,
  })
}

export function assignPrintingRequest(id: number, payload: PrintingAssignPayload) {
  return apiPost<PrintingOpsItem>(`/api/operations/printing/${id}/assign`, payload)
}

export function getPrintingHistory(id: number) {
  return apiGet<{ items: PrintingStatusHistoryItem[] }>(`/api/operations/printing/${id}/history`)
}

export function getWork(filters: UnifiedWorkFilters = {}) {
  return apiGet<UnifiedWorkListResponse>(`/api/operations/work${queryString(filters)}`)
}

export function getWorkItem(id: string) {
  return apiGet<UnifiedWorkItem>(`/api/operations/work/${encodeURIComponent(id)}`)
}

export function completeWork(id: string) {
  return apiPost<UnifiedWorkItem>(`/api/operations/work/${encodeURIComponent(id)}/complete`, {})
}

export function assignWork(id: string, assigneeIds: number[]) {
  return apiPost<UnifiedWorkItem>(`/api/operations/work/${encodeURIComponent(id)}/assign`, {
    assignee_ids: assigneeIds,
  })
}

export function startWork(id: string) {
  return apiPost<UnifiedWorkItem>(`/api/operations/work/${encodeURIComponent(id)}/start`, {})
}

export function rescheduleWork(id: string, payload: { due_at?: string | null; starts_at?: string | null }) {
  return apiPost<UnifiedWorkItem>(`/api/operations/work/${encodeURIComponent(id)}/reschedule`, payload)
}

export function setWorkPriority(id: string, priority: string) {
  return apiPost<UnifiedWorkItem>(`/api/operations/work/${encodeURIComponent(id)}/priority`, { priority })
}

export function setWorkStatus(id: string, status: string) {
  return apiPost<UnifiedWorkItem>(`/api/operations/work/${encodeURIComponent(id)}/status`, { status })
}

export function getWorkFocus(limit = 5) {
  return apiGet<{ items: UnifiedWorkItem[] }>(`/api/operations/work/focus${queryString({ limit })}`)
}

export function getWorkKanban(filters: UnifiedWorkFilters = {}) {
  return apiGet<UnifiedWorkKanban>(`/api/operations/work/kanban${queryString(filters)}`)
}

export function linkTaskToCalendar(taskId: number, payload: LinkTaskToCalendarPayload) {
  return apiPost<TaskCalendarLinkResult>(`/api/operations/work/task/${taskId}/link-calendar`, payload)
}

export function linkCalendarToTask(calendarItemId: number, projectId?: number | null) {
  return apiPost<TaskCalendarLinkResult>(`/api/operations/work/calendar/${calendarItemId}/link-task`, {
    project_id: projectId ?? null,
  })
}

export function unlinkTaskCalendar(taskId: number) {
  return apiDelete<{ task_id: number; unlinked: boolean }>(`/api/operations/work/links/${taskId}`)
}

export function getWebhooks() {
  return apiGet<{ items: OutboundWebhook[] }>('/api/operations/webhooks')
}

export function getWebhook(id: number) {
  return apiGet<OutboundWebhook>(`/api/operations/webhooks/${id}`)
}

export function createWebhook(payload: OutboundWebhookPayload) {
  return apiPost<OutboundWebhook>('/api/operations/webhooks', payload)
}

export function updateWebhook(id: number, payload: Partial<OutboundWebhookPayload>) {
  return apiPut<OutboundWebhook>(`/api/operations/webhooks/${id}`, payload)
}

export function deleteWebhook(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/operations/webhooks/${id}`)
}

export function testWebhook(id: number) {
  return apiPost<{ delivery: WebhookDelivery }>(`/api/operations/webhooks/${id}/test`, {})
}

export function getWebhookDeliveries(id: number) {
  return apiGet<{ items: WebhookDelivery[] }>(`/api/operations/webhooks/${id}/deliveries`)
}

export function getBusinessCalendars() {
  return apiGet<{ items: BusinessCalendar[] }>('/api/operations/business-calendars')
}

export function getBusinessCalendar(id: number) {
  return apiGet<BusinessCalendar>(`/api/operations/business-calendars/${id}`)
}

export function createBusinessCalendar(payload: BusinessCalendarPayload) {
  return apiPost<BusinessCalendar>('/api/operations/business-calendars', payload)
}

export function updateBusinessCalendar(id: number, payload: Partial<BusinessCalendarPayload>) {
  return apiPut<BusinessCalendar>(`/api/operations/business-calendars/${id}`, payload)
}

export function deleteBusinessCalendar(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/operations/business-calendars/${id}`)
}

export function ensureDefaultBusinessCalendar() {
  return apiPost<BusinessCalendar>('/api/operations/business-calendars/ensure-default', {})
}

export function addBusinessCalendarHoliday(calendarId: number, payload: { date: string; name: string }) {
  return apiPost<BusinessCalendarHoliday>(`/api/operations/business-calendars/${calendarId}/holidays`, payload)
}

export function deleteBusinessCalendarHoliday(calendarId: number, holidayId: number) {
  return apiDelete<{ deleted: boolean }>(
    `/api/operations/business-calendars/${calendarId}/holidays/${holidayId}`,
  )
}

export type HolidayImportResult = {
  preview?: boolean
  imported?: number
  skipped?: number
  invalid?: Array<{ line?: number; date?: string; reason?: string }>
  valid?: Array<{ date: string; name: string }>
  [key: string]: unknown
}

export function importBusinessCalendarHolidays(
  calendarId: number,
  payload: { csv: string; preview?: boolean },
) {
  return apiPost<HolidayImportResult>(
    `/api/operations/business-calendars/${calendarId}/holidays/import`,
    payload,
  )
}

export function copyBusinessCalendarHolidaysYear(
  calendarId: number,
  payload: { from_year: number; to_year: number },
) {
  return apiPost<{ copied: number }>(
    `/api/operations/business-calendars/${calendarId}/holidays/copy-year`,
    payload,
  )
}

export function getOperationsSettings() {
  return apiGet<OperationsSettings>('/api/operations/settings')
}

export function updateOperationsSettings(payload: OperationsSettingsPayload) {
  return apiPut<OperationsSettings>('/api/operations/settings', payload)
}

export async function exportOperationsCsv(
  entity: OperationsExportEntity,
  filters: Record<string, string | number | boolean | undefined | null> = {},
) {
  await apiDownload(`/api/operations/export/${entity}.csv${queryString(filters)}`, `${entity}.csv`)
}

export function getSavedViews(viewType?: string) {
  return apiGet<{ items: OperationalSavedView[] }>(
    `/api/operations/saved-views${queryString({ view_type: viewType })}`,
  )
}

export function getSavedView(id: number) {
  return apiGet<OperationalSavedView>(`/api/operations/saved-views/${id}`)
}

export function createSavedView(payload: OperationalSavedViewPayload) {
  return apiPost<OperationalSavedView>('/api/operations/saved-views', payload)
}

export function updateSavedView(id: number, payload: Partial<OperationalSavedViewPayload>) {
  return apiPut<OperationalSavedView>(`/api/operations/saved-views/${id}`, payload)
}

export function deleteSavedView(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/operations/saved-views/${id}`)
}

export function pinSavedView(id: number, isPinned = true) {
  return apiPost<OperationalSavedView>(`/api/operations/saved-views/${id}/pin`, {
    is_pinned: isPinned,
  })
}

export function getSlaRules() {
  return apiGet<{ items: OperationalSlaRule[] }>('/api/operations/sla-rules')
}

export function createSlaRule(payload: OperationalSlaRulePayload) {
  return apiPost<OperationalSlaRule>('/api/operations/sla-rules', payload)
}

export function updateSlaRule(id: number, payload: Partial<OperationalSlaRulePayload>) {
  return apiPut<OperationalSlaRule>(`/api/operations/sla-rules/${id}`, payload)
}

export function deleteSlaRule(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/operations/sla-rules/${id}`)
}

export function snoozeAttention(key: string, options: { preset?: AttentionSnoozePreset; until?: string }) {
  return apiPost<{ snoozed: boolean; until?: string }>('/api/operations/attention/snooze', {
    attention_key: key,
    preset: options.preset,
    until: options.until,
  })
}

export function getTeamDashboard(departmentId?: number) {
  return apiGet<TeamDashboardData>(
    `/api/operations/team-dashboard${queryString({ department_id: departmentId })}`,
  )
}

export function getCommandCenter() {
  return apiGet<CommandCenterData>('/api/operations/command-center')
}

export function getMyDay() {
  return apiGet<MyDayData>('/api/operations/my-day')
}

export function getApprovalsInbox(status?: string) {
  return apiGet<{ items: ApprovalRequest[] }>(
    `/api/operations/approvals/inbox${queryString({ status })}`,
  )
}

export function getMyApprovals() {
  return apiGet<{ items: ApprovalRequest[] }>('/api/operations/approvals/mine')
}

export function createApproval(payload: ApprovalCreatePayload) {
  return apiPost<ApprovalRequest>('/api/operations/approvals', payload)
}

export function approveRequest(id: number, decisionNotes?: string | null) {
  return apiPost<ApprovalRequest>(`/api/operations/approvals/${id}/approve`, {
    decision_notes: decisionNotes ?? null,
  })
}

export function rejectRequest(id: number, decisionNotes?: string | null) {
  return apiPost<ApprovalRequest>(`/api/operations/approvals/${id}/reject`, {
    decision_notes: decisionNotes ?? null,
  })
}

export function getNotificationPreferences() {
  return apiGet<NotificationPreferences>('/api/operations/notification-preferences')
}

export function updateNotificationPreferences(payload: Partial<NotificationPreferences>) {
  return apiPut<NotificationPreferences>('/api/operations/notification-preferences', payload)
}

export function searchOperations(q: string) {
  return apiGet<OperationsSearchResult>(`/api/operations/search${queryString({ q })}`)
}

export function getOperationsInsights(fromOrPeriod: string | 7 | 30 | 90, to?: string) {
  if (typeof fromOrPeriod === 'number') {
    return apiGet<OperationsInsights>(`/api/operations/insights${queryString({ period: fromOrPeriod })}`)
  }

  return apiGet<OperationsInsights>(`/api/operations/insights${queryString({ from: fromOrPeriod, to })}`)
}
