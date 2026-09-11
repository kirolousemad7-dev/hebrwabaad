import { useEffect, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { getCalendarWorkload, type CalendarWorkloadData } from '../../services/calendar'
import {
  calendarWorkloadLevel,
  calendarWorkloadLevelClass,
  calendarWorkloadLevelLabel,
} from '../../utils/calendarLabels'
import { describeApiError } from '../../utils/errors'

type CalendarWorkloadPanelProps = {
  from: string
  to: string
  departmentId?: number | string | null
}

export function CalendarWorkloadPanel({ from, to, departmentId }: CalendarWorkloadPanelProps) {
  const [data, setData] = useState<CalendarWorkloadData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)
    void getCalendarWorkload(from, to, departmentId)
      .then((response) => {
        if (cancelled) return
        setData(response.data)
      })
      .catch((caught) => {
        if (cancelled) return
        setError(describeApiError(caught, 'تعذر تحميل عبء الفريق.'))
        setData(null)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [from, to, departmentId])

  if (loading) {
    return <p className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">جاري تحميل عبء الفريق...</p>
  }

  if (error) {
    return <FeedbackBanner kind="error">{error}</FeedbackBanner>
  }

  if (!data) {
    return <p className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">لا بيانات.</p>
  }

  const rows = [...(data.by_assignee ?? [])].sort((a, b) => b.count - a.count)

  return (
    <div className="min-w-0 space-y-4">
      <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
        {[
          { label: 'إجمالي العناصر', value: data.totals.items },
          { label: 'المهام', value: data.totals.tasks },
          { label: 'الاجتماعات', value: data.totals.meetings },
          { label: 'المتأخر', value: data.totals.overdue },
        ].map((chip) => (
          <div key={chip.label} className="rounded-2xl border border-slate-200 bg-gradient-to-l from-amber-50/80 to-white px-4 py-3">
            <p className="text-xs text-slate-500">{chip.label}</p>
            <p className="text-2xl font-semibold text-slate-900">{chip.value.toLocaleString('ar-SA')}</p>
          </div>
        ))}
      </div>

      {rows.length === 0 ? (
        <p className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">لا عناصر في هذه الفترة.</p>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {rows.map((row) => {
            const level = calendarWorkloadLevel(row.count)
            return (
              <article
                key={row.id ?? 'unassigned'}
                className={`rounded-2xl border p-4 ${calendarWorkloadLevelClass(level)}`}
              >
                <div className="flex items-start justify-between gap-2">
                  <h3 className="font-semibold">{row.name}</h3>
                  <span className="rounded-full border border-current/20 bg-white/70 px-2 py-0.5 text-[11px]">
                    {calendarWorkloadLevelLabel(row.count)}
                  </span>
                </div>
                <dl className="mt-3 grid grid-cols-2 gap-2 text-sm">
                  <div>
                    <dt className="text-xs opacity-70">العناصر</dt>
                    <dd className="text-lg font-semibold">{row.count.toLocaleString('ar-SA')}</dd>
                  </div>
                  <div>
                    <dt className="text-xs opacity-70">مهام</dt>
                    <dd className="text-lg font-semibold">{row.tasks.toLocaleString('ar-SA')}</dd>
                  </div>
                </dl>
              </article>
            )
          })}
        </div>
      )}
    </div>
  )
}
