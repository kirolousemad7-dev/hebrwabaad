import { Link, useParams } from 'react-router-dom'
import { useEffect, useMemo, useState } from 'react'
import {
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { getCalendarAssignees, type CalendarAssignee, type CalendarItem } from '../../services/calendar'
import {
  createProjectMilestone,
  deleteProjectMilestone,
  getProjectCalendarItems,
  getProjectMilestones,
  getProjectTimeline,
  getProjectWorkspace,
  syncProjectMembers,
  updateProjectMilestone,
  type ProjectMilestone,
  type ProjectTimelineEvent,
  type ProjectWorkspace,
} from '../../services/operations'
import { formatDateTimeShort, formatTimeShort } from '../../utils/calendarDates'
import { calendarStatusLabel, calendarTypeLabel } from '../../utils/calendarLabels'
import { describeApiError } from '../../utils/errors'

type TabKey = 'overview' | 'tasks' | 'calendar' | 'team' | 'files' | 'activity' | 'timeline'

const TABS: Array<{ key: TabKey; label: string }> = [
  { key: 'overview', label: 'نظرة عامة' },
  { key: 'tasks', label: 'المهام' },
  { key: 'calendar', label: 'التقويم' },
  { key: 'timeline', label: 'الجدول الزمني' },
  { key: 'team', label: 'الفريق' },
  { key: 'files', label: 'الملفات' },
  { key: 'activity', label: 'النشاط' },
]

const TIMELINE_FILTERS = [
  { value: 'all', label: 'الكل' },
  { value: 'tasks', label: 'مهام' },
  { value: 'files', label: 'ملفات' },
  { value: 'team', label: 'فريق' },
  { value: 'approvals', label: 'موافقات' },
  { value: 'automation', label: 'أتمتة' },
] as const

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

function healthBadge(status: string, label: string) {
  const tone =
    status === 'overdue'
      ? 'border-red-300 bg-red-50 text-red-900'
      : status === 'needs_attention'
        ? 'border-amber-300 bg-amber-50 text-amber-900'
        : 'border-slate-200 bg-slate-50 text-slate-700'
  return <span className={`rounded-full border px-2 py-0.5 text-xs ${tone}`}>{label || status}</span>
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

export function OwnerProjectWorkspacePage() {
  const { projectId } = useParams()
  const [tab, setTab] = useState<TabKey>('overview')
  const [workspace, setWorkspace] = useState<ProjectWorkspace | null>(null)
  const [calendarItems, setCalendarItems] = useState<CalendarItem[]>([])
  const [milestones, setMilestones] = useState<ProjectMilestone[]>([])
  const [timeline, setTimeline] = useState<ProjectTimelineEvent[]>([])
  const [timelineFilter, setTimelineFilter] = useState<string>('all')
  const [assignees, setAssignees] = useState<CalendarAssignee[]>([])
  const [selectedMemberIds, setSelectedMemberIds] = useState<number[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [savingTeam, setSavingTeam] = useState(false)
  const [milestoneTitle, setMilestoneTitle] = useState('')
  const [milestoneDue, setMilestoneDue] = useState('')
  const [savingMilestone, setSavingMilestone] = useState(false)

  const range = useMemo(() => rangeAroundToday(), [])

  async function load() {
    if (!projectId) return
    setLoading(true)
    setError(null)
    try {
      const [workspaceResponse, calendarResponse, milestonesResponse] = await Promise.all([
        getProjectWorkspace(projectId),
        getProjectCalendarItems(projectId, range.from, range.to),
        getProjectMilestones(projectId).catch(() => ({ data: { items: [] as ProjectMilestone[] } })),
      ])
      setWorkspace(workspaceResponse.data)
      setSelectedMemberIds(workspaceResponse.data.members.map((member) => member.user_id))
      setCalendarItems(calendarResponse.data.items ?? [])
      setMilestones(milestonesResponse.data.items ?? [])
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
    if (!projectId || tab !== 'timeline') return
    let cancelled = false
    void getProjectTimeline(projectId, timelineFilter)
      .then((response) => {
        if (!cancelled) setTimeline(response.data.items ?? [])
      })
      .catch(() => {
        if (!cancelled) setTimeline([])
      })
    return () => {
      cancelled = true
    }
  }, [projectId, tab, timelineFilter])

  async function saveMilestone() {
    if (!projectId || !milestoneTitle.trim() || savingMilestone) return
    setSavingMilestone(true)
    setError(null)
    try {
      await createProjectMilestone(projectId, {
        title: milestoneTitle.trim(),
        due_date: milestoneDue || null,
      })
      setMilestoneTitle('')
      setMilestoneDue('')
      setNotice('تمت إضافة المعلم.')
      const response = await getProjectMilestones(projectId)
      setMilestones(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة المعلم.'))
    } finally {
      setSavingMilestone(false)
    }
  }

  async function markMilestoneDone(milestone: ProjectMilestone) {
    if (!projectId) return
    try {
      await updateProjectMilestone(projectId, milestone.id, { status: 'DONE' })
      const response = await getProjectMilestones(projectId)
      setMilestones(response.data.items ?? [])
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
    return null
  }

  const tasks = calendarItems.filter((item) => item.type === 'TASK')
  const health = workspace.health

  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <Link to="/owner/projects" className="text-sm text-slate-600 underline">
          العودة للمشاريع
        </Link>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-slate-900">{workspace.project.title}</h1>
            <p className="mt-1 text-sm text-slate-600">
              {workspace.project.customer?.name ?? 'بدون عميل'}
              {workspace.project.account_manager
                ? ` · مدير الحساب: ${workspace.project.account_manager.name}`
                : ''}
            </p>
          </div>
          {healthBadge(health.status, health.label)}
        </div>
      </header>

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
          <div className="grid gap-4 lg:grid-cols-3">
            <DashboardSection title="الحالة والتقدم">
              <p className="text-sm text-slate-600">الحالة: {workspace.project.status}</p>
              <p className="mt-1 text-sm text-slate-600">
                التقدم: {Math.round(workspace.progress.percent).toLocaleString('ar-SA')}%
              </p>
              <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                <div
                  className="h-full rounded-full bg-brand-primary/80"
                  style={{ width: `${Math.min(100, Math.max(0, workspace.progress.percent))}%` }}
                />
              </div>
              <p className="mt-3 text-sm text-slate-600">
                البداية: {workspace.project.started_at ?? '—'} · الموعد: {workspace.project.deadline ?? '—'}
              </p>
            </DashboardSection>
            <DashboardSection title="مهام التقويم">
              <ul className="space-y-1 text-sm text-slate-700">
                <li>الإجمالي: {workspace.calendar_tasks.total.toLocaleString('ar-SA')}</li>
                <li>مفتوحة: {workspace.calendar_tasks.open.toLocaleString('ar-SA')}</li>
                <li>مكتملة: {workspace.calendar_tasks.completed.toLocaleString('ar-SA')}</li>
                <li>متأخرة: {workspace.calendar_tasks.overdue.toLocaleString('ar-SA')}</li>
              </ul>
            </DashboardSection>
            <DashboardSection title="صحة المشروع">
              <p className="text-sm">{health.label}</p>
              {health.days_to_deadline != null ? (
                <p className="mt-1 text-xs text-slate-500">
                  أيام حتى الموعد: {health.days_to_deadline.toLocaleString('ar-SA')}
                </p>
              ) : null}
              <p className="mt-1 text-xs text-slate-500">
                مهام متأخرة (مساحة): {(health.overdue_workspace_tasks ?? 0).toLocaleString('ar-SA')} · تقويم:{' '}
                {(health.overdue_calendar_tasks ?? 0).toLocaleString('ar-SA')}
              </p>
            </DashboardSection>
          </div>

          {(workspace.required_services?.length ?? 0) > 0 ? (
            <DashboardSection title="الخدمات المطلوبة">
              <ul className="divide-y divide-slate-100">
                {workspace.required_services?.map((line) => (
                  <li key={line.order_item_id} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                    <div>
                      <p className="font-medium text-slate-900">{line.service_name}</p>
                      <p className="text-xs text-slate-500">
                        الكمية: {line.quantity.toLocaleString('ar-SA')}
                        {line.department_name ? ` · ${line.department_name}` : ' · بلا قسم مرتبط'}
                        {line.requires_customer_approval ? ' · اعتماد عميل' : ''}
                      </p>
                    </div>
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                      {line.status_label}
                      {line.assigned_to == null ? ' · غير معيّن لموظف' : ''}
                    </span>
                  </li>
                ))}
              </ul>
            </DashboardSection>
          ) : null}

          <DashboardSection title="المعالم">
            <div className="mb-3 grid gap-2 sm:grid-cols-[1fr_auto_auto]">
              <input
                value={milestoneTitle}
                onChange={(event) => setMilestoneTitle(event.target.value)}
                placeholder="عنوان المعلم"
                className={fieldClass}
              />
              <input
                type="date"
                value={milestoneDue}
                onChange={(event) => setMilestoneDue(event.target.value)}
                className={fieldClass}
              />
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
                    className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm"
                  >
                    <div>
                      <p className="font-medium text-slate-900">{milestone.title}</p>
                      <p className="text-xs text-slate-500">
                        {milestone.status}
                        {milestone.due_date ? ` · ${milestone.due_date}` : ''}
                      </p>
                    </div>
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
                  </li>
                ))}
              </ul>
            )}
          </DashboardSection>
        </div>
      ) : null}

      {tab === 'tasks' || tab === 'calendar' ? (
        <DashboardSection
          title={tab === 'tasks' ? 'المهام' : 'عناصر التقويم'}
          action={
            <Link to="/owner/calendar" className="text-sm underline">
              فتح التقويم
            </Link>
          }
        >
          {(tab === 'tasks' ? tasks : calendarItems).length === 0 ? (
            <p className="text-sm text-slate-500">لا عناصر في هذه الفترة.</p>
          ) : (
            <ul className="space-y-2">
              {(tab === 'tasks' ? tasks : calendarItems).map((item) => (
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

      {tab === 'timeline' ? (
        <DashboardSection title="الجدول الزمني">
          <div className="mb-3 flex flex-wrap gap-2">
            {TIMELINE_FILTERS.map((entry) => (
              <button
                key={entry.value}
                type="button"
                onClick={() => setTimelineFilter(entry.value)}
                className={`rounded-full px-3 py-1 text-xs ${
                  timelineFilter === entry.value
                    ? 'bg-slate-900 text-white'
                    : 'border border-slate-200 bg-white'
                }`}
              >
                {entry.label}
              </button>
            ))}
          </div>
          {timeline.length === 0 ? (
            <p className="text-sm text-slate-500">لا أحداث في هذا الفلتر.</p>
          ) : (
            <ul className="space-y-2">
              {timeline.map((event, index) => (
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
        </DashboardSection>
      ) : null}

      {tab === 'team' ? (
        <DashboardSection title="الفريق" description="مزامنة أعضاء المشروع.">
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
          <p className="text-sm text-slate-700">
            عدد الملفات المرتبطة: {workspace.files_count.toLocaleString('ar-SA')}
          </p>
          <p className="mt-2 text-sm text-slate-500">
            إدارة الملفات التفصيلية متاحة من صفحة الملفات.
          </p>
          <Link to="/owner/files" className="mt-3 inline-block text-sm underline">
            فتح الملفات
          </Link>
        </DashboardSection>
      ) : null}

      {tab === 'activity' ? (
        <DashboardSection title="النشاط القادم">
          {workspace.upcoming_calendar_items.length === 0 ? (
            <p className="text-sm text-slate-500">لا عناصر قادمة.</p>
          ) : (
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
          )}
          <div className="mt-4 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-600">
            صحة المشروع: {health.label}
            {health.days_to_deadline != null
              ? ` · ${health.days_to_deadline.toLocaleString('ar-SA')} يوم حتى الموعد`
              : ''}
          </div>
        </DashboardSection>
      ) : null}
    </section>
  )
}
