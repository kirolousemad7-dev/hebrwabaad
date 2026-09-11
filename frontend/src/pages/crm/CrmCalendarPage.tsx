import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useAuth } from '../../context/AuthContext'
import { getCrmCalendar, type CrmCalendarEvent } from '../../services/crm'
import { describeApiError } from '../../utils/errors'

type ViewMode = 'day' | 'week' | 'month'

function toDateInput(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function startOfWeek(date: Date): Date {
  const copy = new Date(date)
  const day = copy.getDay()
  const diff = day === 0 ? 6 : day - 1
  copy.setDate(copy.getDate() - diff)
  copy.setHours(0, 0, 0, 0)
  return copy
}

function endOfWeek(date: Date): Date {
  const start = startOfWeek(date)
  const end = new Date(start)
  end.setDate(start.getDate() + 6)
  end.setHours(23, 59, 59, 999)
  return end
}

function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

function endOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0, 23, 59, 59, 999)
}

function rangeFor(view: ViewMode, cursor: Date): { from: string; to: string } {
  if (view === 'day') {
    const d = toDateInput(cursor)
    return { from: d, to: d }
  }
  if (view === 'week') {
    return { from: toDateInput(startOfWeek(cursor)), to: toDateInput(endOfWeek(cursor)) }
  }
  return { from: toDateInput(startOfMonth(cursor)), to: toDateInput(endOfMonth(cursor)) }
}

function shiftCursor(view: ViewMode, cursor: Date, direction: -1 | 1): Date {
  const next = new Date(cursor)
  if (view === 'day') next.setDate(next.getDate() + direction)
  else if (view === 'week') next.setDate(next.getDate() + direction * 7)
  else next.setMonth(next.getMonth() + direction)
  return next
}

const TYPE_LABELS: Record<string, string> = {
  follow_up: 'متابعة',
  activity: 'نشاط',
  expected_close: 'إغلاق متوقع',
}

export function CrmCalendarPage() {
  const { user } = useAuth()
  const [view, setView] = useState<ViewMode>('week')
  const [cursor, setCursor] = useState(() => new Date())
  const range = useMemo(() => rangeFor(view, cursor), [view, cursor])
  const [events, setEvents] = useState<CrmCalendarEvent[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmCalendar(range.from, range.to)
      setEvents(response.data.events ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل التقويم.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [range.from, range.to])

  const grouped = useMemo(() => {
    const map = new Map<string, CrmCalendarEvent[]>()
    for (const event of events) {
      const key = event.starts_at ? event.starts_at.slice(0, 10) : 'unknown'
      const list = map.get(key) ?? []
      list.push(event)
      map.set(key, list)
    }
    return [...map.entries()].sort(([a], [b]) => a.localeCompare(b))
  }, [events])

  return (
    <DashboardSection
      title="التقويم"
      description={`من ${range.from} إلى ${range.to}`}
      action={
        <div className="flex flex-wrap gap-2">
          {user?.role === 'OWNER' ? (
            <Link
              to="/owner/calendar"
              className="min-h-10 rounded-xl border border-amber-300 bg-amber-50 px-3 text-sm leading-10 text-amber-950"
            >
              التقويم التشغيلي
            </Link>
          ) : null}
          {(['day', 'week', 'month'] as const).map((mode) => (
            <button
              key={mode}
              type="button"
              onClick={() => setView(mode)}
              className={`min-h-10 rounded-xl px-3 text-sm ${
                view === mode ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
              }`}
            >
              {mode === 'day' ? 'يوم' : mode === 'week' ? 'أسبوع' : 'شهر'}
            </button>
          ))}
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => setCursor(shiftCursor(view, cursor, -1))}>
            السابق
          </button>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => setCursor(new Date())}>
            اليوم
          </button>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => setCursor(shiftCursor(view, cursor, 1))}>
            التالي
          </button>
        </div>
      }
    >
      {loading ? <DashboardPanelSkeleton label="جاري تحميل التقويم..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && events.length === 0 ? (
        <DashboardEmptyState title="لا أحداث في هذه الفترة." description="المتابعات والاجتماعات والإغلاقات المتوقعة تظهر هنا." />
      ) : null}

      {!loading && !error && grouped.length > 0 ? (
        <div className="space-y-4">
          {grouped.map(([day, dayEvents]) => (
            <section key={day} className="rounded-2xl border bg-white p-4">
              <h2 className="mb-3 font-semibold">
                {day === 'unknown' ? 'بدون تاريخ' : new Date(day).toLocaleDateString('ar-SA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
              </h2>
              <ul className="space-y-2">
                {dayEvents.map((event) => (
                  <li key={event.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm">
                    <div className="space-y-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={event.type} label={TYPE_LABELS[event.type] ?? event.type} tone="progress" />
                        <span className="font-medium">{event.title}</span>
                      </div>
                      <p className="text-xs text-slate-500">
                        {event.starts_at ? new Date(event.starts_at).toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' }) : '—'}
                      </p>
                    </div>
                    {event.href || event.lead_id ? (
                      <Link to={event.href || `/crm/leads/${event.lead_id}`} className="rounded-lg border px-2 py-1 text-xs">
                        فتح
                      </Link>
                    ) : null}
                  </li>
                ))}
              </ul>
            </section>
          ))}
        </div>
      ) : null}
    </DashboardSection>
  )
}
