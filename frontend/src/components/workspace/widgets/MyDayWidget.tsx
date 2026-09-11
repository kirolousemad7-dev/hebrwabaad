import { Link } from 'react-router-dom'
import { useState } from 'react'
import { useAsyncData } from '../../../hooks/useAsyncData'
import {
  completeWork,
  getMyDay,
  getWorkFocus,
  isUnifiedWorkItem,
  startWork,
  type UnifiedWorkItem,
} from '../../../services/operations'
import type { CalendarItem } from '../../../services/calendar'
import { formatTimeShort } from '../../../utils/calendarDates'
import { calendarStatusLabel, calendarTypeLabel } from '../../../utils/calendarLabels'
import { describeApiError } from '../../../utils/errors'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

function itemKey(item: UnifiedWorkItem | CalendarItem): string {
  return isUnifiedWorkItem(item) ? item.id : `cal-${item.id}`
}

export function MyDayWidget() {
  const { state, reload } = useAsyncData(async () => {
    const [myDay, focus] = await Promise.all([
      getMyDay(),
      getWorkFocus(5).catch(() => ({ data: { items: [] as UnifiedWorkItem[] } })),
    ])
    return { data: { myDay: myDay.data, focus: focus.data.items ?? [] } }
  })
  const [busyId, setBusyId] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  async function handleComplete(item: UnifiedWorkItem) {
    setBusyId(item.id)
    setActionError(null)
    try {
      await completeWork(item.id)
      await reload()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر الإكمال.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleStart(item: UnifiedWorkItem) {
    setBusyId(item.id)
    setActionError(null)
    try {
      await startWork(item.id)
      await reload()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر البدء.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <WorkspaceWidget title="يومي">
      {state.status === 'loading' ? <div className="h-28 animate-pulse rounded-xl bg-slate-100" aria-busy="true" /> : null}
      {state.status === 'error' ? <WorkspaceErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' ? (
        <div className="space-y-3 text-sm">
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {[
              { label: 'اليوم', value: state.data.myDay.counts.today },
              { label: 'متأخر', value: state.data.myDay.counts.overdue, danger: state.data.myDay.counts.overdue > 0 },
              { label: 'اجتماعات', value: state.data.myDay.counts.meetings_today },
              { label: 'قادم', value: state.data.myDay.counts.upcoming },
            ].map((chip) => (
              <div
                key={chip.label}
                className={`rounded-xl border px-2.5 py-2 ${
                  chip.danger ? 'border-red-300 bg-red-50/70' : 'border-slate-200 bg-white'
                }`}
              >
                <p className="text-xs text-slate-500">{chip.label}</p>
                <p className={`text-lg font-semibold ${chip.danger ? 'text-red-900' : 'text-slate-900'}`}>
                  {chip.value.toLocaleString('ar-SA')}
                </p>
              </div>
            ))}
          </div>

          {state.data.focus.length > 0 ? (
            <div className="rounded-xl border border-amber-200 bg-amber-50/40 px-3 py-2">
              <p className="mb-2 text-xs font-semibold text-amber-900">تركيز</p>
              <ul className="space-y-1.5">
                {state.data.focus.slice(0, 5).map((item) => (
                  <li key={`focus-${item.id}`} className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-medium text-slate-900">{item.title}</span>
                    <span className="flex gap-1">
                      {item.capabilities.can_start && !item.is_completed ? (
                        <button
                          type="button"
                          disabled={busyId === item.id}
                          onClick={() => void handleStart(item)}
                          className="rounded border border-slate-300 bg-white px-2 py-0.5 text-[11px] disabled:opacity-50"
                        >
                          بدء
                        </button>
                      ) : null}
                      {item.capabilities.can_complete && !item.is_completed ? (
                        <button
                          type="button"
                          disabled={busyId === item.id}
                          onClick={() => void handleComplete(item)}
                          className="rounded bg-slate-900 px-2 py-0.5 text-[11px] text-white disabled:opacity-50"
                        >
                          إكمال
                        </button>
                      ) : null}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          {actionError ? <p className="text-xs text-red-700">{actionError}</p> : null}

          {state.data.myDay.items.length === 0 ? (
            <WorkspaceEmptyState title="لا عناصر لليوم." description="ستظهر هنا مهامك واجتماعاتك ذات الأولوية." />
          ) : (
            <ul className="space-y-2">
              {state.data.myDay.items.slice(0, 6).map((item) => {
                if (isUnifiedWorkItem(item)) {
                  return (
                    <li key={itemKey(item)} className="rounded-xl border border-slate-200 px-3 py-2">
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                          <p className="font-medium text-slate-900">{item.title}</p>
                          <p className="mt-0.5 text-xs text-slate-500">
                            {item.source_badge === 'task' ? 'مهمة مشروع' : 'تقويم'} · {item.status}
                            {item.due_at ? ` · ${item.due_at}` : ''}
                          </p>
                        </div>
                        <div className="flex gap-1">
                          {item.capabilities.can_start && !item.is_completed ? (
                            <button
                              type="button"
                              disabled={busyId === item.id}
                              onClick={() => void handleStart(item)}
                              className="rounded border border-slate-300 px-2 py-0.5 text-[11px] disabled:opacity-50"
                            >
                              بدء
                            </button>
                          ) : null}
                          {item.capabilities.can_complete && !item.is_completed ? (
                            <button
                              type="button"
                              disabled={busyId === item.id}
                              onClick={() => void handleComplete(item)}
                              className="rounded bg-slate-900 px-2 py-0.5 text-[11px] text-white disabled:opacity-50"
                            >
                              إكمال
                            </button>
                          ) : null}
                        </div>
                      </div>
                    </li>
                  )
                }

                return (
                  <li key={itemKey(item)} className="rounded-xl border border-slate-200 px-3 py-2">
                    <p className="font-medium text-slate-900">{item.title}</p>
                    <p className="mt-0.5 text-xs text-slate-500">
                      {calendarTypeLabel(item.type)} · {calendarStatusLabel(item.status)} · {formatTimeShort(item.starts_at)}
                    </p>
                  </li>
                )
              })}
            </ul>
          )}

          <div className="flex flex-wrap gap-3">
            <Link
              to="/workspace/work"
              className="inline-flex min-h-11 items-center text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            >
              فتح العمل
            </Link>
            <Link
              to="/workspace/calendar"
              className="inline-flex min-h-11 items-center text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            >
              فتح التقويم
            </Link>
          </div>
        </div>
      ) : null}
    </WorkspaceWidget>
  )
}
