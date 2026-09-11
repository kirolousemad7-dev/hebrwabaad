import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CalendarItemModal } from '../../components/calendar/CalendarItemModal'
import { CalendarKanban } from '../../components/calendar/CalendarKanban'
import { CalendarTaskList } from '../../components/calendar/CalendarTaskList'
import { CalendarWorkloadPanel } from '../../components/calendar/CalendarWorkloadPanel'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useToast } from '../../context/ToastContext'
import {
  completeCalendarItem,
  createCalendarItem,
  deleteCalendarItem,
  duplicateCalendarItem,
  exportCalendarIcs,
  getCalendarAssignees,
  getCalendarItem,
  getCalendarItems,
  rescheduleCalendarItem,
  resolveCalendarItemId,
  resolveOccurrenceAt,
  updateCalendarItem,
  type CalendarAssignee,
  type CalendarItem,
  type CalendarItemPayload,
  type CalendarSummary,
} from '../../services/calendar'
import { getDepartmentOptions, type DepartmentOption } from '../../services/operations'
import {
  buildMonthCells,
  buildWeekDays,
  dateKeyFromIso,
  formatDayTitle,
  formatMonthTitle,
  formatTimeShort,
  rangeFor,
  sameDay,
  shiftCursor,
  toDateInput,
  type CalendarViewMode,
} from '../../utils/calendarDates'
import {
  CALENDAR_PRIORITIES,
  CALENDAR_QUICK_TYPES,
  CALENDAR_SOURCES,
  CALENDAR_STATUSES,
  CALENDAR_TYPES,
  CALENDAR_WEEKDAY_LABELS,
  calendarItemChipClass,
  calendarPriorityLabel,
  calendarSourceBadgeClass,
  calendarSourceLabel,
  calendarStatusLabel,
  calendarStatusTone,
  calendarTypeLabel,
} from '../../utils/calendarLabels'
import { askRecurrenceScope, itemHasRecurrence } from '../../utils/calendarRecurrence'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const EMPTY_SUMMARY: CalendarSummary = {
  today_tasks: 0,
  today_meetings: 0,
  overdue: 0,
  upcoming: 0,
}

type PageTab = 'calendar' | 'tasks' | 'workload' | 'kanban'

type ModalState =
  | { kind: 'closed' }
  | { kind: 'create'; startsAt: Date; type?: string }
  | { kind: 'detail'; item: CalendarItem }
  | { kind: 'edit'; item: CalendarItem }

function preferMobileView(): CalendarViewMode {
  if (typeof window === 'undefined') return 'month'
  return window.matchMedia('(max-width: 767px)').matches ? 'agenda' : 'month'
}

export function OwnerCalendarPage() {
  const toast = useToast()
  const [searchParams, setSearchParams] = useSearchParams()
  const [pageTab, setPageTab] = useState<PageTab>('calendar')
  const [view, setView] = useState<CalendarViewMode>(() => preferMobileView())
  const [cursor, setCursor] = useState(() => new Date())
  const range = useMemo(() => rangeFor(view, cursor), [view, cursor])

  const [scope, setScope] = useState<'mine' | 'team'>('mine')
  const [typeFilter, setTypeFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [priorityFilter, setPriorityFilter] = useState('')
  const [sourceFilter, setSourceFilter] = useState('')
  const [assigneeFilter, setAssigneeFilter] = useState('')
  const [departmentFilter, setDepartmentFilter] = useState('')
  const [search, setSearch] = useState('')
  const [searchDraft, setSearchDraft] = useState('')

  const [items, setItems] = useState<CalendarItem[]>([])
  const [summary, setSummary] = useState<CalendarSummary>(EMPTY_SUMMARY)
  const [assignees, setAssignees] = useState<CalendarAssignee[]>([])
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [modal, setModal] = useState<ModalState>({ kind: 'closed' })
  const [exporting, setExporting] = useState(false)

  useEffect(() => {
    const tab = searchParams.get('tab')
    if (tab === 'tasks' || tab === 'workload' || tab === 'kanban' || tab === 'calendar') {
      setPageTab(tab)
    }
  }, [searchParams])

  useEffect(() => {
    const raw = searchParams.get('item')
    if (!raw || !/^\d+$/.test(raw)) return
    let cancelled = false
    void getCalendarItem(raw)
      .then((response) => {
        if (cancelled) return
        setModal({ kind: 'detail', item: response.data })
        const next = new URLSearchParams(searchParams)
        next.delete('item')
        setSearchParams(next, { replace: true })
      })
      .catch(() => {
        /* ignore deep-link failures */
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    const media = window.matchMedia('(max-width: 767px)')
    function onChange(event: MediaQueryListEvent) {
      if (event.matches) {
        setView((current) => (current === 'month' || current === 'week' ? 'agenda' : current))
      }
    }
    media.addEventListener('change', onChange)
    return () => media.removeEventListener('change', onChange)
  }, [])

  useEffect(() => {
    const handle = window.setTimeout(() => setSearch(searchDraft.trim()), 300)
    return () => window.clearTimeout(handle)
  }, [searchDraft])

  async function loadAssignees() {
    try {
      const response = await getCalendarAssignees()
      setAssignees(response.data.items ?? [])
    } catch {
      setAssignees([])
    }
  }

  async function loadDepartments() {
    try {
      const response = await getDepartmentOptions()
      setDepartments(response.data.items ?? [])
    } catch {
      setDepartments([])
    }
  }

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCalendarItems({
        from: range.from,
        to: range.to,
        scope,
        type: typeFilter || undefined,
        status: statusFilter || undefined,
        priority: priorityFilter || undefined,
        source: sourceFilter || undefined,
        assignee_id: assigneeFilter || undefined,
        department_id: departmentFilter || undefined,
        q: search || undefined,
        include_linked: '1',
      })
      setItems(response.data.items ?? [])
      setSummary(response.data.summary ?? EMPTY_SUMMARY)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل التقويم.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadAssignees()
    void loadDepartments()
  }, [])

  useEffect(() => {
    if (pageTab === 'tasks') return
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [range.from, range.to, scope, typeFilter, statusFilter, priorityFilter, sourceFilter, assigneeFilter, departmentFilter, search, pageTab])

  const itemsByDay = useMemo(() => {
    const map = new Map<string, CalendarItem[]>()
    for (const item of items) {
      const key = dateKeyFromIso(item.starts_at, Boolean(item.all_day))
      const list = map.get(key) ?? []
      list.push(item)
      map.set(key, list)
    }
    return map
  }, [items])

  const upcoming = useMemo(() => {
    const now = Date.now()
    return items
      .filter((item) => {
        if (item.status === 'COMPLETED' || item.status === 'CANCELLED') return false
        return new Date(item.starts_at).getTime() >= now - 60_000
      })
      .slice(0, 8)
  }, [items])

  const monthCells = useMemo(() => buildMonthCells(cursor), [cursor])
  const weekDays = useMemo(() => buildWeekDays(cursor), [cursor])
  const today = useMemo(() => new Date(), [])

  function clearFilters() {
    setTypeFilter('')
    setStatusFilter('')
    setPriorityFilter('')
    setSourceFilter('')
    setAssigneeFilter('')
    setDepartmentFilter('')
    setSearchDraft('')
    setSearch('')
    setScope('mine')
  }

  async function handleCreate(payload: CalendarItemPayload) {
    setBusy(true)
    setActionError(null)
    try {
      await createCalendarItem({ ...payload, source: payload.source ?? 'MANUAL' })
      toast.success('تمت إضافة العنصر للجدول.')
      setModal({ kind: 'closed' })
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر إضافة العنصر.'))
      throw caught
    } finally {
      setBusy(false)
    }
  }

  async function handleUpdate(item: CalendarItem, payload: CalendarItemPayload) {
    setBusy(true)
    setActionError(null)
    try {
      const id = resolveCalendarItemId(item)
      let scopeOpt = payload.scope
      if (itemHasRecurrence(item) && !scopeOpt) {
        const asked = askRecurrenceScope('تطبيق التعديل')
        if (!asked) {
          setBusy(false)
          return
        }
        scopeOpt = asked
      }
      await updateCalendarItem(id, {
        ...payload,
        scope: scopeOpt,
        occurrence_at: resolveOccurrenceAt(item) ?? undefined,
      })
      toast.success('تم تحديث العنصر.')
      setModal({ kind: 'closed' })
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر تحديث العنصر.'))
      throw caught
    } finally {
      setBusy(false)
    }
  }

  async function handleDelete(item: CalendarItem) {
    let scopeOpt: 'this' | 'future' | 'all' | undefined
    if (itemHasRecurrence(item)) {
      const asked = askRecurrenceScope('حذف')
      if (!asked) return
      scopeOpt = asked
    } else if (!window.confirm('حذف هذا العنصر من التقويم؟')) {
      return
    }

    setBusy(true)
    setActionError(null)
    try {
      await deleteCalendarItem(resolveCalendarItemId(item), {
        scope: scopeOpt,
        occurrence_at: resolveOccurrenceAt(item),
      })
      toast.success('تم حذف العنصر.')
      setModal({ kind: 'closed' })
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر حذف العنصر.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleComplete(item: CalendarItem) {
    setBusy(true)
    setActionError(null)
    try {
      await completeCalendarItem(resolveCalendarItemId(item))
      toast.success('تم إكمال العنصر.')
      setModal({ kind: 'closed' })
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر إكمال العنصر.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleDuplicate(item: CalendarItem, startsAt: string) {
    setBusy(true)
    setActionError(null)
    try {
      await duplicateCalendarItem(resolveCalendarItemId(item), startsAt)
      toast.success('تم إنشاء نسخة.')
      setModal({ kind: 'closed' })
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر تكرار العنصر.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleDropReschedule(item: CalendarItem, day: Date) {
    if (!item.can_edit || item.is_linked) return

    const current = new Date(item.starts_at)
    const next = new Date(day)
    if (item.all_day) {
      next.setHours(0, 0, 0, 0)
    } else {
      next.setHours(current.getHours(), current.getMinutes(), 0, 0)
    }

    let endsAt: string | null | undefined
    if (item.ends_at) {
      const duration = new Date(item.ends_at).getTime() - current.getTime()
      endsAt = item.all_day
        ? `${toDateInput(day)}T00:00:00.000Z`
        : new Date(next.getTime() + Math.max(duration, 0)).toISOString()
    }

    const startsAt = item.all_day ? `${toDateInput(day)}T00:00:00.000Z` : next.toISOString()

    let scopeOpt: 'this' | 'future' | 'all' | undefined
    if (itemHasRecurrence(item)) {
      const asked = askRecurrenceScope('إعادة الجدولة')
      if (!asked) return
      scopeOpt = asked
    }

    setBusy(true)
    setActionError(null)
    try {
      await rescheduleCalendarItem(resolveCalendarItemId(item), {
        starts_at: startsAt,
        ends_at: endsAt,
        scope: scopeOpt,
        occurrence_at: resolveOccurrenceAt(item),
      })
      toast.success('تمت إعادة الجدولة.')
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر إعادة الجدولة.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleExportIcs() {
    setExporting(true)
    setActionError(null)
    try {
      await exportCalendarIcs({ from: range.from, to: range.to, scope })
      toast.success('تم تنزيل ملف ICS.')
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر تصدير ICS.'))
    } finally {
      setExporting(false)
    }
  }

  function openCreateForDay(day: Date, type?: string) {
    const starts = new Date(day)
    starts.setHours(9, 0, 0, 0)
    setModal({ kind: 'create', startsAt: starts, type })
  }

  function openQuickCreate(type: string) {
    setModal({ kind: 'create', startsAt: new Date(), type })
  }

  function renderItemChip(item: CalendarItem) {
    const draggable = item.can_edit && !item.is_linked
    return (
      <button
        key={String(item.id)}
        type="button"
        draggable={draggable}
        onDragStart={(event) => {
          if (!draggable) {
            event.preventDefault()
            return
          }
          event.stopPropagation()
          event.dataTransfer.setData('text/calendar-item-id', String(item.id))
          event.dataTransfer.effectAllowed = 'move'
        }}
        onClick={(event) => {
          event.stopPropagation()
          setModal({ kind: 'detail', item })
        }}
        className={`block w-full truncate rounded-lg border px-1.5 py-0.5 text-start text-[11px] leading-4 ${calendarItemChipClass(item)}`}
        title={item.title}
      >
        {!item.all_day ? `${formatTimeShort(item.starts_at)} · ` : ''}
        {item.title}
        {item.source && item.source !== 'MANUAL' ? (
          <span className={`ms-1 inline rounded border px-1 text-[9px] ${calendarSourceBadgeClass(item.source)}`}>
            {calendarSourceLabel(item.source)}
          </span>
        ) : null}
      </button>
    )
  }

  function renderAgendaList(dayItems: [string, CalendarItem[]][]) {
    if (dayItems.length === 0) {
      return <DashboardEmptyState title="لا عناصر في هذه الفترة." description="أضف مهمة أو اجتماعاً أو متابعة للجدول." />
    }

    return (
      <div className="space-y-3">
        {dayItems.map(([day, list]) => (
          <section key={day} className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2 print:hidden">
              <h3 className="font-semibold text-slate-900">
                {day === 'unknown' ? 'بدون تاريخ' : formatDayTitle(new Date(`${day}T12:00:00`))}
              </h3>
              {day !== 'unknown' ? (
                <button
                  type="button"
                  className="text-xs text-amber-800 underline"
                  onClick={() => openCreateForDay(new Date(`${day}T12:00:00`))}
                >
                  + إضافة لهذا اليوم
                </button>
              ) : null}
            </div>
            <ul className="space-y-2">
              {list.map((item) => (
                <li key={String(item.id)}>
                  <button
                    type="button"
                    onClick={() => setModal({ kind: 'detail', item })}
                    className={`flex w-full flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2 text-start text-sm hover:bg-slate-50 ${
                      item.status === 'OVERDUE'
                        ? 'border-red-300/80'
                        : item.status === 'COMPLETED'
                          ? 'border-slate-100 opacity-60'
                          : 'border-slate-100'
                    }`}
                  >
                    <div className="space-y-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge
                          status={item.status}
                          label={calendarStatusLabel(item.status)}
                          tone={calendarStatusTone(item.status)}
                        />
                        <span className="font-medium">{item.title}</span>
                        {item.source && item.source !== 'MANUAL' ? (
                          <span className={`rounded-full border px-2 py-0.5 text-[10px] ${calendarSourceBadgeClass(item.source)}`}>
                            {calendarSourceLabel(item.source)}
                          </span>
                        ) : null}
                      </div>
                      <p className="text-xs text-slate-500">
                        {calendarTypeLabel(item.type)}
                        {' · '}
                        {item.all_day ? 'يوم كامل' : formatTimeShort(item.starts_at)}
                        {item.assignees[0]?.name ? ` · ${item.assignees[0].name}` : ''}
                      </p>
                    </div>
                  </button>
                </li>
              ))}
            </ul>
          </section>
        ))}
      </div>
    )
  }

  const agendaEntries = useMemo(
    () => [...itemsByDay.entries()].sort(([a], [b]) => a.localeCompare(b)),
    [itemsByDay],
  )

  const title =
    view === 'day'
      ? formatDayTitle(cursor)
      : view === 'week'
        ? `أسبوع ${formatMonthTitle(cursor)}`
        : view === 'agenda'
          ? `قائمة ${formatMonthTitle(cursor)}`
          : formatMonthTitle(cursor)

  const tabs: { key: PageTab; label: string }[] = [
    { key: 'calendar', label: 'التقويم' },
    { key: 'tasks', label: 'المهام' },
    { key: 'workload', label: 'عبء الفريق' },
    { key: 'kanban', label: 'كانبان' },
  ]

  return (
    <div className="min-w-0 space-y-4 overflow-x-hidden">
      <DashboardSection
        title="التقويم التشغيلي"
        description="مهام واجتماعات ومتابعات الفريق — تقويم ومهام وعبء وكانبان."
        action={
          <div className="print:hidden flex flex-wrap gap-2">
            {CALENDAR_QUICK_TYPES.map((type) => (
              <button
                key={type}
                type="button"
                className="min-h-10 rounded-xl border border-amber-300 bg-amber-50 px-3 text-sm text-amber-950"
                onClick={() => openQuickCreate(type)}
              >
                + {calendarTypeLabel(type)}
              </button>
            ))}
          </div>
        }
      >
        <div className="space-y-4">
          <div className="print:hidden flex flex-wrap gap-2">
            {tabs.map((tab) => (
              <button
                key={tab.key}
                type="button"
                onClick={() => setPageTab(tab.key)}
                className={`min-h-10 rounded-xl px-4 text-sm ${
                  pageTab === tab.key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
                }`}
              >
                {tab.label}
              </button>
            ))}
            <button
              type="button"
              className="min-h-10 rounded-xl border px-3 text-sm disabled:opacity-60"
              disabled={exporting}
              onClick={() => void handleExportIcs()}
            >
              تصدير ICS
            </button>
            <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => window.print()}>
              طباعة
            </button>
          </div>

          {pageTab === 'calendar' ? (
            <>
              <div className="print:hidden flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <h2 className="text-2xl font-semibold text-slate-900 sm:text-3xl">{title}</h2>
                <div className="flex flex-wrap gap-2">
                  {(
                    [
                      { key: 'month', label: 'الشهر' },
                      { key: 'week', label: 'الأسبوع' },
                      { key: 'day', label: 'اليوم' },
                      { key: 'agenda', label: 'القائمة' },
                    ] as const
                  ).map((entry) => (
                    <button
                      key={entry.key}
                      type="button"
                      onClick={() => setView(entry.key)}
                      className={`min-h-10 rounded-xl px-3 text-sm ${
                        view === entry.key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
                      }`}
                    >
                      {entry.label}
                    </button>
                  ))}
                  <button
                    type="button"
                    className="min-h-10 rounded-xl border px-3 text-sm"
                    onClick={() => setCursor(shiftCursor(view, cursor, -1))}
                  >
                    السابق
                  </button>
                  <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => setCursor(new Date())}>
                    اليوم
                  </button>
                  <button
                    type="button"
                    className="min-h-10 rounded-xl border px-3 text-sm"
                    onClick={() => setCursor(shiftCursor(view, cursor, 1))}
                  >
                    التالي
                  </button>
                </div>
              </div>

              <div className="print:hidden grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                {[
                  { label: 'مهام اليوم', value: summary.today_tasks },
                  { label: 'الاجتماعات', value: summary.today_meetings },
                  { label: 'المتأخر', value: summary.overdue, danger: summary.overdue > 0 },
                  { label: 'القادم', value: summary.upcoming },
                ].map((chip) => (
                  <div
                    key={chip.label}
                    className={`rounded-2xl border px-4 py-3 ${
                      chip.danger
                        ? 'border-red-300 bg-red-50/70'
                        : 'border-slate-200 bg-gradient-to-l from-amber-50/80 to-white'
                    }`}
                  >
                    <p className="text-xs text-slate-500">{chip.label}</p>
                    <p className={`text-2xl font-semibold ${chip.danger ? 'text-red-900' : 'text-slate-900'}`}>
                      {chip.value.toLocaleString('ar-SA')}
                    </p>
                  </div>
                ))}
              </div>

              <div className="print:hidden grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 sm:grid-cols-2 lg:grid-cols-4">
                <select aria-label="النطاق" value={scope} onChange={(event) => setScope(event.target.value as 'mine' | 'team')} className={fieldClass}>
                  <option value="mine">خاص بي</option>
                  <option value="team">الفريق</option>
                </select>
                <select aria-label="النوع" value={typeFilter} onChange={(event) => setTypeFilter(event.target.value)} className={fieldClass}>
                  <option value="">كل الأنواع</option>
                  {CALENDAR_TYPES.map((entry) => (
                    <option key={entry} value={entry}>{calendarTypeLabel(entry)}</option>
                  ))}
                </select>
                <select aria-label="الحالة" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className={fieldClass}>
                  <option value="">كل الحالات</option>
                  {CALENDAR_STATUSES.map((entry) => (
                    <option key={entry} value={entry}>{calendarStatusLabel(entry)}</option>
                  ))}
                </select>
                <select aria-label="الأولوية" value={priorityFilter} onChange={(event) => setPriorityFilter(event.target.value)} className={fieldClass}>
                  <option value="">كل الأولويات</option>
                  {CALENDAR_PRIORITIES.map((entry) => (
                    <option key={entry} value={entry}>{calendarPriorityLabel(entry)}</option>
                  ))}
                </select>
                <select aria-label="المصدر" value={sourceFilter} onChange={(event) => setSourceFilter(event.target.value)} className={fieldClass}>
                  <option value="">كل المصادر</option>
                  {CALENDAR_SOURCES.map((entry) => (
                    <option key={entry} value={entry}>{calendarSourceLabel(entry)}</option>
                  ))}
                </select>
                <select aria-label="المعيّن" value={assigneeFilter} onChange={(event) => setAssigneeFilter(event.target.value)} className={fieldClass}>
                  <option value="">كل المعيّنين</option>
                  {assignees.map((entry) => (
                    <option key={entry.id} value={entry.id}>{entry.name}</option>
                  ))}
                </select>
                <select
                  aria-label="القسم"
                  value={departmentFilter}
                  onChange={(event) => setDepartmentFilter(event.target.value)}
                  className={fieldClass}
                >
                  <option value="">كل الأقسام</option>
                  {departments.map((entry) => (
                    <option key={entry.id} value={entry.id}>{entry.name}</option>
                  ))}
                </select>
                <input
                  aria-label="بحث"
                  placeholder="بحث في العنوان والوصف..."
                  value={searchDraft}
                  onChange={(event) => setSearchDraft(event.target.value)}
                  className={fieldClass}
                />
                <button type="button" className="min-h-10 rounded-lg border border-slate-300 px-3 text-sm" onClick={clearFilters}>
                  مسح الفلاتر
                </button>
              </div>
            </>
          ) : null}

          {actionError ? <FeedbackBanner kind="error">{actionError}</FeedbackBanner> : null}

          {pageTab === 'tasks' ? (
            <CalendarTaskList
              assignees={assignees}
              departments={departments}
              onOpenItem={(item) => setModal({ kind: 'detail', item })}
              onChanged={() => void load()}
            />
          ) : null}

          {pageTab === 'workload' ? (
            <CalendarWorkloadPanel from={range.from} to={range.to} departmentId={departmentFilter || undefined} />
          ) : null}

          {pageTab === 'kanban' ? (
            loading ? (
              <DashboardPanelSkeleton label="جاري تحميل الكانبان..." />
            ) : error ? (
              <DashboardErrorState message={error} onRetry={() => void load()} />
            ) : (
              <CalendarKanban
                items={items}
                onOpenItem={(item) => setModal({ kind: 'detail', item })}
                onChanged={() => void load()}
              />
            )
          ) : null}

          {pageTab === 'calendar' ? (
            <>
              {loading ? <DashboardPanelSkeleton label="جاري تحميل التقويم..." /> : null}
              {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}

              {!loading && !error ? (
                <div className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
                  <div className="min-w-0 space-y-4">
                    {view === 'month' ? (
                      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div className="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-xs font-medium text-slate-600">
                          {CALENDAR_WEEKDAY_LABELS.map((label) => (
                            <div key={label} className="px-1 py-2">
                              {label}
                            </div>
                          ))}
                        </div>
                        <div className="grid grid-cols-7 auto-rows-[minmax(5.5rem,1fr)]">
                          {monthCells.map((day) => {
                            const key = toDateInput(day)
                            const dayItems = itemsByDay.get(key) ?? []
                            const inMonth = day.getMonth() === cursor.getMonth()
                            const isToday = sameDay(day, today)
                            return (
                              <div
                                key={key}
                                role="button"
                                tabIndex={0}
                                onClick={() => openCreateForDay(day)}
                                onKeyDown={(event) => {
                                  if (event.key === 'Enter' || event.key === ' ') openCreateForDay(day)
                                }}
                                onDragOver={(event) => {
                                  event.preventDefault()
                                }}
                                onDrop={(event) => {
                                  event.preventDefault()
                                  event.stopPropagation()
                                  const raw = event.dataTransfer.getData('text/calendar-item-id')
                                  const item = items.find((entry) => String(entry.id) === raw)
                                  if (item) void handleDropReschedule(item, day)
                                }}
                                className={`min-w-0 cursor-pointer border-b border-e border-slate-100 p-1.5 text-start align-top ${
                                  inMonth ? 'bg-white' : 'bg-slate-50/70 text-slate-400'
                                } ${isToday ? 'bg-amber-50/80 ring-2 ring-inset ring-amber-400' : ''}`}
                              >
                                <div className="mb-1 flex items-center justify-between gap-1">
                                  <span className={`text-xs font-semibold ${isToday ? 'text-amber-800' : ''}`}>
                                    {day.getDate().toLocaleString('ar-SA')}
                                  </span>
                                  {dayItems.length > 0 ? (
                                    <span className="rounded-full bg-slate-900 px-1.5 text-[10px] text-white">
                                      {dayItems.length.toLocaleString('ar-SA')}
                                    </span>
                                  ) : null}
                                </div>
                                <div className="space-y-0.5">
                                  {dayItems.slice(0, 3).map(renderItemChip)}
                                  {dayItems.length > 3 ? (
                                    <p className="text-[10px] text-slate-500">+{dayItems.length - 3} المزيد</p>
                                  ) : null}
                                </div>
                              </div>
                            )
                          })}
                        </div>
                      </div>
                    ) : null}

                    {view === 'week' ? (
                      <div className="grid gap-2 md:grid-cols-7">
                        {weekDays.map((day) => {
                          const key = toDateInput(day)
                          const dayItems = itemsByDay.get(key) ?? []
                          const isToday = sameDay(day, today)
                          return (
                            <section
                              key={key}
                              className={`min-w-0 rounded-2xl border bg-white p-2 ${
                                isToday ? 'border-amber-400 ring-1 ring-amber-300' : 'border-slate-200'
                              }`}
                              onDragOver={(event) => event.preventDefault()}
                              onDrop={(event) => {
                                event.preventDefault()
                                const raw = event.dataTransfer.getData('text/calendar-item-id')
                                const item = items.find((entry) => String(entry.id) === raw)
                                if (item) void handleDropReschedule(item, day)
                              }}
                            >
                              <button
                                type="button"
                                className="mb-2 w-full rounded-xl bg-slate-50 px-2 py-1 text-start text-xs font-semibold print:hidden"
                                onClick={() => openCreateForDay(day)}
                              >
                                {day.toLocaleDateString('ar-SA', { weekday: 'short', day: 'numeric' })}
                              </button>
                              <div className="space-y-1">
                                {dayItems.length === 0 ? (
                                  <p className="text-[11px] text-slate-400">فارغ</p>
                                ) : (
                                  dayItems.map(renderItemChip)
                                )}
                              </div>
                            </section>
                          )
                        })}
                      </div>
                    ) : null}

                    {view === 'day' ? (
                      <section className="rounded-2xl border border-slate-200 bg-white p-4">
                        <div className="mb-3 flex items-center justify-between gap-2 print:hidden">
                          <h3 className="font-semibold">{formatDayTitle(cursor)}</h3>
                          <button type="button" className="text-sm text-amber-800 underline" onClick={() => openCreateForDay(cursor)}>
                            + إضافة
                          </button>
                        </div>
                        {(itemsByDay.get(toDateInput(cursor)) ?? []).length === 0 ? (
                          <DashboardEmptyState title="لا عناصر لهذا اليوم." description="اضغط إضافة لجدولة مهمة أو اجتماع." />
                        ) : (
                          renderAgendaList([[toDateInput(cursor), itemsByDay.get(toDateInput(cursor)) ?? []]])
                        )}
                      </section>
                    ) : null}

                    {view === 'agenda' ? renderAgendaList(agendaEntries) : null}
                  </div>

                  <aside className="print:hidden min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 className="font-semibold text-slate-900">القادم</h3>
                    {upcoming.length === 0 ? (
                      <p className="text-sm text-slate-500">لا عناصر قادمة في هذه الفترة.</p>
                    ) : (
                      <ul className="space-y-2">
                        {upcoming.map((item) => (
                          <li key={`up-${String(item.id)}`}>
                            <button
                              type="button"
                              onClick={() => setModal({ kind: 'detail', item })}
                              className="w-full rounded-xl border border-slate-100 px-3 py-2 text-start text-sm hover:bg-slate-50"
                            >
                              <p className="font-medium text-slate-900">{item.title}</p>
                              <p className="text-xs text-slate-500">
                                {calendarTypeLabel(item.type)} · {formatTimeShort(item.starts_at)}
                              </p>
                            </button>
                          </li>
                        ))}
                      </ul>
                    )}
                  </aside>
                </div>
              ) : null}
            </>
          ) : null}
        </div>
      </DashboardSection>

      {modal.kind === 'create' ? (
        <CalendarItemModal
          key={`create-${modal.startsAt.toISOString()}-${modal.type ?? 'TASK'}`}
          mode="create"
          assignees={assignees}
          defaultStartsAt={modal.startsAt}
          defaultType={modal.type}
          busy={busy}
          error={actionError}
          onClose={() => setModal({ kind: 'closed' })}
          onSubmit={handleCreate}
        />
      ) : null}

      {modal.kind === 'detail' ? (
        <CalendarItemModal
          key={`detail-${String(modal.item.id)}`}
          mode="detail"
          item={modal.item}
          assignees={assignees}
          busy={busy}
          error={actionError}
          onClose={() => setModal({ kind: 'closed' })}
          onSubmit={async () => undefined}
          onEdit={() => setModal({ kind: 'edit', item: modal.item })}
          onDelete={() => handleDelete(modal.item)}
          onComplete={() => handleComplete(modal.item)}
          onDuplicate={(startsAt) => handleDuplicate(modal.item, startsAt)}
          onRefreshItem={async () => {
            const response = await getCalendarItem(resolveCalendarItemId(modal.item))
            setModal({ kind: 'detail', item: response.data })
          }}
        />
      ) : null}

      {modal.kind === 'edit' ? (
        <CalendarItemModal
          key={`edit-${String(modal.item.id)}`}
          mode="edit"
          item={modal.item}
          assignees={assignees}
          busy={busy}
          error={actionError}
          onClose={() => setModal({ kind: 'closed' })}
          onSubmit={(payload) => handleUpdate(modal.item, payload)}
        />
      ) : null}
    </div>
  )
}
