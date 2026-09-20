export type AttentionKind = 'overdue' | 'due_soon' | 'unassigned' | 'waiting'

export type ProjectAttentionItem = {
  kind: AttentionKind | string
  related_type: 'task' | 'milestone' | string
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
  source: 'service_line' | 'reference' | string
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
  closure?: ProjectClosureReadiness
}

const ATTENTION_KIND_LABELS: Record<string, string> = {
  overdue: 'متأخر',
  due_soon: 'قريب',
  unassigned: 'بلا مسؤول',
  waiting: 'بانتظار',
}

export function attentionKindLabel(kind: string): string {
  return ATTENTION_KIND_LABELS[kind] ?? kind
}

export function groupAttentionItems(items: ProjectAttentionItem[]): Record<AttentionKind, ProjectAttentionItem[]> {
  const groups: Record<AttentionKind, ProjectAttentionItem[]> = {
    overdue: [],
    due_soon: [],
    unassigned: [],
    waiting: [],
  }

  for (const item of items) {
    if (item.kind === 'overdue' || item.kind === 'due_soon' || item.kind === 'unassigned' || item.kind === 'waiting') {
      groups[item.kind].push(item)
    }
  }

  return groups
}

export function isClosureReady(closure: ProjectClosureReadiness | null | undefined): boolean {
  return closure?.state === 'ready'
}

export function milestoneOpenTaskCount(milestone: {
  open_tasks?: number
  progress?: { total: number; completed: number }
}): number {
  if (typeof milestone.open_tasks === 'number') {
    return Math.max(0, milestone.open_tasks)
  }
  const total = milestone.progress?.total ?? 0
  const completed = milestone.progress?.completed ?? 0
  return Math.max(0, total - completed)
}

export function milestoneCompletionWarning(openTasks: number): string | null {
  if (openTasks <= 0) return null
  return `هناك ${openTasks.toLocaleString('ar-SA')} مهام ما زالت مفتوحة لهذا المعلم.`
}

/** Customer payloads must never include these internal keys. */
export const CUSTOMER_FORBIDDEN_KEYS = [
  'team_stats',
  'members',
  'health',
  'risks',
  'unified_work',
  'attention',
  'execution_summary',
  'closure_readiness',
  'recent_activity',
  'timeline_preview',
  'pending_approvals',
] as const

export function customerPayloadHasInternalKeys(payload: Record<string, unknown>): string[] {
  return CUSTOMER_FORBIDDEN_KEYS.filter((key) => key in payload)
}
