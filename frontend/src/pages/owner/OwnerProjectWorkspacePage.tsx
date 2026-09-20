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
  getProjectTimeline,
  getProjectWorkspace,
  getProjectWorkspaceTasks,
  linkProjectWorkspaceTaskCalendar,
  syncProjectMembers,
  updateProjectBrief,
  updateProjectMilestone,
  updateProjectPhase,
  updateProjectWorkspaceTask,
  type ProjectMilestone,
  type ProjectPhase,
  type ProjectReference,
  type ProjectStructure,
  type ProjectTimelineEvent,
  type ProjectWorkspace,
  type ProjectWorkspaceTask,
} from '../../services/operations'
import { formatDateTimeShort, formatTimeShort } from '../../utils/calendarDates'
import { calendarStatusLabel, calendarTypeLabel } from '../../utils/calendarLabels'
import { describeApiError } from '../../utils/errors'

type TabKey =
  | 'overview'
  | 'brief'
  | 'timeline'
  | 'tasks'
  | 'calendar'
  | 'milestones'
  | 'team'
  | 'files'
  | 'activity'

const TABS: Array<{ key: TabKey; label: string }> = [
  { key: 'overview', label: 'نظرة عامة' },
  { key: 'brief', label: 'الموجز والمتطلبات' },
  { key: 'timeline', label: 'الخط الزمني' },
  { key: 'tasks', label: 'المهام' },
  { key: 'calendar', label: 'التقويم' },
  { key: 'milestones', label: 'المعالم' },
  { key: 'team', label: 'الفريق' },
  { key: 'files', label: 'الملفات' },
  { key: 'activity', label: 'النشاط' },
]

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
    <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
      <div className="h-full rounded-full bg-brand-primary/80" style={{ width: `${width}%` }} />
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
              <li key={`m-${milestone.id}`} className="rounded-lg bg-slate-50 px-3 py-2 text-sm">
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
  const [activity, setActivity] = useState<ProjectTimelineEvent[]>([])
  const [activityFilter, setActivityFilter] = useState<string>('all')
  const [assignees, setAssignees] = useState<CalendarAssignee[]>([])
  const [selectedMemberIds, setSelectedMemberIds] = useState<number[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
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
    if (!projectId || tab !== 'activity') return
    let cancelled = false
    setActivityError(null)
    void getProjectTimeline(projectId, activityFilter)
      .then((response) => {
        if (!cancelled) setActivity(response.data.items ?? [])
      })
      .catch((caught) => {
        if (!cancelled) {
          setActivity([])
          setActivityError(describeApiError(caught, 'تعذر تحميل النشاط.'))
        }
      })
    return () => {
      cancelled = true
    }
  }, [projectId, tab, activityFilter])

  async function saveBrief() {
    if (!projectId || savingBrief) return
    setSavingBrief(true)
    setError(null)
    try {
      await updateProjectBrief(projectId, {
        brief: {
          ...(workspace?.brief ?? {}),
          objective: briefObjective.trim() || null,
          desired_outcome: briefOutcome.trim() || null,
        },
        client_profile: {
          ...(workspace?.client_profile ?? {}),
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
        url: referenceUrl.trim() || null,
        type: 'OTHER',
        is_client_visible: false,
      })
      setReferenceTitle('')
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
  const health = workspace.health
  const structure = workspace.structure
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

      <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        {TABS.map((entry) => (
          <button
            key={entry.key}
            type="button"
            onClick={() => setTab(entry.key)}
            className={`rounded-full px-3 py-1.5 text-sm ${
              tab === entry.key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700'
            }`}
          >
            {entry.label}
          </button>
        ))}
      </div>

      {tab === 'overview' ? (
        <div className="space-y-4">
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
              <div className="mt-4">
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                  صحة المشروع
                </p>
                <p className="text-sm">{health.label}</p>
                {health.days_to_deadline != null ? (
                  <p className="mt-1 text-xs text-slate-500">
                    أيام حتى الموعد: {health.days_to_deadline.toLocaleString('ar-SA')}
                  </p>
                ) : null}
              </div>
            </DashboardSection>
          </div>

          <DashboardSection
            title="التسليمات"
            description="مشتقة من خدمات الحزمة والمراجع — بدون نموذج تسليم منفصل."
          >
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

          <DashboardSection title="أحدث التحديثات">
            <ProjectRecentActivity events={workspace.recent_activity ?? workspace.timeline_preview} />
          </DashboardSection>

          <div className="grid gap-4 lg:grid-cols-2">
            <DashboardSection title="المسؤوليات">
              <ul className="space-y-2 text-sm text-slate-700">
                <li>
                  <span className="text-slate-500">المالك:</span> صلاحية إشراف كاملة (دور المالك)
                </li>
                <li>
                  <span className="text-slate-500">مدير الحساب:</span>{' '}
                  {workspace.project.account_manager?.name ?? '—'}
                </li>
                <li>
                  <span className="text-slate-500">الفريق:</span>{' '}
                  {workspace.members.length === 0
                    ? 'لا أعضاء'
                    : workspace.members
                        .map((member) => member.user?.name)
                        .filter(Boolean)
                        .join(' · ')}
                </li>
              </ul>
            </DashboardSection>
            <DashboardSection title="التقدم والمهام">
              <ul className="space-y-1 text-sm text-slate-700">
                <li>مكتمل: {workspace.progress.completed.toLocaleString('ar-SA')}</li>
                <li>قيد التنفيذ: {workspace.progress.in_progress.toLocaleString('ar-SA')}</li>
                <li>معلّق: {workspace.progress.todo.toLocaleString('ar-SA')}</li>
                <li>متأخر: {workspace.progress.overdue.toLocaleString('ar-SA')}</li>
              </ul>
            </DashboardSection>
          </div>

          <DashboardSection title="لمحة الخط الزمني">
            <StructureTree structure={structure} />
          </DashboardSection>
        </div>
      ) : null}

      {tab === 'brief' ? (
        <div className="space-y-4">
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
            <button
              type="button"
              disabled={savingBrief}
              onClick={() => void saveBrief()}
              className="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm text-white disabled:opacity-50"
            >
              حفظ الموجز
            </button>
          </DashboardSection>

          <DashboardSection title="المراجع والأمثلة">
            <div className="mb-3 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
              <input
                className={fieldClass}
                placeholder="عنوان المرجع"
                value={referenceTitle}
                onChange={(e) => setReferenceTitle(e.target.value)}
              />
              <input
                className={fieldClass}
                placeholder="رابط (اختياري)"
                value={referenceUrl}
                onChange={(e) => setReferenceUrl(e.target.value)}
              />
              <button
                type="button"
                disabled={savingReference}
                onClick={() => void saveReference()}
                className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
              >
                إضافة
              </button>
            </div>
            {(workspace.references?.length ?? 0) === 0 ? (
              <p className="text-sm text-slate-500">لا مراجع بعد.</p>
            ) : (
              <ul className="space-y-2">
                {workspace.references.map((reference) => (
                  <li
                    key={reference.id}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm"
                  >
                    <div>
                      <p className="font-medium">{reference.title}</p>
                      <p className="text-xs text-slate-500">
                        {reference.type}
                        {reference.is_client_visible ? ' · ظاهر للعميل' : ' · داخلي'}
                        {reference.url ? ` · ${reference.url}` : ''}
                      </p>
                    </div>
                    <button
                      type="button"
                      onClick={() => void removeReference(reference)}
                      className="rounded border border-red-200 px-2 py-1 text-xs text-red-800"
                    >
                      حذف
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </DashboardSection>
        </div>
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
            <div className="mb-4 grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2">
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
            <div className="overflow-x-auto">
              <ul className="min-w-[28rem] space-y-2 sm:min-w-0">
                {filteredWorkspaceTasks.map((task) => (
                  <li key={task.id}>
                    <button
                      type="button"
                      onClick={() => setEditingTask(task)}
                      className={`flex w-full flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2 text-start text-sm ${
                        task.is_overdue
                          ? 'border-red-200 bg-red-50'
                          : 'border-slate-100 bg-white hover:border-slate-300'
                      }`}
                    >
                      <div>
                        <p className="font-medium text-slate-900">{task.title}</p>
                        <p className="text-xs text-slate-500">
                          {task.status}
                          {task.assignee ? ` · ${task.assignee.name}` : ''}
                          {task.deadline ? ` · ${task.deadline}` : ''}
                          {task.calendar_item_id ? ' · مربوط بالتقويم' : ''}
                          {task.is_client_visible ? ' · ظاهر للعميل' : ''}
                          {task.is_overdue ? ' · متأخر' : ''}
                        </p>
                      </div>
                      <span className="text-xs text-slate-500">تعديل</span>
                    </button>
                  </li>
                ))}
              </ul>
            </div>
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
          <div className="mb-4 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-700">
            مدير الحساب: {workspace.project.account_manager?.name ?? '—'}
          </div>
          {(workspace.team_stats?.length ?? 0) > 0 ? (
            <ul className="mb-4 grid gap-2 sm:grid-cols-2">
              {workspace.team_stats?.map((row) => (
                <li key={row.user_id}>
                  <button
                    type="button"
                    onClick={() => {
                      setTaskFilterUserId(row.user_id)
                      setTab('tasks')
                    }}
                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-start text-sm hover:border-slate-400"
                  >
                    <p className="font-medium text-slate-900">{row.name}</p>
                    <p className="text-xs text-slate-500">{row.role}</p>
                    <p className="mt-2 text-xs text-slate-600">
                      معيّن {row.assigned} · مكتمل {row.completed} · مفتوح {row.open} · متأخر {row.overdue}
                    </p>
                  </button>
                </li>
              ))}
            </ul>
          ) : null}
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
          {activity.length === 0 ? (
            <p className="text-sm text-slate-500">
              {activityError ? 'تعذر عرض النشاط حالياً.' : 'لا أحداث في هذا الفلتر.'}
            </p>
          ) : (
            <ul className="space-y-2">
              {activity.map((event, index) => (
                <li
                  key={`${event.type}-${event.related_id ?? index}-${event.occurred_at}`}
                  className="rounded-xl border border-slate-100 px-3 py-2 text-sm"
                >
                  <p className="font-medium text-slate-900">{event.title}</p>
                  <p className="text-xs text-slate-500">
                    {event.type} · {formatDateTimeShort(event.occurred_at)}
                  </p>
                </li>
              ))}
            </ul>
          )}
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
        />
      ) : null}
    </section>
  )
}
