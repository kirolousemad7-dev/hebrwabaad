import { Link } from 'react-router-dom'
import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { getCalendarAssignees, type CalendarAssignee } from '../../services/calendar'
import {
  completeWork,
  createSavedView,
  deleteSavedView,
  exportOperationsCsv,
  getDepartmentOptions,
  getOperationsProjects,
  getSavedViews,
  getWork,
  getWorkKanban,
  linkCalendarToTask,
  linkTaskToCalendar,
  pinSavedView,
  startWork,
  type DepartmentOption,
  type OperationalSavedView,
  type UnifiedWorkFilters,
  type UnifiedWorkItem,
  type UnifiedWorkKanban,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type BucketTab = 'all' | 'today' | 'overdue' | 'upcoming' | 'completed' | 'kanban'

const BUCKET_TABS: Array<{ key: BucketTab; label: string }> = [
  { key: 'all', label: 'الكل' },
  { key: 'today', label: 'لي اليوم' },
  { key: 'overdue', label: 'المتأخر' },
  { key: 'upcoming', label: 'القادم' },
  { key: 'completed', label: 'مكتمل' },
  { key: 'kanban', label: 'كانبان' },
]

const PRIORITY_OPTIONS = [
  { value: '', label: 'كل الأولويات' },
  { value: 'URGENT', label: 'عاجل' },
  { value: 'HIGH', label: 'مرتفع' },
  { value: 'MEDIUM', label: 'متوسط' },
  { value: 'LOW', label: 'منخفض' },
]

const STATUS_OPTIONS = [
  { value: '', label: 'كل الحالات' },
  { value: 'open', label: 'مفتوح' },
  { value: 'in_progress', label: 'قيد التنفيذ' },
  { value: 'review', label: 'مراجعة' },
  { value: 'completed', label: 'مكتمل' },
  { value: 'overdue', label: 'متأخر' },
]

const SOURCE_OPTIONS = [
  { value: '', label: 'كل المصادر' },
  { value: 'task', label: 'مهمة مشروع' },
  { value: 'calendar', label: 'تقويم' },
]

function sourceBadgeLabel(badge: string): string {
  if (badge === 'task') return 'مهمة مشروع'
  if (badge === 'calendar') return 'تقويم'
  return badge
}

function priorityLabel(priority: string): string {
  if (priority === 'URGENT') return 'عاجل'
  if (priority === 'HIGH') return 'مرتفع'
  if (priority === 'MEDIUM') return 'متوسط'
  if (priority === 'LOW') return 'منخفض'
  return priority
}

function statusLabel(status: string): string {
  if (status === 'open') return 'مفتوح'
  if (status === 'in_progress') return 'قيد التنفيذ'
  if (status === 'review') return 'مراجعة'
  if (status === 'completed') return 'مكتمل'
  if (status === 'cancelled') return 'ملغى'
  if (status === 'overdue') return 'متأخر'
  return status
}

type OwnerWorkPageProps = {
  basePath: '/owner' | '/workspace'
}

export function OwnerWorkPage({ basePath }: OwnerWorkPageProps) {
  const [bucket, setBucket] = useState<BucketTab>('all')
  const [items, setItems] = useState<UnifiedWorkItem[]>([])
  const [kanban, setKanban] = useState<UnifiedWorkKanban['columns'] | null>(null)
  const [savedViews, setSavedViews] = useState<OperationalSavedView[]>([])
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [projects, setProjects] = useState<Array<{ id: number; title: string }>>([])
  const [assignees, setAssignees] = useState<CalendarAssignee[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<string | null>(null)
  const [saveViewName, setSaveViewName] = useState('')
  const [scheduleItem, setScheduleItem] = useState<UnifiedWorkItem | null>(null)
  const [scheduleStartsAt, setScheduleStartsAt] = useState('')
  const [scheduleEndsAt, setScheduleEndsAt] = useState('')
  const [scheduleAllDay, setScheduleAllDay] = useState(false)
  const [scheduleSaving, setScheduleSaving] = useState(false)
  const [linkTaskItem, setLinkTaskItem] = useState<UnifiedWorkItem | null>(null)
  const [linkProjectId, setLinkProjectId] = useState('')
  const [linkSaving, setLinkSaving] = useState(false)
  const [filters, setFilters] = useState({
    department_id: '',
    project_id: '',
    assigned_to: '',
    priority: '',
    source: '',
    status: '',
  })

  const listFilters = useMemo((): UnifiedWorkFilters => {
    const next: UnifiedWorkFilters = {
      per_page: 40,
      sort: 'overdue_first',
    }
    if (bucket !== 'kanban' && bucket !== 'all') {
      next.bucket = bucket
    }
    if (filters.department_id) next.department_id = Number(filters.department_id)
    if (filters.project_id) next.project_id = Number(filters.project_id)
    if (filters.assigned_to) next.assigned_to = Number(filters.assigned_to)
    if (filters.priority) next.priority = filters.priority
    if (filters.source) next.source = filters.source
    if (filters.status) next.status = filters.status
    return next
  }, [bucket, filters])

  const loadLookups = useCallback(async () => {
    const [deptRes, projectRes, assigneeRes, viewsRes] = await Promise.all([
      getDepartmentOptions().catch(() => ({ data: { items: [] as DepartmentOption[] } })),
      getOperationsProjects(1).catch(() => ({ data: { items: [] as Array<{ id: number; title: string }> } })),
      getCalendarAssignees().catch(() => ({ data: { items: [] as CalendarAssignee[] } })),
      getSavedViews('work').catch(() => ({ data: { items: [] as OperationalSavedView[] } })),
    ])
    setDepartments(deptRes.data.items ?? [])
    setProjects((projectRes.data.items ?? []).map((row) => ({ id: row.id, title: row.title })))
    setAssignees(assigneeRes.data.items ?? [])
    setSavedViews(viewsRes.data.items ?? [])
  }, [])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      if (bucket === 'kanban') {
        const response = await getWorkKanban(listFilters)
        setKanban(response.data.columns)
        setItems([])
      } else {
        const response = await getWork(listFilters)
        setItems(response.data.items ?? [])
        setKanban(null)
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل العمل الموحد.'))
      setItems([])
      setKanban(null)
    } finally {
      setLoading(false)
    }
  }, [bucket, listFilters])

  useEffect(() => {
    void loadLookups()
  }, [loadLookups])

  useEffect(() => {
    void load()
  }, [load])

  const pinnedViews = useMemo(
    () => savedViews.filter((view) => view.is_pinned),
    [savedViews],
  )

  async function handleComplete(item: UnifiedWorkItem) {
    setBusyId(item.id)
    setError(null)
    try {
      await completeWork(item.id)
      setNotice('تم إكمال العنصر.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إكمال العنصر.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleStart(item: UnifiedWorkItem) {
    setBusyId(item.id)
    setError(null)
    try {
      await startWork(item.id)
      setNotice('تم بدء العمل.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر بدء العمل.'))
    } finally {
      setBusyId(null)
    }
  }

  function canAddToCalendar(item: UnifiedWorkItem): boolean {
    if (item.source_type !== 'task' && item.source_badge !== 'task') {
      return false
    }
    if (item.calendar_item_id) {
      return false
    }
    if (item.capabilities.can_link_calendar === false) {
      return false
    }
    return item.capabilities.can_edit || item.capabilities.can_link_calendar === true
  }

  function canLinkTask(item: UnifiedWorkItem): boolean {
    if (item.source_type !== 'calendar' && item.source_badge !== 'calendar') {
      return false
    }
    if (item.linked_task_id) {
      return false
    }
    return item.capabilities.can_link_task === true
  }

  function openScheduleModal(item: UnifiedWorkItem) {
    const start = item.due_at ? item.due_at.slice(0, 16) : ''
    setScheduleItem(item)
    setScheduleStartsAt(start)
    setScheduleEndsAt('')
    setScheduleAllDay(false)
  }

  async function submitSchedule() {
    if (!scheduleItem || !scheduleStartsAt) {
      setError('وقت البدء مطلوب.')
      return
    }
    setScheduleSaving(true)
    setError(null)
    try {
      await linkTaskToCalendar(scheduleItem.source_id, {
        starts_at: scheduleStartsAt,
        ends_at: scheduleEndsAt || null,
        all_day: scheduleAllDay,
      })
      setNotice('تمت إضافة المهمة إلى التقويم.')
      setScheduleItem(null)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر الربط بالتقويم.'))
    } finally {
      setScheduleSaving(false)
    }
  }

  async function submitLinkTask() {
    if (!linkTaskItem) return
    setLinkSaving(true)
    setError(null)
    try {
      await linkCalendarToTask(
        linkTaskItem.source_id,
        linkProjectId ? Number(linkProjectId) : null,
      )
      setNotice('تم إنشاء مهمة مرتبطة.')
      setLinkTaskItem(null)
      setLinkProjectId('')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر ربط مهمة المشروع.'))
    } finally {
      setLinkSaving(false)
    }
  }

  async function applySavedView(view: OperationalSavedView) {
    const next = { ...filters }
    const f = view.filters ?? {}
    next.department_id = f.department_id != null ? String(f.department_id) : ''
    next.project_id = f.project_id != null ? String(f.project_id) : ''
    next.assigned_to = f.assigned_to != null ? String(f.assigned_to) : ''
    next.priority = typeof f.priority === 'string' ? f.priority : ''
    next.source = typeof f.source === 'string' ? f.source : ''
    next.status = typeof f.status === 'string' ? f.status : ''
    setFilters(next)
    if (typeof f.bucket === 'string' && BUCKET_TABS.some((tab) => tab.key === f.bucket)) {
      setBucket(f.bucket as BucketTab)
    }
  }

  async function handleSaveView() {
    if (!saveViewName.trim()) {
      setError('اسم العرض مطلوب.')
      return
    }
    try {
      await createSavedView({
        name: saveViewName.trim(),
        view_type: 'work',
        filters: {
          department_id: filters.department_id ? Number(filters.department_id) : undefined,
          project_id: filters.project_id ? Number(filters.project_id) : undefined,
          assigned_to: filters.assigned_to ? Number(filters.assigned_to) : undefined,
          priority: filters.priority || undefined,
          source: filters.source || undefined,
          status: filters.status || undefined,
          bucket: bucket === 'kanban' ? 'all' : bucket,
        },
        is_pinned: true,
      })
      setSaveViewName('')
      setNotice('تم حفظ العرض.')
      await loadLookups()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ العرض.'))
    }
  }

  async function handlePin(view: OperationalSavedView) {
    try {
      await pinSavedView(view.id, !view.is_pinned)
      await loadLookups()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث التثبيت.'))
    }
  }

  async function handleDeleteView(view: OperationalSavedView) {
    try {
      await deleteSavedView(view.id)
      await loadLookups()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف العرض.'))
    }
  }

  function renderWorkRow(item: UnifiedWorkItem) {
    const href = item.href || (item.project_id ? `${basePath === '/owner' ? '/owner/projects' : '/workspace/projects'}/${item.project_id}` : null)
    return (
      <li key={item.id} className="rounded-2xl border border-slate-200 bg-white p-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              {href ? (
                <Link to={href} className="font-semibold text-slate-900 underline-offset-2 hover:underline">
                  {item.title}
                </Link>
              ) : (
                <h2 className="font-semibold text-slate-900">{item.title}</h2>
              )}
              <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] text-slate-600">
                {sourceBadgeLabel(item.source_badge)}
              </span>
              {item.is_overdue ? (
                <span className="rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] text-red-800">
                  متأخر
                </span>
              ) : null}
            </div>
            <p className="mt-1 text-xs text-slate-500">
              {statusLabel(item.status)} · {priorityLabel(item.priority)}
              {item.due_at ? ` · استحقاق ${item.due_at}` : ''}
            </p>
            {item.blocked_label ? <p className="mt-1 text-xs text-amber-800">{item.blocked_label}</p> : null}
          </div>
          <div className="flex flex-wrap gap-2">
            {canAddToCalendar(item) ? (
              <button
                type="button"
                onClick={() => openScheduleModal(item)}
                className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs text-amber-950"
              >
                إضافة للتقويم
              </button>
            ) : null}
            {canLinkTask(item) ? (
              <button
                type="button"
                onClick={() => {
                  setLinkTaskItem(item)
                  setLinkProjectId(item.project_id != null ? String(item.project_id) : '')
                }}
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
              >
                ربط مهمة
              </button>
            ) : null}
            {item.capabilities.can_start && !item.is_completed ? (
              <button
                type="button"
                disabled={busyId === item.id}
                onClick={() => void handleStart(item)}
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs disabled:opacity-50"
              >
                بدء
              </button>
            ) : null}
            {item.capabilities.can_complete && !item.is_completed ? (
              <button
                type="button"
                disabled={busyId === item.id}
                onClick={() => void handleComplete(item)}
                className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white disabled:opacity-50"
              >
                إكمال
              </button>
            ) : null}
          </div>
        </div>
      </li>
    )
  }

  const kanbanColumns: Array<{ key: keyof NonNullable<typeof kanban>; label: string }> = [
    { key: 'open', label: 'مفتوح' },
    { key: 'in_progress', label: 'قيد التنفيذ' },
    { key: 'review', label: 'مراجعة' },
    { key: 'overdue', label: 'متأخر' },
    { key: 'completed', label: 'مكتمل' },
  ]

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">العمل</h1>
          <p className="mt-1 text-sm text-slate-600">عرض موحّد لمهام المشاريع والتقويم.</p>
        </div>
        <button
          type="button"
          onClick={() =>
            void exportOperationsCsv('work', {
              department_id: filters.department_id || undefined,
              project_id: filters.project_id || undefined,
              priority: filters.priority || undefined,
              source: filters.source || undefined,
              status: filters.status || undefined,
            }).then(
              () => setNotice('تم تنزيل تصدير العمل.'),
              (caught) => setError(describeApiError(caught, 'تعذر التصدير.')),
            )
          }
          className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
        >
          تصدير CSV
        </button>
      </header>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <div className="flex flex-wrap gap-2">
        {BUCKET_TABS.map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => setBucket(tab.key)}
            className={`rounded-full px-3 py-1.5 text-sm ${
              bucket === tab.key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">
        <aside className="space-y-4">
          <DashboardSection title="عروض مثبتة">
            {pinnedViews.length === 0 ? (
              <p className="text-xs text-slate-500">لا عروض مثبتة بعد.</p>
            ) : (
              <ul className="space-y-1.5">
                {pinnedViews.map((view) => (
                  <li key={view.id}>
                    <button
                      type="button"
                      onClick={() => void applySavedView(view)}
                      className="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-start text-sm hover:border-amber-300"
                    >
                      {view.name}
                    </button>
                  </li>
                ))}
              </ul>
            )}
            <div className="mt-3 space-y-2">
              <input
                value={saveViewName}
                onChange={(event) => setSaveViewName(event.target.value)}
                placeholder="اسم عرض جديد"
                className={fieldClass}
              />
              <button
                type="button"
                onClick={() => void handleSaveView()}
                className="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs text-white"
              >
                حفظ العرض الحالي
              </button>
            </div>
            {savedViews.length > 0 ? (
              <ul className="mt-3 space-y-1 border-t border-slate-100 pt-3">
                {savedViews.map((view) => (
                  <li key={`all-${view.id}`} className="flex items-center justify-between gap-1 text-xs">
                    <button type="button" className="truncate text-start underline" onClick={() => void applySavedView(view)}>
                      {view.name}
                    </button>
                    <span className="flex shrink-0 gap-1">
                      <button type="button" onClick={() => void handlePin(view)} className="text-amber-800">
                        {view.is_pinned ? 'إلغاء' : 'تثبيت'}
                      </button>
                      <button type="button" onClick={() => void handleDeleteView(view)} className="text-red-700">
                        حذف
                      </button>
                    </span>
                  </li>
                ))}
              </ul>
            ) : null}
          </DashboardSection>
        </aside>

        <div className="space-y-4">
          <div className="grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 sm:grid-cols-2 xl:grid-cols-3">
            <label className="text-xs text-slate-500">
              القسم
              <select
                value={filters.department_id}
                onChange={(event) => setFilters((current) => ({ ...current, department_id: event.target.value }))}
                className={`mt-1 ${fieldClass}`}
              >
                <option value="">الكل</option>
                {departments.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-xs text-slate-500">
              المشروع
              <select
                value={filters.project_id}
                onChange={(event) => setFilters((current) => ({ ...current, project_id: event.target.value }))}
                className={`mt-1 ${fieldClass}`}
              >
                <option value="">الكل</option>
                {projects.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.title}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-xs text-slate-500">
              المعيّن
              <select
                value={filters.assigned_to}
                onChange={(event) => setFilters((current) => ({ ...current, assigned_to: event.target.value }))}
                className={`mt-1 ${fieldClass}`}
              >
                <option value="">الكل</option>
                {assignees.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-xs text-slate-500">
              الأولوية
              <select
                value={filters.priority}
                onChange={(event) => setFilters((current) => ({ ...current, priority: event.target.value }))}
                className={`mt-1 ${fieldClass}`}
              >
                {PRIORITY_OPTIONS.map((row) => (
                  <option key={row.value || 'all'} value={row.value}>
                    {row.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-xs text-slate-500">
              المصدر
              <select
                value={filters.source}
                onChange={(event) => setFilters((current) => ({ ...current, source: event.target.value }))}
                className={`mt-1 ${fieldClass}`}
              >
                {SOURCE_OPTIONS.map((row) => (
                  <option key={row.value || 'all'} value={row.value}>
                    {row.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-xs text-slate-500">
              الحالة
              <select
                value={filters.status}
                onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}
                className={`mt-1 ${fieldClass}`}
              >
                {STATUS_OPTIONS.map((row) => (
                  <option key={row.value || 'all'} value={row.value}>
                    {row.label}
                  </option>
                ))}
              </select>
            </label>
          </div>

          {loading ? <DashboardPanelSkeleton label="جاري تحميل العمل..." /> : null}
          {!loading && error && items.length === 0 && !kanban ? (
            <DashboardErrorState message={error} onRetry={() => void load()} />
          ) : null}

          {!loading && bucket !== 'kanban' && items.length === 0 && !error ? (
            <DashboardEmptyState title="لا عناصر." description="جرّب تغيير التبويب أو الفلاتر." />
          ) : null}

          {!loading && bucket !== 'kanban' && items.length > 0 ? (
            <ul className="space-y-3">{items.map(renderWorkRow)}</ul>
          ) : null}

          {!loading && bucket === 'kanban' && kanban ? (
            <div className="grid gap-3 overflow-x-auto md:grid-cols-2 xl:grid-cols-5">
              {kanbanColumns.map((column) => (
                <div key={column.key} className="min-w-[200px] rounded-2xl border border-slate-200 bg-slate-50/60 p-3">
                  <h3 className="mb-2 text-sm font-semibold text-slate-800">
                    {column.label}{' '}
                    <span className="text-xs font-normal text-slate-500">
                      ({(kanban[column.key] ?? []).length.toLocaleString('ar-SA')})
                    </span>
                  </h3>
                  <ul className="space-y-2">
                    {(kanban[column.key] ?? []).slice(0, 12).map((item) => (
                      <li key={item.id} className="rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-sm">
                        <p className="font-medium text-slate-900">{item.title}</p>
                        <p className="mt-0.5 text-[11px] text-slate-500">
                          {sourceBadgeLabel(item.source_badge)} · {priorityLabel(item.priority)}
                        </p>
                        {canAddToCalendar(item) ? (
                          <button
                            type="button"
                            className="mt-1 text-[11px] underline"
                            onClick={() => openScheduleModal(item)}
                          >
                            إضافة للتقويم
                          </button>
                        ) : null}
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          ) : null}
        </div>
      </div>

      {scheduleItem ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" role="dialog">
          <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-4 shadow-lg">
            <h2 className="text-lg font-semibold text-slate-900">إضافة للتقويم</h2>
            <p className="mt-1 text-sm text-slate-600">{scheduleItem.title}</p>
            <div className="mt-4 space-y-3">
              <label className="block text-xs text-slate-500">
                يبدأ في
                <input
                  type="datetime-local"
                  value={scheduleStartsAt}
                  onChange={(event) => setScheduleStartsAt(event.target.value)}
                  className={`mt-1 ${fieldClass}`}
                />
              </label>
              <label className="block text-xs text-slate-500">
                ينتهي في (اختياري)
                <input
                  type="datetime-local"
                  value={scheduleEndsAt}
                  onChange={(event) => setScheduleEndsAt(event.target.value)}
                  className={`mt-1 ${fieldClass}`}
                />
              </label>
              <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={scheduleAllDay}
                  onChange={(event) => setScheduleAllDay(event.target.checked)}
                />
                طوال اليوم
              </label>
            </div>
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setScheduleItem(null)}
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                إلغاء
              </button>
              <button
                type="button"
                disabled={scheduleSaving}
                onClick={() => void submitSchedule()}
                className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
              >
                ربط
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {linkTaskItem ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" role="dialog">
          <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-4 shadow-lg">
            <h2 className="text-lg font-semibold text-slate-900">ربط مهمة مشروع</h2>
            <p className="mt-1 text-sm text-slate-600">{linkTaskItem.title}</p>
            <label className="mt-4 block text-xs text-slate-500">
              المشروع (اختياري)
              <select
                value={linkProjectId}
                onChange={(event) => setLinkProjectId(event.target.value)}
                className={`mt-1 ${fieldClass}`}
              >
                <option value="">بدون / تلقائي</option>
                {projects.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.title}
                  </option>
                ))}
              </select>
            </label>
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setLinkTaskItem(null)}
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                إلغاء
              </button>
              <button
                type="button"
                disabled={linkSaving}
                onClick={() => void submitLinkTask()}
                className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
              >
                ربط
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
