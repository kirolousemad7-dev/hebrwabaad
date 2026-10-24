import { Link, useParams } from 'react-router-dom'
import { useEffect, useMemo, useState } from 'react'
import { FileLibrary } from '../../components/files/FileLibrary'
import {
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { ProjectAttentionPanel } from '../../components/owner/project-workspace/ProjectAttentionPanel'
import { ProjectClosureReadiness } from '../../components/owner/project-workspace/ProjectClosureReadiness'
import { ProjectDeliverables } from '../../components/owner/project-workspace/ProjectDeliverables'
import { ProjectExecutionSummaryPanel } from '../../components/owner/project-workspace/ProjectExecutionSummary'
import { ProjectRecentActivity } from '../../components/owner/project-workspace/ProjectRecentActivity'
import { ProjectNotesThread } from '../../components/owner/project-workspace/ProjectNotesThread'
import { ProjectTaskBoard } from '../../components/owner/project-workspace/ProjectTaskBoard'
import { ProjectTaskQuickEdit } from '../../components/owner/project-workspace/ProjectTaskQuickEdit'
import { ProjectTimelineGantt } from '../../components/owner/project-workspace/ProjectTimelineGantt'
import { ProjectWorkspaceHeader } from '../../components/owner/project-workspace/ProjectWorkspaceHeader'
import {
  milestoneCompletionWarning,
  milestoneOpenTaskCount,
} from '../../utils/projectExecution'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { getCalendarAssignees, type CalendarAssignee, type CalendarItem } from '../../services/calendar'
import {
  createProjectMilestone,
  createProjectPhase,
  createProjectReference,
  createProjectWorkspaceTask,
  deleteProjectMilestone,
  deleteProjectPhase,
  deleteProjectReference,
  getProjectCalendarItems,
  getProjectMilestones,
  getProjectWorkspace,
  getProjectWorkspaceTasks,
  linkProjectWorkspaceTaskCalendar,
  syncProjectMembers,
  syncTaskReferences,
  updateProjectBrief,
  updateProjectMilestone,
  updateProjectPhase,
  updateProjectWorkspaceTask,
  type ProjectMilestone,
  type ProjectPhase,
  type ProjectReference,
  type ProjectStructure,
  type ProjectWorkspace,
  type ProjectWorkspaceTask,
} from '../../services/operations'
import { getProjectActivities } from '../../services/projectActivities'
import type { ProjectActivity } from '../../types/api'
import { formatDateTimeShort, formatTimeShort } from '../../utils/calendarDates'
import { calendarStatusLabel, calendarTypeLabel } from '../../utils/calendarLabels'
import { describeApiError } from '../../utils/errors'
import {
  projectActivityHeadline,
  projectActivityMatchesFilter,
} from '../../utils/projectActivityLabels'
import { PROJECT_STATUS_LABELS, formatProjectDate, projectInitials } from '../../utils/workspaceProjects'

type TabKey =
  | 'overview'
  | 'client'
  | 'brief'
  | 'references'
  | 'timeline'
  | 'tasks'
  | 'calendar'
  | 'milestones'
  | 'team'
  | 'files'
  | 'notes'
  | 'activity'

const TABS: Array<{ key: TabKey; label: string }> = [
  { key: 'overview', label: 'نظرة عامة' },
  { key: 'client', label: 'بيانات العميل' },
  { key: 'brief', label: 'البريف' },
  { key: 'references', label: 'الريفرنسات' },
  { key: 'tasks', label: 'المهام' },
  { key: 'files', label: 'الملفات والمرفقات' },
  { key: 'team', label: 'الفريق' },
  { key: 'notes', label: 'الملاحظات' },
  { key: 'activity', label: 'النشاط' },
  { key: 'timeline', label: 'الخط الزمني' },
  { key: 'calendar', label: 'التقويم' },
  { key: 'milestones', label: 'المعالم' },
]

const BRIEF_FIELDS: Array<{ key: string; label: string }> = [
  { key: 'client_request', label: 'طلب العميل' },
  { key: 'target_audience', label: 'الجمهور المستهدف' },
  { key: 'products_services', label: 'المنتجات / الخدمات' },
  { key: 'market', label: 'السوق' },
  { key: 'competitors', label: 'المنافسون' },
  { key: 'usp', label: 'الميزة التنافسية' },
  { key: 'positioning', label: 'تموضع العلامة' },
  { key: 'brand_description', label: 'وصف العلامة' },
  { key: 'personality', label: 'شخصية العلامة' },
  { key: 'tone', label: 'نبرة الصوت' },
  { key: 'colors', label: 'ألوان العلامة' },
  { key: 'fonts', label: 'الخطوط' },
  { key: 'likes', label: 'ما يعجب العميل' },
  { key: 'dislikes', label: 'ما لا يعجب العميل' },
  { key: 'platforms', label: 'المنصات' },
  { key: 'content_goals', label: 'أهداف المحتوى' },
  { key: 'deliverables', label: 'المخرجات المطلوبة' },
  { key: 'preferred_style', label: 'الأسلوب المفضل' },
  { key: 'formats', label: 'الصيغ والأبعاد' },
  { key: 'sizes', label: 'المقاسات' },
  { key: 'quantities', label: 'الكميات' },
  { key: 'materials', label: 'الخامات' },
  { key: 'finishing', label: 'التشطيب' },
  { key: 'delivery', label: 'متطلبات التسليم' },
]

const PROFILE_FIELDS: Array<{ key: string; label: string }> = [
  { key: 'phone', label: 'الهاتف' },
  { key: 'whatsapp', label: 'واتساب' },
  { key: 'website', label: 'الموقع' },
  { key: 'city', label: 'المدينة / الدولة' },
  { key: 'social_links', label: 'حسابات التواصل' },
  { key: 'contact_person', label: 'جهة الاتصال' },
  { key: 'notes', label: 'ملاحظات العميل' },
]

const REFERENCE_TYPES = [
  { value: 'DESIGN', label: 'تصميم' },
  { value: 'BRANDING', label: 'هوية' },
  { value: 'CONTENT', label: 'محتوى' },
  { value: 'WEBSITE', label: 'موقع' },
  { value: 'VIDEO', label: 'فيديو' },
  { value: 'OTHER', label: 'أخرى' },
]

function textMap(source: Record<string, unknown> | null | undefined, keys: string[]): Record<string, string> {
  const next: Record<string, string> = {}
  for (const key of keys) {
    const value = source?.[key]
    next[key] = typeof value === 'string' ? value : ''
  }
  return next
}

function teamRoleLabel(role: string): string {
  if (role === 'account_manager') return 'مدير الحساب'
  if (role === 'manager') return 'مدير المشروع'
  if (role === 'assignee') return 'مكلّف بمهمة'
  if (role === 'member') return 'عضو'
  return role
}

const ACTIVITY_FILTERS = [
  { value: 'all', label: 'الكل' },
  { value: 'tasks', label: 'مهام' },
  { value: 'files', label: 'ملفات' },
  { value: 'team', label: 'فريق' },
  { value: 'approvals', label: 'موافقات' },
  { value: 'automation', label: 'أتمتة' },
] as const

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

function InfoCard({ title, rows }: { title: string; rows: Array<[string, string | null | undefined]> }) {
  const visible = rows.filter(([, value]) => value && value.trim() !== '')
  if (visible.length === 0) return null
  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-4">
      <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
      <dl className="mt-3 divide-y divide-slate-100">
        {visible.map(([label, value]) => (
          <div key={label} className="flex items-start justify-between gap-4 py-2 text-sm">
            <dt className="text-slate-500">{label}</dt>
            <dd className="max-w-[70%] text-end font-medium leading-6 text-slate-900">{value}</dd>
          </div>
        ))}
      </dl>
    </section>
  )
}

const BRIEF_GROUPS: Array<{ title: string; keys: string[] }> = [
  { title: 'نظرة عامة', keys: ['client_request'] },
  { title: 'الجمهور والسوق', keys: ['target_audience', 'market', 'competitors', 'usp', 'products_services', 'positioning'] },
  { title: 'البراند', keys: ['personality', 'tone', 'likes', 'dislikes', 'brand_description'] },
  { title: 'التسليمات', keys: ['deliverables', 'platforms', 'formats', 'content_goals'] },
  { title: 'التصميم', keys: ['preferred_style', 'colors', 'fonts'] },
  { title: 'الطباعة / الإنتاج', keys: ['sizes', 'quantities', 'materials', 'finishing', 'delivery'] },
]

function BriefDocument({
  objective,
  outcome,
  wants,
  doesNotWant,
  scopeIn,
  scopeOut,
  fields,
}: {
  objective: string
  outcome: string
  wants: string
  doesNotWant: string
  scopeIn: string
  scopeOut: string
  fields: Record<string, string>
}) {
  const extras: Record<string, Array<[string, string]>> = {
    'نظرة عامة': [
      ['هدف المشروع', objective],
      ['النتيجة المرغوبة', outcome],
      ['ضمن النطاق', scopeIn],
      ['خارج النطاق', scopeOut],
      ['ما يريده العميل', wants],
      ['ما لا يريده العميل', doesNotWant],
    ].filter((row): row is [string, string] => row[1].trim() !== ''),
  }

  const groups = BRIEF_GROUPS.map((group) => {
    const rows = [
      ...(extras[group.title] ?? []),
      ...group.keys
        .map((key) => {
          const label = BRIEF_FIELDS.find((field) => field.key === key)?.label ?? key
          return [label, fields[key] ?? ''] as [string, string]
        })
        .filter((row) => row[1].trim() !== ''),
    ]
    return { title: group.title, rows }
  }).filter((group) => group.rows.length > 0)

  if (groups.length === 0) {
    return <p className="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500">لا يوجد بريف بعد.</p>
  }

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      {groups.map((group) => (
        <section key={group.title} className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
          <h3 className="text-sm font-semibold text-slate-900">{group.title}</h3>
          <dl className="mt-3 space-y-3">
            {group.rows.map(([label, value]) => (
              <div key={label}>
                <dt className="text-xs text-slate-500">{label}</dt>
                <dd className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-800">{value}</dd>
              </div>
            ))}
          </dl>
        </section>
      ))}
    </div>
  )
}

function referenceHost(url: string): string {
  try {
    return new URL(url).host
  } catch {
    return url
  }
}

function rangeAroundToday() {
  const from = new Date()
  from.setDate(from.getDate() - 14)
  const to = new Date()
  to.setDate(to.getDate() + 45)
  return {
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10),
  }
}

function textValue(record: Record<string, unknown>, key: string): string {
  const value = record[key]
  if (typeof value === 'string') return value
  if (Array.isArray(value)) return value.map(String).join('\n')
  return ''
}

function ProgressBar({ percent }: { percent: number }) {
  const width = Math.min(100, Math.max(0, percent))
  return (
    <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" dir="ltr">
      <div className="ms-auto h-full rounded-full bg-brand-primary/80" style={{ width: `${width}%` }} />
    </div>
  )
}

function StructureTree({ structure }: { structure: ProjectStructure }) {
  if (structure.phases.length === 0 && structure.unassigned_tasks.length === 0) {
    return <p className="text-sm text-slate-500">لا مراحل أو مهام بعد. أضف مرحلة من تبويب الخط الزمني.</p>
  }

  return (
    <div className="space-y-4">
      {structure.phases.map((phase) => (
        <article key={phase.id} className="rounded-xl border border-slate-200 bg-white p-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <p className="text-xs text-slate-500">مرحلة</p>
              <h3 className="font-semibold text-slate-900">{phase.title}</h3>
            </div>
            <div className="text-left text-xs text-slate-500">
              <p>{phase.status}</p>
              <p>{Math.round(phase.progress?.percent ?? 0).toLocaleString('ar-SA')}%</p>
            </div>
          </div>
          <ProgressBar percent={phase.progress?.percent ?? 0} />
          <ul className="mt-3 space-y-2 border-r-2 border-slate-100 pr-3">
            {(phase.milestones ?? []).map((milestone) => (
              <li key={`m-${milestone.id}`} className="rounded-lg bg-[#f4f6f8] px-3 py-2 text-sm">
                <p className="font-medium text-slate-900">◆ {milestone.title}</p>
                <p className="text-xs text-slate-500">
                  معلم · {milestone.status}
                  {milestone.due_date ? ` · ${milestone.due_date}` : ''}
                </p>
                <ul className="mt-2 space-y-1">
                  {(milestone.tasks ?? []).map((task) => (
                    <li key={task.id} className="text-xs text-slate-700">
                      ▸ {task.title}
                      {task.deadline ? ` · ${task.deadline}` : ''}
                      {task.assignee ? ` · ${task.assignee.name}` : ''}
                    </li>
                  ))}
                </ul>
              </li>
            ))}
            {(phase.tasks ?? []).map((task) => (
              <li key={`t-${task.id}`} className="rounded-lg border border-slate-100 px-3 py-2 text-sm">
                ▸ {task.title}
                <span className="ms-2 text-xs text-slate-500">{task.status}</span>
              </li>
            ))}
          </ul>
        </article>
      ))}

      {structure.unassigned_tasks.length > 0 ? (
        <article className="rounded-xl border border-dashed border-slate-200 p-4">
          <h3 className="font-semibold text-slate-900">مهام بلا مرحلة</h3>
          <ul className="mt-2 space-y-1 text-sm">
            {structure.unassigned_tasks.map((task) => (
              <li key={task.id}>
                ▸ {task.title}
                <span className="ms-2 text-xs text-slate-500">{task.status}</span>
              </li>
            ))}
          </ul>
        </article>
      ) : null}
    </div>
  )
}

export function OwnerProjectWorkspacePage() {
  const { projectId } = useParams()
  const [tab, setTab] = useState<TabKey>('overview')
  const [workspace, setWorkspace] = useState<ProjectWorkspace | null>(null)
  const [calendarItems, setCalendarItems] = useState<CalendarItem[]>([])
  const [milestones, setMilestones] = useState<ProjectMilestone[]>([])
  const [activity, setActivity] = useState<ProjectActivity[]>([])
  const [recentActivities, setRecentActivities] = useState<ProjectActivity[]>([])
  const [activityFilter, setActivityFilter] = useState<string>('all')
  const [recentActivityLoading, setRecentActivityLoading] = useState(false)
  const [recentActivityError, setRecentActivityError] = useState<string | null>(null)
  const [activityLoading, setActivityLoading] = useState(false)
  const [assignees, setAssignees] = useState<CalendarAssignee[]>([])
  const [selectedMemberIds, setSelectedMemberIds] = useState<number[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [editingBrief, setEditingBrief] = useState(false)
  const [editingClient, setEditingClient] = useState(false)
  const [savingTeam, setSavingTeam] = useState(false)
  const [savingBrief, setSavingBrief] = useState(false)
  const [milestoneTitle, setMilestoneTitle] = useState('')
  const [milestoneDue, setMilestoneDue] = useState('')
  const [milestonePhaseId, setMilestonePhaseId] = useState('')
  const [milestoneClientVisible, setMilestoneClientVisible] = useState(false)
  const [savingMilestone, setSavingMilestone] = useState(false)
  const [phaseTitle, setPhaseTitle] = useState('')
  const [savingPhase, setSavingPhase] = useState(false)
  const [referenceTitle, setReferenceTitle] = useState('')
  const [referenceType, setReferenceType] = useState('DESIGN')
  const [referenceDescription, setReferenceDescription] = useState('')
  const [briefDraft, setBriefDraft] = useState<Record<string, string>>({})
  const [profileDraft, setProfileDraft] = useState<Record<string, string>>({})
  const [referenceUrl, setReferenceUrl] = useState('')
  const [savingReference, setSavingReference] = useState(false)
  const [briefObjective, setBriefObjective] = useState('')
  const [briefOutcome, setBriefOutcome] = useState('')
  const [companyName, setCompanyName] = useState('')
  const [industry, setIndustry] = useState('')
  const [wants, setWants] = useState('')
  const [doesNotWant, setDoesNotWant] = useState('')
  const [scopeIn, setScopeIn] = useState('')
  const [scopeOut, setScopeOut] = useState('')
  const [timelineMode, setTimelineMode] = useState<'tree' | 'gantt'>('tree')
  const [ganttScale, setGanttScale] = useState<'day' | 'week' | 'month'>('week')
  const [workspaceTasks, setWorkspaceTasks] = useState<ProjectWorkspaceTask[]>([])
  const [taskFilterUserId, setTaskFilterUserId] = useState<number | null>(null)
  const [expandedMilestoneId, setExpandedMilestoneId] = useState<number | null>(null)
  const [showTaskForm, setShowTaskForm] = useState(false)
  const [savingTask, setSavingTask] = useState(false)
  const [taskTitle, setTaskTitle] = useState('')
  const [taskDescription, setTaskDescription] = useState('')
  const [taskAssignee, setTaskAssignee] = useState('')
  const [taskPriority, setTaskPriority] = useState('MEDIUM')
  const [taskDeadline, setTaskDeadline] = useState('')
  const [taskStartAt, setTaskStartAt] = useState('')
  const [taskPhaseId, setTaskPhaseId] = useState('')
  const [taskMilestoneId, setTaskMilestoneId] = useState('')
  const [taskClientVisible, setTaskClientVisible] = useState(false)
  const [taskLinkCalendar, setTaskLinkCalendar] = useState(true)
  const [editingTask, setEditingTask] = useState<ProjectWorkspaceTask | null>(null)
  const [savingTaskEdit, setSavingTaskEdit] = useState(false)
  const [taskCreateError, setTaskCreateError] = useState<string | null>(null)
  const [tasksError, setTasksError] = useState<string | null>(null)
  const [milestonesError, setMilestonesError] = useState<string | null>(null)
  const [activityError, setActivityError] = useState<string | null>(null)

  const range = useMemo(() => rangeAroundToday(), [])

  function applyWorkspace(data: ProjectWorkspace) {
    setWorkspace(data)
    if (data.milestones) {
      setMilestones(data.milestones)
    }
    setSelectedMemberIds(data.members.map((member) => member.user_id))
    setBriefObjective(textValue(data.brief ?? {}, 'objective'))
    setBriefOutcome(textValue(data.brief ?? {}, 'desired_outcome'))
    setCompanyName(textValue(data.client_profile ?? {}, 'company_name'))
    setIndustry(textValue(data.client_profile ?? {}, 'industry'))
    setWants(textValue(data.requirements ?? {}, 'wants'))
    setDoesNotWant(textValue(data.requirements ?? {}, 'does_not_want'))
    setScopeIn(textValue(data.scope ?? {}, 'in_scope'))
    setScopeOut(textValue(data.scope ?? {}, 'out_of_scope'))
    setBriefDraft(textMap(data.brief, BRIEF_FIELDS.map((field) => field.key)))
    setProfileDraft(textMap(data.client_profile, PROFILE_FIELDS.map((field) => field.key)))
  }

  async function loadTasks() {
    if (!projectId) return
    try {
      const response = await getProjectWorkspaceTasks(projectId)
      setWorkspaceTasks(response.data.items ?? [])
      setTasksError(null)
    } catch (caught) {
      setTasksError(describeApiError(caught, 'تعذر تحميل المهام.'))
    }
  }

  async function load() {
    if (!projectId) {
      setLoading(false)
      setError('معرّف المشروع غير صالح.')
      setWorkspace(null)
      return
    }
    setLoading(true)
    setError(null)
    try {
      const [workspaceResponse, calendarResponse] = await Promise.all([
        getProjectWorkspace(projectId),
        getProjectCalendarItems(projectId, range.from, range.to),
      ])
      applyWorkspace(workspaceResponse.data)
      setCalendarItems(calendarResponse.data.items ?? [])
      setMilestonesError(null)
      if (!workspaceResponse.data.milestones) {
        try {
          const milestonesResponse = await getProjectMilestones(projectId)
          setMilestones(milestonesResponse.data.items ?? [])
        } catch (caught) {
          setMilestonesError(describeApiError(caught, 'تعذر تحميل المعالم.'))
        }
      }
      await loadTasks()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل مساحة المشروع.'))
      setWorkspace(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    void getCalendarAssignees()
      .then((response) => setAssignees(response.data.items ?? []))
      .catch(() => setAssignees([]))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [projectId])

  useEffect(() => {
    if (!projectId) return
    let cancelled = false
    setRecentActivityLoading(true)
    setRecentActivityError(null)
    void getProjectActivities(Number(projectId), 1, 12)
      .then((response) => {
        if (!cancelled) setRecentActivities(response.data.items ?? [])
      })
      .catch((caught) => {
        if (!cancelled) {
          setRecentActivities([])
          setRecentActivityError(describeApiError(caught, 'تعذر تحميل النشاط.'))
        }
      })
      .finally(() => {
        if (!cancelled) setRecentActivityLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [projectId])

  useEffect(() => {
    if (!projectId || tab !== 'activity') return
    let cancelled = false
    setActivityError(null)
    setActivityLoading(true)
    void getProjectActivities(Number(projectId), 1, 50)
      .then((response) => {
        if (!cancelled) setActivity(response.data.items ?? [])
      })
      .catch((caught) => {
        if (!cancelled) {
          setActivity([])
          setActivityError(describeApiError(caught, 'تعذر تحميل النشاط.'))
        }
      })
      .finally(() => {
        if (!cancelled) setActivityLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [projectId, tab])

  async function saveBrief() {
    if (!projectId || savingBrief) return
    setSavingBrief(true)
    setError(null)
    try {
      await updateProjectBrief(projectId, {
        brief: {
          ...(workspace?.brief ?? {}),
          ...Object.fromEntries(Object.entries(briefDraft).map(([key, value]) => [key, value.trim() || null])),
          objective: briefObjective.trim() || null,
          desired_outcome: briefOutcome.trim() || null,
        },
        client_profile: {
          ...(workspace?.client_profile ?? {}),
          ...Object.fromEntries(Object.entries(profileDraft).map(([key, value]) => [key, value.trim() || null])),
          company_name: companyName.trim() || null,
          industry: industry.trim() || null,
        },
        requirements: {
          ...(workspace?.requirements ?? {}),
          wants: wants
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean),
          does_not_want: doesNotWant
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean),
        },
        scope: {
          ...(workspace?.scope ?? {}),
          in_scope: scopeIn
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean),
          out_of_scope: scopeOut
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean),
        },
      })
      setNotice('تم حفظ الموجز والمتطلبات.')
      const refreshed = await getProjectWorkspace(projectId)
      applyWorkspace(refreshed.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الموجز.'))
    } finally {
      setSavingBrief(false)
    }
  }

  async function saveTask() {
    if (!projectId || !taskTitle.trim() || !taskAssignee || savingTask) return
    if (taskStartAt && taskDeadline && taskDeadline < taskStartAt) {
      setTaskCreateError('تاريخ الاستحقاق يجب أن يكون بعد البداية.')
      return
    }
    setSavingTask(true)
    setError(null)
    setTaskCreateError(null)
    try {
      await createProjectWorkspaceTask(projectId, {
        title: taskTitle.trim(),
        description: taskDescription.trim() || null,
        assigned_to: Number(taskAssignee),
        priority: taskPriority,
        deadline: taskDeadline || null,
        start_at: taskStartAt || null,
        due_at: taskDeadline || null,
        phase_id: taskPhaseId ? Number(taskPhaseId) : null,
        milestone_id: taskMilestoneId ? Number(taskMilestoneId) : null,
        is_client_visible: taskClientVisible,
        link_to_calendar: taskLinkCalendar && Boolean(taskStartAt || taskDeadline),
        status: 'TODO',
      })
      setTaskTitle('')
      setTaskDescription('')
      setTaskDeadline('')
      setTaskStartAt('')
      setTaskPhaseId('')
      setTaskMilestoneId('')
      setTaskClientVisible(false)
      setShowTaskForm(false)
      await refreshAfterTaskMutation({ includeCalendar: taskLinkCalendar })
      setNotice('تم إنشاء المهمة داخل المشروع.')
    } catch (caught) {
      setNotice(null)
      setError(describeApiError(caught, 'تعذر إنشاء المهمة.'))
    } finally {
      setSavingTask(false)
    }
  }

  async function refreshAfterTaskMutation(options?: { includeCalendar?: boolean }) {
    if (!projectId) return
    const requests: Array<Promise<unknown>> = [
      getProjectWorkspace(projectId).then((response) => {
        applyWorkspace(response.data)
      }),
      loadTasks(),
    ]
    if (options?.includeCalendar) {
      requests.push(
        getProjectCalendarItems(projectId, range.from, range.to).then((response) => {
          setCalendarItems(response.data.items ?? [])
        }),
      )
    }
    await Promise.all(requests)
  }

  async function saveTaskEdit(payload: {
    title: string
    description: string | null
    assigned_to: number
    priority: string
    status: string
    deadline: string | null
    start_at: string | null
    due_at: string | null
    phase_id: number | null
    milestone_id: number | null
    is_client_visible: boolean
    link_to_calendar: boolean
  }) {
    if (!projectId || !editingTask || savingTaskEdit) return
    if (payload.start_at && payload.due_at && payload.due_at < payload.start_at) {
      setError('تاريخ الاستحقاق يجب أن يكون بعد البداية.')
      return
    }
    setSavingTaskEdit(true)
    setError(null)
    try {
      await updateProjectWorkspaceTask(projectId, editingTask.id, payload)
      setEditingTask(null)
      await refreshAfterTaskMutation({ includeCalendar: payload.link_to_calendar })
      setNotice('تم تحديث المهمة.')
    } catch (caught) {
      setNotice(null)
      setError(describeApiError(caught, 'تعذر تحديث المهمة.'))
    } finally {
      setSavingTaskEdit(false)
    }
  }

  async function linkEditingTaskCalendar() {
    if (!projectId || !editingTask || savingTaskEdit) return
    setSavingTaskEdit(true)
    setError(null)
    try {
      await linkProjectWorkspaceTaskCalendar(projectId, editingTask.id, {
        starts_at: editingTask.start_at ?? editingTask.deadline,
        ends_at: editingTask.due_at ?? editingTask.deadline,
      })
      setEditingTask(null)
      await refreshAfterTaskMutation({ includeCalendar: true })
      setNotice('تم ربط المهمة بالتقويم.')
    } catch (caught) {
      setNotice(null)
      setError(describeApiError(caught, 'تعذر ربط المهمة بالتقويم.'))
    } finally {
      setSavingTaskEdit(false)
    }
  }

  async function savePhase() {
    if (!projectId || !phaseTitle.trim() || savingPhase) return
    setSavingPhase(true)
    setError(null)
    try {
      await createProjectPhase(projectId, {
        title: phaseTitle.trim(),
        status: 'PENDING',
        is_client_visible: true,
      })
      setPhaseTitle('')
      setNotice('تمت إضافة المرحلة.')
      const refreshed = await getProjectWorkspace(projectId)
      applyWorkspace(refreshed.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة المرحلة.'))
    } finally {
      setSavingPhase(false)
    }
  }

  async function markPhaseDone(phase: ProjectPhase) {
    if (!projectId) return
    try {
      await updateProjectPhase(projectId, phase.id, { status: 'DONE' })
      const refreshed = await getProjectWorkspace(projectId)
      applyWorkspace(refreshed.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث المرحلة.'))
    }
  }

  async function removePhase(phase: ProjectPhase) {
    if (!projectId) return
    try {
      await deleteProjectPhase(projectId, phase.id)
      const refreshed = await getProjectWorkspace(projectId)
      applyWorkspace(refreshed.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف المرحلة.'))
    }
  }

  async function saveMilestone() {
    if (!projectId || !milestoneTitle.trim() || savingMilestone) return
    setSavingMilestone(true)
    setError(null)
    try {
      await createProjectMilestone(projectId, {
        title: milestoneTitle.trim(),
        due_date: milestoneDue || null,
        phase_id: milestonePhaseId ? Number(milestonePhaseId) : null,
        is_client_visible: milestoneClientVisible,
      })
      setMilestoneTitle('')
      setMilestoneDue('')
      setMilestonePhaseId('')
      setMilestoneClientVisible(false)
      setNotice('تمت إضافة المعلم.')
      const [milestonesResponse, refreshed] = await Promise.all([
        getProjectMilestones(projectId),
        getProjectWorkspace(projectId),
      ])
      setMilestones(milestonesResponse.data.items ?? [])
      applyWorkspace(refreshed.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة المعلم.'))
    } finally {
      setSavingMilestone(false)
    }
  }

  async function markMilestoneDone(milestone: ProjectMilestone) {
    if (!projectId) return
    const openTasks = milestoneOpenTaskCount(milestone)
    const warning = milestoneCompletionWarning(openTasks)
    if (warning && !window.confirm(`${warning}\nهل تريد إتمام المعلم على أي حال؟`)) {
      return
    }
    try {
      await updateProjectMilestone(projectId, milestone.id, { status: 'DONE' })
      const [milestonesResponse, refreshed] = await Promise.all([
        getProjectMilestones(projectId),
        getProjectWorkspace(projectId),
      ])
      setMilestones(milestonesResponse.data.items ?? [])
      applyWorkspace(refreshed.data)
      setNotice(openTasks > 0 ? 'تم إتمام المعلم مع بقاء مهام مفتوحة.' : 'تم إتمام المعلم.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث المعلم.'))
    }
  }

  async function removeMilestone(milestone: ProjectMilestone) {
    if (!projectId) return
    try {
      await deleteProjectMilestone(projectId, milestone.id)
      setMilestones((current) => current.filter((row) => row.id !== milestone.id))
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف المعلم.'))
    }
  }

  async function saveReference() {
    if (!projectId || !referenceTitle.trim() || savingReference) return
    setSavingReference(true)
    setError(null)
    try {
      await createProjectReference(projectId, {
        title: referenceTitle.trim(),
        description: referenceDescription.trim() || null,
        url: referenceUrl.trim() || null,
        type: referenceType,
        is_client_visible: false,
      })
      setReferenceTitle('')
      setReferenceDescription('')
      setReferenceUrl('')
      setNotice('تمت إضافة المرجع.')
      const refreshed = await getProjectWorkspace(projectId)
      applyWorkspace(refreshed.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة المرجع.'))
    } finally {
      setSavingReference(false)
    }
  }

  async function removeReference(reference: ProjectReference) {
    if (!projectId) return
    try {
      await deleteProjectReference(projectId, reference.id)
      const refreshed = await getProjectWorkspace(projectId)
      applyWorkspace(refreshed.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف المرجع.'))
    }
  }

  async function saveTeam() {
    if (!projectId || savingTeam) return
    setSavingTeam(true)
    setNotice(null)
    try {
      const response = await syncProjectMembers(
        projectId,
        selectedMemberIds.map((userId) => ({ user_id: userId, role: 'member' })),
      )
      setWorkspace((current) =>
        current
          ? {
              ...current,
              members: response.data.members,
            }
          : current,
      )
      setNotice('تم تحديث أعضاء الفريق.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث الفريق.'))
    } finally {
      setSavingTeam(false)
    }
  }

  function toggleMember(userId: number) {
    setSelectedMemberIds((current) =>
      current.includes(userId) ? current.filter((id) => id !== userId) : [...current, userId],
    )
  }

  if (loading) {
    return <DashboardPanelSkeleton label="جاري تحميل مساحة المشروع..." />
  }

  if (error && !workspace) {
    return <DashboardErrorState message={error} onRetry={() => void load()} />
  }

  if (!workspace) {
    return (
      <DashboardErrorState
        message={error ?? 'مساحة المشروع غير متاحة.'}
        onRetry={() => void load()}
      />
    )
  }

  const tasks = calendarItems.filter((item) => item.type === 'TASK')
  const structure = workspace.structure
  const taskTitles = Object.fromEntries(workspaceTasks.map((task) => [task.id, task.title]))
  const phases = workspace.phases ?? []
  const filteredWorkspaceTasks = taskFilterUserId
    ? workspaceTasks.filter((task) => task.assigned_to === taskFilterUserId)
    : workspaceTasks

  return (
    <section className="space-y-6">
      <ProjectWorkspaceHeader
        workspace={workspace}
        onAddTask={() => {
          setTab('tasks')
          setShowTaskForm(true)
        }}
        onAddMilestone={() => setTab('milestones')}
        onAddPhase={() => setTab('timeline')}
      />

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {[
          { label: 'إجمالي المهام', value: workspace.progress.total },
          { label: 'مكتملة', value: workspace.progress.completed },
          { label: 'قيد التنفيذ', value: workspace.progress.in_progress },
          { label: 'تحت المراجعة', value: workspace.progress.review + workspace.progress.revision },
          { label: 'متأخرة', value: workspace.progress.overdue },
        ].map((item) => (
          <div key={item.label} className="rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300">
            <p className="text-2xl font-semibold text-slate-900">{item.value.toLocaleString('en-US')}</p>
            <p className="mt-1 text-xs text-slate-500">{item.label}</p>
          </div>
        ))}
      </div>

      <div className="flex gap-1 overflow-x-auto border-b border-slate-200">
        {TABS.map((entry) => (
          <button
            key={entry.key}
            type="button"
            onClick={() => setTab(entry.key)}
            className={`shrink-0 border-b-2 px-3 py-2 text-sm transition ${
              tab === entry.key
                ? 'border-brand-cobalt-500 font-medium text-brand-cobalt-700'
                : 'border-transparent text-slate-500 hover:text-slate-900'
            }`}
          >
            {entry.label}
          </button>
        ))}
      </div>

      {tab === 'overview' ? (
        <div className="space-y-4">
          <div className="grid gap-4 lg:grid-cols-2">
            <DashboardSection title="ملخص المشروع">
              <dl className="space-y-2 text-sm">
                <div className="flex justify-between gap-3">
                  <dt className="text-slate-500">الحالة</dt>
                  <dd className="font-medium text-slate-900">{PROJECT_STATUS_LABELS[workspace.project.status] ?? workspace.project.status}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-slate-500">التقدم</dt>
                  <dd className="font-medium text-slate-900">
                    {Math.round(workspace.progress.percent).toLocaleString('en-US')}% ·{' '}
                    {workspace.progress.completed.toLocaleString('en-US')} من {workspace.progress.total.toLocaleString('en-US')}
                  </dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-slate-500">التسليم</dt>
                  <dd className="font-medium text-slate-900">{formatProjectDate(workspace.project.deadline)}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-slate-500">مدير الحساب</dt>
                  <dd className="font-medium text-slate-900">{workspace.project.account_manager?.name ?? '—'}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-slate-500">الفريق</dt>
                  <dd className="font-medium text-slate-900">{(workspace.team_stats?.length ?? workspace.members.length).toLocaleString('en-US')}</dd>
                </div>
              </dl>
            </DashboardSection>
            <DashboardSection title="الموعد التالي">
              <dl className="space-y-2 text-sm">
                <div className="flex justify-between gap-3">
                  <dt className="text-slate-500">أقرب موعد</dt>
                  <dd className="font-medium text-slate-900">
                    {formatProjectDate(workspace.execution_summary?.next_deadline ?? workspace.next_deadline ?? workspace.next_task?.deadline)}
                  </dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-slate-500">المهمة</dt>
                  <dd className="font-medium text-slate-900">{workspace.next_task?.title ?? '—'}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-slate-500">المعلم</dt>
                  <dd className="font-medium text-slate-900">
                    {workspace.execution_summary?.active_milestone?.title ?? workspace.next_milestone?.title ?? '—'}
                  </dd>
                </div>
              </dl>
              <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" onClick={() => setTab('tasks')} className="text-sm text-brand-cobalt-700 underline">
                  المهام
                </button>
                <button type="button" onClick={() => setTab('milestones')} className="text-sm text-brand-cobalt-700 underline">
                  المعالم
                </button>
                <button type="button" onClick={() => setTab('timeline')} className="text-sm text-brand-cobalt-700 underline">
                  الخط الزمني
                </button>
              </div>
            </DashboardSection>
          </div>

          <DashboardSection title="من البريف">
            <div className="space-y-2 text-sm leading-6 text-slate-700">
              <p>{briefObjective || 'لم يُكتب هدف بعد.'}</p>
              {briefDraft.target_audience ? <p className="text-slate-500">الجمهور: {briefDraft.target_audience}</p> : null}
              {briefDraft.tone ? <p className="text-slate-500">النبرة: {briefDraft.tone}</p> : null}
            </div>
            <button type="button" onClick={() => setTab('brief')} className="mt-3 text-sm text-brand-cobalt-700 underline">
              فتح البريف
            </button>
          </DashboardSection>

          <div className="grid gap-4 lg:grid-cols-2">
            <DashboardSection title="ملاحظات حديثة">
              <ProjectNotesThread
                projectId={workspace.project.id}
                limit={3}
                canWrite={false}
                onShowAll={() => setTab('notes')}
              />
            </DashboardSection>
            <DashboardSection title="آخر النشاط">
              <ProjectRecentActivity
                activities={recentActivities}
                loading={recentActivityLoading}
                error={recentActivityError}
                limit={5}
                taskTitles={taskTitles}
              />
              <button type="button" onClick={() => setTab('activity')} className="mt-3 text-sm text-brand-cobalt-700 underline">
                كل النشاط
              </button>
            </DashboardSection>
          </div>

          <details className="rounded-2xl border border-slate-200 bg-white p-4">
            <summary className="cursor-pointer text-sm font-semibold text-slate-900">تفاصيل إضافية</summary>
            <div className="mt-4 space-y-4">
              <DashboardSection title="ملخص التنفيذ">
                <ProjectExecutionSummaryPanel summary={workspace.execution_summary} />
              </DashboardSection>
              <div className="grid gap-4 lg:grid-cols-2">
                <DashboardSection title="ما يحتاج متابعة">
                  <ProjectAttentionPanel
                    attention={workspace.attention}
                    onSelectItem={(item) => {
                      if (item.related_type === 'task') {
                        setTab('tasks')
                        const task = workspaceTasks.find((row) => row.id === item.related_id)
                        if (task) setEditingTask(task)
                      } else if (item.related_type === 'milestone') {
                        setTab('milestones')
                        setExpandedMilestoneId(item.related_id)
                      }
                    }}
                  />
                </DashboardSection>
                <DashboardSection title="جاهزية الإغلاق">
                  <ProjectClosureReadiness
                    closure={workspace.closure_readiness ?? workspace.execution_summary?.closure}
                  />
                </DashboardSection>
              </div>
              <DashboardSection title="التسليمات" description="مشتقة من خدمات الحزمة والمراجع — بدون نموذج تسليم منفصل.">
                <ProjectDeliverables
                  deliverables={workspace.deliverables}
                  onOpenTask={(taskId) => {
                    setTab('tasks')
                    const task = workspaceTasks.find((row) => row.id === taskId)
                    if (task) setEditingTask(task)
                  }}
                />
              </DashboardSection>
              {(workspace.pending_approvals?.length ?? 0) > 0 ? (
                <DashboardSection title="موافقات معلّقة">
                  <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                    {workspace.pending_approvals?.map((row) => (
                      <li key={row.id} className="px-3 py-2.5 text-sm">
                        <p className="font-medium text-slate-900">{row.title}</p>
                        <p className="text-xs text-slate-500">
                          {row.type}
                          {row.assigned_to ? ` · ${row.assigned_to.name}` : ''}
                        </p>
                      </li>
                    ))}
                  </ul>
                </DashboardSection>
              ) : null}
              <DashboardSection title="لمحة الخط الزمني">
                <StructureTree structure={structure} />
                <button type="button" onClick={() => setTab('timeline')} className="mt-3 text-sm text-brand-cobalt-700 underline">
                  فتح الخط الزمني
                </button>
              </DashboardSection>
            </div>
          </details>
        </div>
      ) : null}

      {tab === 'brief' ? (
        <div className="space-y-4">
          {!editingBrief ? (
            <div className="flex justify-end">
              <button
                type="button"
                onClick={() => setEditingBrief(true)}
                className="inline-flex min-h-10 items-center rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800"
              >
                تعديل
              </button>
            </div>
          ) : null}
          {editingBrief ? (
          <DashboardSection title="ملف العميل والموجز">
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="space-y-1 text-sm">
                <span className="text-slate-600">اسم الشركة</span>
                <input className={fieldClass} value={companyName} onChange={(e) => setCompanyName(e.target.value)} />
              </label>
              <label className="space-y-1 text-sm">
                <span className="text-slate-600">القطاع</span>
                <input className={fieldClass} value={industry} onChange={(e) => setIndustry(e.target.value)} />
              </label>
              <label className="space-y-1 text-sm sm:col-span-2">
                <span className="text-slate-600">هدف المشروع</span>
                <textarea
                  className={fieldClass}
                  rows={2}
                  value={briefObjective}
                  onChange={(e) => setBriefObjective(e.target.value)}
                />
              </label>
              <label className="space-y-1 text-sm sm:col-span-2">
                <span className="text-slate-600">النتيجة المرغوبة</span>
                <textarea
                  className={fieldClass}
                  rows={2}
                  value={briefOutcome}
                  onChange={(e) => setBriefOutcome(e.target.value)}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="text-slate-600">ما يريده العميل (سطر لكل نقطة)</span>
                <textarea className={fieldClass} rows={4} value={wants} onChange={(e) => setWants(e.target.value)} />
              </label>
              <label className="space-y-1 text-sm">
                <span className="text-slate-600">ما لا يريده العميل</span>
                <textarea
                  className={fieldClass}
                  rows={4}
                  value={doesNotWant}
                  onChange={(e) => setDoesNotWant(e.target.value)}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="text-slate-600">ضمن النطاق (سطر لكل نقطة)</span>
                <textarea className={fieldClass} rows={4} value={scopeIn} onChange={(e) => setScopeIn(e.target.value)} />
              </label>
              <label className="space-y-1 text-sm">
                <span className="text-slate-600">خارج النطاق</span>
                <textarea
                  className={fieldClass}
                  rows={4}
                  value={scopeOut}
                  onChange={(e) => setScopeOut(e.target.value)}
                />
              </label>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              {BRIEF_FIELDS.map((field) => (
                <label key={field.key} className="space-y-1 text-sm">
                  <span className="text-slate-600">{field.label}</span>
                  <textarea
                    className={fieldClass}
                    rows={2}
                    value={briefDraft[field.key] ?? ''}
                    onChange={(event) =>
                      setBriefDraft((current) => ({ ...current, [field.key]: event.target.value }))
                    }
                  />
                </label>
              ))}
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
              <button
                type="button"
                disabled={savingBrief}
                onClick={() => void saveBrief()}
                className="inline-flex min-h-10 items-center rounded-lg bg-brand-cobalt-500 px-4 text-sm font-medium text-white disabled:opacity-50"
              >
                حفظ الموجز
              </button>
              <button
                type="button"
                onClick={() => setEditingBrief(false)}
                className="inline-flex min-h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm text-slate-800"
              >
                عرض
              </button>
            </div>
          </DashboardSection>
          ) : (
            <BriefDocument
              objective={briefObjective}
              outcome={briefOutcome}
              wants={wants}
              doesNotWant={doesNotWant}
              scopeIn={scopeIn}
              scopeOut={scopeOut}
              fields={briefDraft}
            />
          )}
        </div>
      ) : null}

      {tab === 'client' ? (
        <DashboardSection title="بيانات العميل" description="بيانات الحساب مرتبطة بسجل العميل. الحقول الإضافية خاصة بهذا المشروع.">
          {!editingClient ? (
            <div className="space-y-4">
              <div className="grid gap-4 lg:grid-cols-2">
                <InfoCard
                  title="بيانات أساسية"
                  rows={[
                    ['الاسم', workspace.project.customer?.name],
                    ['الشركة', companyName],
                    ['الهاتف', profileDraft.phone],
                    ['البريد', workspace.project.customer?.email],
                    ['واتساب', profileDraft.whatsapp],
                  ]}
                />
                <InfoCard
                  title="معلومات إضافية"
                  rows={[
                    ['الموقع', profileDraft.website],
                    ['المجال', industry],
                    ['المدينة / الدولة', profileDraft.city],
                    ['جهة الاتصال', profileDraft.contact_person],
                  ]}
                />
              </div>
              <InfoCard
                title="قنوات التواصل"
                rows={[
                  ['Website', profileDraft.website],
                  ['Social', profileDraft.social_links],
                  ['ملاحظات', profileDraft.notes],
                ]}
              />
              <button
                type="button"
                onClick={() => setEditingClient(true)}
                className="inline-flex min-h-10 items-center rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800"
              >
                تعديل
              </button>
            </div>
          ) : (
            <>
          <dl className="mb-4 grid gap-3 sm:grid-cols-2">
            <div>
              <dt className="text-xs text-slate-500">اسم العميل</dt>
              <dd className="text-sm font-medium">{workspace.project.customer?.name ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs text-slate-500">البريد</dt>
              <dd className="text-sm font-medium">{workspace.project.customer?.email ?? '—'}</dd>
            </div>
            <label className="space-y-1 text-sm">
              <span className="text-slate-600">اسم الشركة</span>
              <input className={fieldClass} value={companyName} onChange={(e) => setCompanyName(e.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="text-slate-600">القطاع</span>
              <input className={fieldClass} value={industry} onChange={(e) => setIndustry(e.target.value)} />
            </label>
            {PROFILE_FIELDS.map((field) => (
              <label key={field.key} className="space-y-1 text-sm">
                <span className="text-slate-600">{field.label}</span>
                <input
                  className={fieldClass}
                  value={profileDraft[field.key] ?? ''}
                  onChange={(event) =>
                    setProfileDraft((current) => ({ ...current, [field.key]: event.target.value }))
                  }
                />
              </label>
            ))}
          </dl>
          <div className="flex flex-wrap gap-2">
          <button
            type="button"
            disabled={savingBrief}
            onClick={() => void saveBrief()}
            className="inline-flex min-h-10 items-center rounded-lg bg-brand-cobalt-500 px-4 text-sm font-medium text-white disabled:opacity-50"
          >
            حفظ بيانات العميل
          </button>
          <button
            type="button"
            onClick={() => setEditingClient(false)}
            className="inline-flex min-h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm text-slate-800"
          >
            عرض
          </button>
          </div>
            </>
          )}
        </DashboardSection>
      ) : null}

      {tab === 'references' ? (
        <DashboardSection title="الريفرنسات">
            <div className="mb-3 grid gap-2 sm:grid-cols-2">
              <input
                className={fieldClass}
                placeholder="عنوان المرجع"
                value={referenceTitle}
                onChange={(e) => setReferenceTitle(e.target.value)}
              />
              <select className={fieldClass} value={referenceType} onChange={(e) => setReferenceType(e.target.value)}>
                {REFERENCE_TYPES.map((type) => (
                  <option key={type.value} value={type.value}>
                    {type.label}
                  </option>
                ))}
              </select>
              <input
                className={fieldClass}
                placeholder="رابط داخلي أو وصف المسار"
                value={referenceUrl}
                onChange={(e) => setReferenceUrl(e.target.value)}
              />
              <input
                className={fieldClass}
                placeholder="وصف اختياري"
                value={referenceDescription}
                onChange={(e) => setReferenceDescription(e.target.value)}
              />
              <button
                type="button"
                disabled={savingReference}
                onClick={() => void saveReference()}
                className="inline-flex min-h-10 items-center justify-center rounded-lg bg-brand-cobalt-500 px-3 text-sm font-medium text-white disabled:opacity-50"
              >
                إضافة مرجع
              </button>
            </div>
            {(workspace.references?.length ?? 0) === 0 ? (
              <p className="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500">لا توجد مراجع بعد</p>
            ) : (
              <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {workspace.references.map((reference) => {
                  const image = reference.url && /\.(png|jpe?g|webp|gif|svg)(\?|$)/i.test(reference.url)
                  const linked = workspaceTasks.filter((task) => reference.linked_task_ids?.includes(task.id))
                  return (
                  <li
                    key={reference.id}
                    className="flex flex-col rounded-2xl border border-slate-200 bg-white p-3 transition hover:border-slate-300"
                  >
                    {image && reference.url ? (
                      <img src={reference.url} alt="" className="mb-3 h-28 w-full rounded-xl object-cover" />
                    ) : (
                      <div className="mb-3 flex h-16 items-center justify-center rounded-xl bg-[#f4f6f8] text-xs font-medium text-slate-500">
                        {reference.url ? referenceHost(reference.url) : (reference.type || 'FILE')}
                      </div>
                    )}
                    <p className="font-medium text-slate-900">{reference.title}</p>
                    <p className="mt-1 text-xs text-slate-500">
                      {REFERENCE_TYPES.find((type) => type.value === reference.type)?.label ?? reference.type}
                    </p>
                    {reference.description ? <p className="mt-2 text-sm leading-6 text-slate-700">{reference.description}</p> : null}
                    <p className="mt-2 text-xs text-slate-500">
                      {reference.creator?.name ?? '—'}
                      {reference.created_at ? ` · ${reference.created_at.slice(0, 10)}` : ''}
                    </p>
                    {linked.length > 0 ? (
                      <p className="mt-1 text-xs text-slate-600">مهمة: {linked.map((task) => task.title).join(' · ')}</p>
                    ) : null}
                    <div className="mt-3 flex flex-wrap gap-2">
                      {reference.url ? (
                        <a href={reference.url} target="_blank" rel="noreferrer" className="inline-flex min-h-9 items-center rounded-lg border border-slate-200 px-2.5 text-xs text-slate-800">
                          فتح
                        </a>
                      ) : null}
                      {reference.url ? (
                        <button
                          type="button"
                          onClick={() => {
                            void navigator.clipboard?.writeText(reference.url ?? '')
                            setNotice('تم نسخ الرابط')
                          }}
                          className="inline-flex min-h-9 items-center rounded-lg border border-slate-200 px-2.5 text-xs text-slate-800"
                        >
                          نسخ الرابط
                        </button>
                      ) : null}
                      <button
                        type="button"
                        onClick={() => void removeReference(reference)}
                        className="inline-flex min-h-9 items-center rounded-lg border border-red-200 px-2.5 text-xs text-red-700"
                      >
                        حذف
                      </button>
                    </div>
                  </li>
                  )
                })}
              </ul>
            )}
        </DashboardSection>
      ) : null}

      {tab === 'timeline' ? (
        <div className="space-y-4">
          <DashboardSection title="المراحل والخط الزمني">
            <div className="mb-3 flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => setTimelineMode('tree')}
                className={`rounded-full px-3 py-1 text-xs ${
                  timelineMode === 'tree' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
                }`}
              >
                شجرة
              </button>
              <button
                type="button"
                onClick={() => setTimelineMode('gantt')}
                className={`rounded-full px-3 py-1 text-xs ${
                  timelineMode === 'gantt' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
                }`}
              >
                خط زمني مرئي
              </button>
            </div>
            <div className="mb-3 grid gap-2 sm:grid-cols-[1fr_auto]">
              <input
                className={fieldClass}
                placeholder="اسم المرحلة (اكتشاف، استراتيجية…)"
                value={phaseTitle}
                onChange={(e) => setPhaseTitle(e.target.value)}
              />
              <button
                type="button"
                disabled={savingPhase}
                onClick={() => void savePhase()}
                className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
              >
                إضافة مرحلة
              </button>
            </div>
            {phases.length === 0 ? (
              <p className="text-sm text-slate-500">لا مراحل بعد.</p>
            ) : (
              <ul className="mb-4 space-y-2">
                {phases.map((phase) => (
                  <li
                    key={phase.id}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm"
                  >
                    <div>
                      <p className="font-medium">{phase.title}</p>
                      <p className="text-xs text-slate-500">
                        {phase.status}
                        {phase.starts_at ? ` · من ${phase.starts_at}` : ''}
                        {phase.ends_at ? ` إلى ${phase.ends_at}` : ''}
                      </p>
                    </div>
                    <div className="flex gap-2">
                      {phase.status !== 'DONE' ? (
                        <button
                          type="button"
                          onClick={() => void markPhaseDone(phase)}
                          className="rounded border border-slate-300 px-2 py-1 text-xs"
                        >
                          إنجاز
                        </button>
                      ) : null}
                      <button
                        type="button"
                        onClick={() => void removePhase(phase)}
                        className="rounded border border-red-200 px-2 py-1 text-xs text-red-800"
                      >
                        حذف
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
            {timelineMode === 'gantt' ? (
              <ProjectTimelineGantt structure={structure} scale={ganttScale} onScaleChange={setGanttScale} />
            ) : (
              <StructureTree structure={structure} />
            )}
          </DashboardSection>
        </div>
      ) : null}

      {tab === 'tasks' ? (
        <DashboardSection
          title="مهام المشروع"
          action={
            <button
              type="button"
              onClick={() => setShowTaskForm((current) => !current)}
              className="text-sm underline"
            >
              {showTaskForm ? 'إخفاء النموذج' : '+ مهمة جديدة'}
            </button>
          }
        >
          <p className="mb-3 text-xs text-slate-500">
            نفس سجل Task يظهر في الخط الزمني والتقويم والعمل الموحّد — بدون تكرار.
          </p>
          {taskCreateError ? <p className="mb-2 text-xs text-red-700">{taskCreateError}</p> : null}
          {tasksError ? <p className="mb-2 text-xs text-red-700">{tasksError}</p> : null}
          {showTaskForm ? (
            <div className="mb-4 grid gap-2 rounded-xl border border-slate-200 bg-[#f4f6f8] p-3 sm:grid-cols-2">
              <input
                className={fieldClass}
                placeholder="عنوان المهمة"
                value={taskTitle}
                onChange={(e) => setTaskTitle(e.target.value)}
              />
              <select
                className={fieldClass}
                value={taskAssignee}
                onChange={(e) => setTaskAssignee(e.target.value)}
              >
                <option value="">المسؤول</option>
                {assignees.map((person) => (
                  <option key={person.id} value={person.id}>
                    {person.name}
                  </option>
                ))}
              </select>
              <select
                className={fieldClass}
                value={taskPriority}
                onChange={(e) => setTaskPriority(e.target.value)}
              >
                <option value="LOW">منخفضة</option>
                <option value="MEDIUM">متوسطة</option>
                <option value="HIGH">عالية</option>
                <option value="URGENT">عاجلة</option>
              </select>
              <select
                className={fieldClass}
                value={taskPhaseId}
                onChange={(e) => setTaskPhaseId(e.target.value)}
              >
                <option value="">بلا مرحلة</option>
                {phases.map((phase) => (
                  <option key={phase.id} value={phase.id}>
                    {phase.title}
                  </option>
                ))}
              </select>
              <select
                className={fieldClass}
                value={taskMilestoneId}
                onChange={(e) => setTaskMilestoneId(e.target.value)}
              >
                <option value="">بلا معلم</option>
                {milestones.map((milestone) => (
                  <option key={milestone.id} value={milestone.id}>
                    {milestone.title}
                  </option>
                ))}
              </select>
              <input
                type="date"
                className={fieldClass}
                value={taskStartAt}
                onChange={(e) => setTaskStartAt(e.target.value)}
              />
              <input
                type="date"
                className={fieldClass}
                value={taskDeadline}
                onChange={(e) => setTaskDeadline(e.target.value)}
              />
              <textarea
                className={`${fieldClass} sm:col-span-2`}
                rows={2}
                placeholder="وصف اختياري"
                value={taskDescription}
                onChange={(e) => setTaskDescription(e.target.value)}
              />
              <label className="flex items-center gap-2 text-xs text-slate-600">
                <input
                  type="checkbox"
                  checked={taskClientVisible}
                  onChange={(e) => setTaskClientVisible(e.target.checked)}
                />
                ظاهر للعميل
              </label>
              <label className="flex items-center gap-2 text-xs text-slate-600">
                <input
                  type="checkbox"
                  checked={taskLinkCalendar}
                  onChange={(e) => setTaskLinkCalendar(e.target.checked)}
                />
                ربط بالتقويم عند وجود تاريخ
              </label>
              <button
                type="button"
                disabled={savingTask}
                onClick={() => void saveTask()}
                className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50 sm:col-span-2"
              >
                حفظ المهمة
              </button>
            </div>
          ) : null}
          {taskFilterUserId ? (
            <button
              type="button"
              className="mb-2 text-xs underline"
              onClick={() => setTaskFilterUserId(null)}
            >
              إلغاء فلتر العضو
            </button>
          ) : null}
          {filteredWorkspaceTasks.length === 0 ? (
            <p className="text-sm text-slate-500">لا مهام مساحة عمل لهذا المشروع بعد.</p>
          ) : (
            <ProjectTaskBoard tasks={filteredWorkspaceTasks} onOpen={setEditingTask} />
          )}
          <div className="mt-4 border-t border-slate-100 pt-3">
            <p className="mb-2 text-xs font-medium text-slate-600">عناصر تقويم مرتبطة (نفس الفترة)</p>
            {tasks.length === 0 ? (
              <p className="text-sm text-slate-500">لا عناصر تقويم من نوع مهمة في هذه الفترة.</p>
            ) : (
              <ul className="space-y-2">
                {tasks.map((item) => (
                  <li
                    key={String(item.id)}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm"
                  >
                    <div>
                      <p className="font-medium text-slate-900">{item.title}</p>
                      <p className="text-xs text-slate-500">
                        {calendarTypeLabel(item.type)} · {calendarStatusLabel(item.status)}
                      </p>
                    </div>
                    <span className="text-xs text-slate-500">{formatDateTimeShort(item.starts_at)}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </DashboardSection>
      ) : null}

      {tab === 'calendar' ? (
        <DashboardSection
          title="عناصر التقويم"
          action={
            <Link to="/owner/calendar" className="text-sm underline">
              فتح التقويم
            </Link>
          }
        >
          {calendarItems.length === 0 ? (
            <p className="text-sm text-slate-500">لا عناصر في هذه الفترة.</p>
          ) : (
            <ul className="space-y-2">
              {calendarItems.map((item) => (
                <li
                  key={String(item.id)}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm"
                >
                  <div>
                    <p className="font-medium text-slate-900">{item.title}</p>
                    <p className="text-xs text-slate-500">
                      {calendarTypeLabel(item.type)} · {calendarStatusLabel(item.status)}
                    </p>
                  </div>
                  <span className="text-xs text-slate-500">{formatDateTimeShort(item.starts_at)}</span>
                </li>
              ))}
            </ul>
          )}
        </DashboardSection>
      ) : null}

      {tab === 'milestones' ? (
        <DashboardSection title="المعالم">
          {milestonesError ? <p className="mb-2 text-xs text-red-700">{milestonesError}</p> : null}
          <div className="mb-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto_auto] lg:items-center">
            <input
              value={milestoneTitle}
              onChange={(event) => setMilestoneTitle(event.target.value)}
              placeholder="عنوان المعلم"
              className={fieldClass}
            />
            <select
              className={fieldClass}
              value={milestonePhaseId}
              onChange={(event) => setMilestonePhaseId(event.target.value)}
            >
              <option value="">بلا مرحلة</option>
              {phases.map((phase) => (
                <option key={phase.id} value={phase.id}>
                  {phase.title}
                </option>
              ))}
            </select>
            <input
              type="date"
              value={milestoneDue}
              onChange={(event) => setMilestoneDue(event.target.value)}
              className={fieldClass}
            />
            <label className="flex items-center gap-2 text-xs text-slate-600">
              <input
                type="checkbox"
                checked={milestoneClientVisible}
                onChange={(event) => setMilestoneClientVisible(event.target.checked)}
              />
              ظاهر للعميل
            </label>
            <button
              type="button"
              disabled={savingMilestone}
              onClick={() => void saveMilestone()}
              className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
            >
              إضافة
            </button>
          </div>
          {milestones.length === 0 ? (
            <p className="text-sm text-slate-500">لا معالم بعد.</p>
          ) : (
            <ul className="space-y-2">
                {milestones.map((milestone) => (
                  <li
                    key={milestone.id}
                    className="rounded-xl border border-slate-100 px-3 py-2 text-sm"
                  >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <button
                        type="button"
                        className="text-start"
                        onClick={() =>
                          setExpandedMilestoneId((current) =>
                            current === milestone.id ? null : milestone.id,
                          )
                        }
                      >
                        <p className="font-medium text-slate-900">{milestone.title}</p>
                        <p className="text-xs text-slate-500">
                          {milestone.status}
                          {milestone.phase ? ` · ${milestone.phase.title}` : ''}
                          {milestone.due_date ? ` · ${milestone.due_date}` : ''}
                          {milestone.is_client_visible ? ' · ظاهر للعميل' : ''}
                          {milestone.is_overdue ? ' · متأخر' : ''}
                          {milestone.progress
                            ? ` · ${milestone.progress.completed}/${milestone.progress.total} (${Math.round(milestone.progress.percent).toLocaleString('ar-SA')}%)`
                            : ''}
                          {milestone.progress?.overdue
                            ? ` · مهام متأخرة ${milestone.progress.overdue}`
                            : ''}
                          {(milestone.open_tasks ??
                            Math.max(
                              0,
                              (milestone.progress?.total ?? 0) - (milestone.progress?.completed ?? 0),
                            )) > 0
                            ? ` · مفتوحة ${milestone.open_tasks ?? (milestone.progress!.total - milestone.progress!.completed)}`
                            : ''}
                        </p>
                      </button>
                      <div className="flex gap-2">
                        {milestone.status !== 'DONE' ? (
                          <button
                            type="button"
                            onClick={() => void markMilestoneDone(milestone)}
                            className="rounded border border-slate-300 px-2 py-1 text-xs"
                          >
                            إنجاز
                          </button>
                        ) : null}
                        <button
                          type="button"
                          onClick={() => void removeMilestone(milestone)}
                          className="rounded border border-red-200 px-2 py-1 text-xs text-red-800"
                        >
                          حذف
                        </button>
                      </div>
                    </div>
                    {expandedMilestoneId === milestone.id ? (
                      <ul className="mt-2 space-y-1 border-t border-slate-100 pt-2 text-xs text-slate-600">
                        {workspaceTasks.filter((task) => task.milestone_id === milestone.id).length === 0 ? (
                          <li>لا مهام مرتبطة.</li>
                        ) : (
                          workspaceTasks
                            .filter((task) => task.milestone_id === milestone.id)
                            .map((task) => (
                              <li key={task.id}>
                                ▸ {task.title} · {task.status}
                                {task.assignee ? ` · ${task.assignee.name}` : ''}
                              </li>
                            ))
                        )}
                      </ul>
                    ) : null}
                  </li>
                ))}
            </ul>
          )}
        </DashboardSection>
      ) : null}

      {tab === 'team' ? (
        <DashboardSection title="الفريق" description="مزامنة أعضاء المشروع وإحصاءات المهام.">
          {(workspace.team_stats?.length ?? 0) === 0 ? (
            <p className="mb-4 rounded-xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500">لا يوجد أعضاء فريق بعد</p>
          ) : (
            <ul className="mb-4 grid gap-3 sm:grid-cols-2">
              {workspace.team_stats?.map((row) => (
                <li key={row.user_id}>
                  <button
                    type="button"
                    onClick={() => {
                      setTaskFilterUserId(row.user_id)
                      setTab('tasks')
                    }}
                    className={`flex w-full items-start gap-3 rounded-2xl border bg-white px-3 py-3 text-start text-sm transition hover:border-slate-300 ${
                      row.role === 'account_manager' ? 'border-brand-cobalt-300' : 'border-slate-200'
                    }`}
                  >
                    <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#f4f6f8] text-xs font-semibold text-slate-700">
                      {projectInitials(row.name)}
                    </span>
                    <span className="min-w-0">
                      <span className="block font-medium text-slate-900">{row.name}</span>
                      <span className="text-xs text-slate-500">{teamRoleLabel(row.role)}</span>
                      <span className="mt-2 block text-xs text-slate-600">
                        مهام مفتوحة {row.open.toLocaleString('en-US')} · مكتملة {row.completed.toLocaleString('en-US')}
                      </span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
          <div className="grid gap-2 sm:grid-cols-2">
            {assignees.map((person) => {
              const checked = selectedMemberIds.includes(person.id)
              return (
                <label
                  key={person.id}
                  className="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm"
                >
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={() => toggleMember(person.id)}
                  />
                  <span>{person.name}</span>
                </label>
              )
            })}
          </div>
          <button
            type="button"
            disabled={savingTeam}
            onClick={() => void saveTeam()}
            className="mt-4 min-h-10 rounded-lg bg-slate-900 px-4 text-sm text-white disabled:opacity-50"
          >
            حفظ الفريق
          </button>
        </DashboardSection>
      ) : null}

      {tab === 'notes' ? (
        <DashboardSection title="ملاحظات المشروع">
          <ProjectNotesThread projectId={workspace.project.id} canWrite />
        </DashboardSection>
      ) : null}

      {tab === 'files' ? (
        <DashboardSection title="الملفات">
          <p className="mb-3 text-sm text-slate-600">
            يستخدم نظام الملفات الحالي ({workspace.files_count.toLocaleString('ar-SA')} ملف مرتبط). يمكن
            ربط الرفع بمهمة موجودة عند الدعم.
          </p>
          <FileLibrary
            scope="workspace"
            query={`?project_id=${workspace.project.id}&per_page=15`}
            projects={[{ id: workspace.project.id, label: workspace.project.title }]}
            tasks={workspaceTasks.map((task) => ({ id: task.id, label: task.title }))}
          />
        </DashboardSection>
      ) : null}

      {tab === 'activity' ? (
        <DashboardSection title="سجل النشاط">
          <div className="mb-3 flex flex-wrap gap-2">
            {ACTIVITY_FILTERS.map((entry) => (
              <button
                key={entry.value}
                type="button"
                onClick={() => setActivityFilter(entry.value)}
                className={`rounded-full px-3 py-1 text-xs ${
                  activityFilter === entry.value
                    ? 'bg-slate-900 text-white'
                    : 'border border-slate-200 bg-white'
                }`}
              >
                {entry.label}
              </button>
            ))}
          </div>
          {activityError ? <p className="mb-2 text-xs text-red-700">{activityError}</p> : null}
          {activityLoading ? (
            <div className="space-y-2" aria-busy="true" aria-label="جاري تحميل النشاط">
              {[0, 1, 2, 3].map((index) => (
                <div key={index} className="h-14 animate-pulse rounded-xl bg-slate-100" />
              ))}
            </div>
          ) : null}
          {!activityLoading && activity.filter((row) => projectActivityMatchesFilter(row, activityFilter)).length === 0 ? (
            <p className="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500">
              {activityError ? 'تعذر عرض النشاط حالياً.' : 'لا يوجد نشاط بعد'}
            </p>
          ) : null}
          {!activityLoading && activity.filter((row) => projectActivityMatchesFilter(row, activityFilter)).length > 0 ? (
            <ul className="space-y-0 border-s border-slate-200 ps-4">
              {activity
                .filter((row) => projectActivityMatchesFilter(row, activityFilter))
                .map((event) => (
                  <li key={event.id} className="relative pb-4 text-sm">
                    <span className="absolute -start-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-brand-cobalt-500" />
                    <p className="font-medium leading-6 text-slate-900">
                      {projectActivityHeadline(event, taskTitles)}
                    </p>
                    <p className="text-xs text-slate-500">
                      {event.actor?.name ? `${event.actor.name} · ` : ''}
                      {formatDateTimeShort(event.created_at)}
                    </p>
                  </li>
                ))}
            </ul>
          ) : null}
          {workspace.upcoming_calendar_items.length > 0 ? (
            <div className="mt-4">
              <h3 className="mb-2 text-sm font-semibold">قادم في التقويم</h3>
              <ul className="space-y-2">
                {workspace.upcoming_calendar_items.map((item) => (
                  <li
                    key={String(item.id)}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm"
                  >
                    <span className="font-medium">{item.title}</span>
                    <span className="text-xs text-slate-500">
                      {calendarTypeLabel(item.type)} · {formatTimeShort(item.starts_at)}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </DashboardSection>
      ) : null}

      {editingTask ? (
        <ProjectTaskQuickEdit
          task={editingTask}
          assignees={assignees}
          phases={phases}
          milestones={milestones}
          saving={savingTaskEdit}
          canEdit
          onClose={() => setEditingTask(null)}
          onSave={(payload) => void saveTaskEdit(payload)}
          onLinkCalendar={() => void linkEditingTaskCalendar()}
        >
          <div className="mt-4 space-y-3 border-t border-slate-100 pt-4 text-sm">
            <p>
              <span className="text-slate-500">المشروع: </span>
              {workspace.project.title}
            </p>
            <p>
              <span className="text-slate-500">العميل: </span>
              {workspace.project.customer?.name ?? '—'}
            </p>
            <p>
              <span className="text-slate-500">أنشأها: </span>
              {editingTask.creator?.name ?? '—'}
              {editingTask.created_at ? ` · ${editingTask.created_at.slice(0, 10)}` : ''}
            </p>
            {editingTask.is_overdue ? <p className="text-xs font-medium text-red-700">متأخرة</p> : null}
            <div className="space-y-1">
              <p className="text-xs text-slate-500">مراجع المشروع المرتبطة</p>
              {(workspace.references ?? []).length === 0 ? (
                <p className="text-xs text-slate-500">لا مراجع على المشروع.</p>
              ) : (
                workspace.references.map((reference) => {
                  const linked = (reference.linked_task_ids ?? []).includes(editingTask.id)
                  return (
                    <label key={reference.id} className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={linked}
                        onChange={() => {
                          const nextIds = (workspace.references ?? [])
                            .filter((item) =>
                              item.id === reference.id
                                ? !linked
                                : (item.linked_task_ids ?? []).includes(editingTask.id),
                            )
                            .map((item) => item.id)
                          void syncTaskReferences(workspace.project.id, editingTask.id, nextIds).then(() => load())
                        }}
                      />
                      <span>{reference.title}</span>
                    </label>
                  )
                })
              )}
            </div>
            <ProjectNotesThread projectId={workspace.project.id} taskId={editingTask.id} canWrite title="ملاحظات المهمة" />
          </div>
        </ProjectTaskQuickEdit>
      ) : null}
    </section>
  )
}
