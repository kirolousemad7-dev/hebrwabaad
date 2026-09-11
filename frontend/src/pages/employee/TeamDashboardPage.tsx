import { Link } from 'react-router-dom'
import { useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  completeWork,
  getTeamDashboard,
  startWork,
  type TeamDashboardData,
  type UnifiedWorkItem,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'

function WorkMiniList({
  items,
  onComplete,
  onStart,
  busyId,
}: {
  items: UnifiedWorkItem[]
  onComplete: (item: UnifiedWorkItem) => void
  onStart: (item: UnifiedWorkItem) => void
  busyId: string | null
}) {
  if (items.length === 0) {
    return <p className="text-sm text-slate-500">لا عناصر.</p>
  }

  return (
    <ul className="space-y-2">
      {items.slice(0, 12).map((item) => (
        <li key={item.id} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p className="font-medium text-slate-900">{item.title}</p>
              <p className="text-xs text-slate-500">
                {item.source_badge === 'task' ? 'مهمة مشروع' : 'تقويم'}
                {item.due_at ? ` · ${item.due_at}` : ''}
                {item.is_overdue ? ' · متأخر' : ''}
              </p>
            </div>
            <div className="flex gap-1">
              {item.capabilities.can_start && !item.is_completed ? (
                <button
                  type="button"
                  disabled={busyId === item.id}
                  onClick={() => onStart(item)}
                  className="rounded border border-slate-300 px-2 py-1 text-[11px] disabled:opacity-50"
                >
                  بدء
                </button>
              ) : null}
              {item.capabilities.can_complete && !item.is_completed ? (
                <button
                  type="button"
                  disabled={busyId === item.id}
                  onClick={() => onComplete(item)}
                  className="rounded bg-slate-900 px-2 py-1 text-[11px] text-white disabled:opacity-50"
                >
                  إكمال
                </button>
              ) : null}
            </div>
          </div>
        </li>
      ))}
    </ul>
  )
}

export function TeamDashboardPage() {
  const [data, setData] = useState<TeamDashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [forbidden, setForbidden] = useState(false)
  const [busyId, setBusyId] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setForbidden(false)
    try {
      const response = await getTeamDashboard()
      setData(response.data)
    } catch (caught) {
      const message = describeApiError(caught, 'تعذر تحميل لوحة الفريق.')
      if (typeof caught === 'object' && caught && 'status' in caught && (caught as { status: number }).status === 403) {
        setForbidden(true)
        setError('ليست لديك صلاحية عرض لوحة الفريق.')
      } else {
        setError(message)
      }
      setData(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function handleComplete(item: UnifiedWorkItem) {
    setBusyId(item.id)
    try {
      await completeWork(item.id)
      setNotice('تم الإكمال.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر الإكمال.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleStart(item: UnifiedWorkItem) {
    setBusyId(item.id)
    try {
      await startWork(item.id)
      setNotice('تم البدء.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر البدء.'))
    } finally {
      setBusyId(null)
    }
  }

  if (loading) {
    return <DashboardPanelSkeleton label="جاري تحميل لوحة الفريق..." />
  }

  if (forbidden) {
    return (
      <section className="space-y-4">
        <h1 className="text-2xl font-semibold text-slate-900">لوحة الفريق</h1>
        <DashboardEmptyState title="غير متاح." description={error ?? 'هذه الصفحة للمديرين فقط.'} />
        <Link to="/workspace" className="text-sm underline">
          العودة لمساحة العمل
        </Link>
      </section>
    )
  }

  if (error && !data) {
    return <DashboardErrorState message={error} onRetry={() => void load()} />
  }

  if (!data) {
    return null
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold text-slate-900">لوحة الفريق</h1>
        <p className="mt-1 text-sm text-slate-600">ملخص عمل الفريق اليوم والمتأخر والقادم.</p>
      </header>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <div className="grid gap-3 sm:grid-cols-3">
        {[
          { label: 'اليوم', value: data.today.count },
          { label: 'المتأخر', value: data.overdue.count, danger: data.overdue.count > 0 },
          { label: 'القادم', value: data.upcoming.count },
        ].map((chip) => (
          <div
            key={chip.label}
            className={`rounded-2xl border px-4 py-3 ${
              chip.danger ? 'border-red-300 bg-red-50/70' : 'border-slate-200 bg-gradient-to-l from-amber-50/70 to-white'
            }`}
          >
            <p className="text-xs text-slate-500">{chip.label}</p>
            <p className={`text-2xl font-semibold ${chip.danger ? 'text-red-900' : 'text-slate-900'}`}>
              {chip.value.toLocaleString('ar-SA')}
            </p>
          </div>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <DashboardSection title="اليوم">
          <WorkMiniList items={data.today.items} onComplete={handleComplete} onStart={handleStart} busyId={busyId} />
        </DashboardSection>
        <DashboardSection title="المتأخر">
          <WorkMiniList items={data.overdue.items} onComplete={handleComplete} onStart={handleStart} busyId={busyId} />
        </DashboardSection>
        <DashboardSection title="القادم">
          <WorkMiniList items={data.upcoming.items} onComplete={handleComplete} onStart={handleStart} busyId={busyId} />
        </DashboardSection>
      </div>

      <DashboardSection title="عبء العمل">
        {data.workload.by_assignee.length === 0 ? (
          <p className="text-sm text-slate-500">لا بيانات عبء.</p>
        ) : (
          <ul className="space-y-2">
            {data.workload.by_assignee.map((row) => (
              <li
                key={row.user_id}
                className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm"
              >
                <span>مستخدم #{row.user_id}</span>
                <span className="text-xs text-slate-600">
                  {row.count.toLocaleString('ar-SA')} عنصر · متأخر {row.overdue.toLocaleString('ar-SA')} · عاجل{' '}
                  {row.urgent.toLocaleString('ar-SA')}
                </span>
              </li>
            ))}
          </ul>
        )}
        <p className="mt-3 text-xs text-slate-500">
          الإجمالي: {data.workload.totals.items.toLocaleString('ar-SA')} · متأخر:{' '}
          {data.workload.totals.overdue.toLocaleString('ar-SA')}
        </p>
        <Link to="/workspace/work" className="mt-3 inline-block text-sm underline">
          فتح العمل الموحد
        </Link>
      </DashboardSection>
    </section>
  )
}
