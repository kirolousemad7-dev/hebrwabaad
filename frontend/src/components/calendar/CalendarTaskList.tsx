import { useEffect, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { StatusBadge } from '../ui/StatusBadge'
import {
  bulkUpdateCalendarItems,
  completeCalendarItem,
  getCalendarTasks,
  type CalendarAssignee,
  type CalendarItem,
  type CalendarTaskFilters,
} from '../../services/calendar'
import type { DepartmentOption } from '../../services/operations'
import {
  CALENDAR_PRIORITIES,
  CALENDAR_STATUSES,
  calendarPriorityLabel,
  calendarStatusLabel,
  calendarStatusTone,
  calendarTypeLabel,
} from '../../utils/calendarLabels'
import { formatDateTimeShort } from '../../utils/calendarDates'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type CalendarTaskListProps = {
  assignees: CalendarAssignee[]
  departments?: DepartmentOption[]
  onOpenItem: (item: CalendarItem) => void
  onChanged?: () => void
}

export function CalendarTaskList({ assignees, departments = [], onOpenItem, onChanged }: CalendarTaskListProps) {
  const [scope, setScope] = useState<'mine' | 'team'>('mine')
  const [status, setStatus] = useState('')
  const [priority, setPriority] = useState('')
  const [assigneeId, setAssigneeId] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [q, setQ] = useState('')
  const [qDraft, setQDraft] = useState('')
  const [sort, setSort] = useState('starts_at')
  const [page, setPage] = useState(1)
  const [items, setItems] = useState<CalendarItem[]>([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [bulkStatus, setBulkStatus] = useState('')
  const [bulkPriority, setBulkPriority] = useState('')

  useEffect(() => {
    const handle = window.setTimeout(() => {
      setQ(qDraft.trim())
      setPage(1)
    }, 300)
    return () => window.clearTimeout(handle)
  }, [qDraft])

  async function load(filters?: Partial<CalendarTaskFilters>) {
    setLoading(true)
    setError(null)
    try {
      const response = await getCalendarTasks({
        scope,
        status: status || undefined,
        priority: priority || undefined,
        assignee_id: assigneeId || undefined,
        department_id: departmentId || undefined,
        q: q || undefined,
        sort,
        page,
        per_page: 20,
        ...filters,
      })
      setItems(response.data.items ?? [])
      setMeta(response.data.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: 0 })
      setSelected(new Set())
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل قائمة المهام.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [scope, status, priority, assigneeId, departmentId, q, sort, page])

  function toggleOne(id: string) {
    setSelected((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  function toggleAll() {
    if (selected.size === items.length) {
      setSelected(new Set())
      return
    }
    setSelected(new Set(items.map((item) => String(item.id))))
  }

  async function quickComplete(item: CalendarItem) {
    setBusy(true)
    setError(null)
    try {
      await completeCalendarItem(item.id)
      await load()
      onChanged?.()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إكمال المهمة.'))
    } finally {
      setBusy(false)
    }
  }

  async function applyBulk() {
    if (selected.size === 0) return
    const changes: { status?: string; priority?: string } = {}
    if (bulkStatus) changes.status = bulkStatus
    if (bulkPriority) changes.priority = bulkPriority
    if (!changes.status && !changes.priority) {
      setError('اختر حالة أو أولوية للتحديث الجماعي.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await bulkUpdateCalendarItems([...selected], changes)
      await load()
      onChanged?.()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر التحديث الجماعي.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="min-w-0 space-y-3">
      <div className="grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 sm:grid-cols-2 lg:grid-cols-4">
        <select aria-label="النطاق" value={scope} onChange={(e) => { setScope(e.target.value as 'mine' | 'team'); setPage(1) }} className={fieldClass}>
          <option value="mine">خاص بي</option>
          <option value="team">الفريق</option>
        </select>
        <select aria-label="الحالة" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className={fieldClass}>
          <option value="">كل الحالات</option>
          {CALENDAR_STATUSES.map((entry) => (
            <option key={entry} value={entry}>{calendarStatusLabel(entry)}</option>
          ))}
        </select>
        <select aria-label="الأولوية" value={priority} onChange={(e) => { setPriority(e.target.value); setPage(1) }} className={fieldClass}>
          <option value="">كل الأولويات</option>
          {CALENDAR_PRIORITIES.map((entry) => (
            <option key={entry} value={entry}>{calendarPriorityLabel(entry)}</option>
          ))}
        </select>
        <select aria-label="المعيّن" value={assigneeId} onChange={(e) => { setAssigneeId(e.target.value); setPage(1) }} className={fieldClass}>
          <option value="">كل المعيّنين</option>
          {assignees.map((entry) => (
            <option key={entry.id} value={entry.id}>{entry.name}</option>
          ))}
        </select>
        <select
          aria-label="القسم"
          value={departmentId}
          onChange={(e) => {
            setDepartmentId(e.target.value)
            setPage(1)
          }}
          className={fieldClass}
        >
          <option value="">كل الأقسام</option>
          {departments.map((entry) => (
            <option key={entry.id} value={entry.id}>
              {entry.name}
            </option>
          ))}
        </select>
        <input
          aria-label="بحث"
          placeholder="بحث في المهام..."
          value={qDraft}
          onChange={(e) => setQDraft(e.target.value)}
          className={`${fieldClass} sm:col-span-2`}
        />
        <select aria-label="الترتيب" value={sort} onChange={(e) => setSort(e.target.value)} className={fieldClass}>
          <option value="starts_at">حسب الموعد</option>
          <option value="priority">حسب الأولوية</option>
          <option value="status">حسب الحالة</option>
        </select>
        <p className="flex items-center text-xs text-slate-500">
          {meta.total.toLocaleString('ar-SA')} مهمة
        </p>
      </div>

      {selected.size > 0 ? (
        <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50/60 p-3">
          <span className="text-sm text-amber-950">{selected.size.toLocaleString('ar-SA')} محددة</span>
          <select aria-label="حالة جماعية" value={bulkStatus} onChange={(e) => setBulkStatus(e.target.value)} className={`${fieldClass} max-w-[10rem]`}>
            <option value="">حالة...</option>
            {CALENDAR_STATUSES.filter((s) => s !== 'OVERDUE').map((entry) => (
              <option key={entry} value={entry}>{calendarStatusLabel(entry)}</option>
            ))}
          </select>
          <select aria-label="أولوية جماعية" value={bulkPriority} onChange={(e) => setBulkPriority(e.target.value)} className={`${fieldClass} max-w-[10rem]`}>
            <option value="">أولوية...</option>
            {CALENDAR_PRIORITIES.map((entry) => (
              <option key={entry} value={entry}>{calendarPriorityLabel(entry)}</option>
            ))}
          </select>
          <button type="button" disabled={busy} className="min-h-10 rounded-xl bg-slate-900 px-3 text-sm text-white disabled:opacity-60" onClick={() => void applyBulk()}>
            تطبيق
          </button>
        </div>
      ) : null}

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {loading ? (
        <p className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">جاري تحميل المهام...</p>
      ) : items.length === 0 ? (
        <p className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">لا مهام مطابقة.</p>
      ) : (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
          <div className="flex items-center gap-2 border-b border-slate-100 px-3 py-2 text-xs text-slate-500">
            <label className="inline-flex items-center gap-2">
              <input type="checkbox" checked={selected.size === items.length && items.length > 0} onChange={toggleAll} />
              تحديد الكل
            </label>
          </div>
          <ul className="divide-y divide-slate-100">
            {items.map((item) => {
              const id = String(item.id)
              return (
                <li key={id} className="flex flex-wrap items-center gap-2 px-3 py-3">
                  <input type="checkbox" checked={selected.has(id)} onChange={() => toggleOne(id)} aria-label={`تحديد ${item.title}`} />
                  <button type="button" className="min-w-0 flex-1 text-start" onClick={() => onOpenItem(item)}>
                    <div className="flex flex-wrap items-center gap-2">
                      <StatusBadge status={item.status} label={calendarStatusLabel(item.status)} tone={calendarStatusTone(item.status)} />
                      <span className="font-medium text-slate-900">{item.title}</span>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                      {calendarTypeLabel(item.type)} · {formatDateTimeShort(item.starts_at)}
                      {item.assignees[0]?.name ? ` · ${item.assignees[0].name}` : ''}
                      {item.priority ? ` · ${calendarPriorityLabel(item.priority)}` : ''}
                    </p>
                  </button>
                  {item.can_edit && !item.is_linked && item.status !== 'COMPLETED' && item.status !== 'CANCELLED' ? (
                    <button
                      type="button"
                      disabled={busy}
                      className="min-h-9 rounded-xl border border-emerald-300 px-3 text-xs text-emerald-900 disabled:opacity-60"
                      onClick={() => void quickComplete(item)}
                    >
                      إكمال
                    </button>
                  ) : null}
                </li>
              )
            })}
          </ul>
        </div>
      )}

      {meta.last_page > 1 ? (
        <div className="flex flex-wrap items-center justify-between gap-2">
          <button
            type="button"
            className="min-h-10 rounded-xl border px-3 text-sm disabled:opacity-50"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            السابق
          </button>
          <span className="text-xs text-slate-500">
            صفحة {meta.current_page.toLocaleString('ar-SA')} من {meta.last_page.toLocaleString('ar-SA')}
          </span>
          <button
            type="button"
            className="min-h-10 rounded-xl border px-3 text-sm disabled:opacity-50"
            disabled={page >= meta.last_page}
            onClick={() => setPage((p) => p + 1)}
          >
            التالي
          </button>
        </div>
      ) : null}
    </div>
  )
}
