import { FormEvent, useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useToast } from '../../context/ToastContext'
import { createCalendarItem } from '../../services/calendar'
import {
  cancelCrmFollowUp,
  completeCrmFollowUp,
  getCrmFollowUps,
  rescheduleCrmFollowUp,
  type CrmFollowUp,
} from '../../services/crm'
import {
  CRM_FOLLOW_UP_STATUS_LABELS,
  CRM_FOLLOW_UP_TYPE_LABELS,
  crmPriorityLabel,
} from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

type TabKey = 'today' | 'overdue' | 'upcoming'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

function startOfDay(date: Date): Date {
  const copy = new Date(date)
  copy.setHours(0, 0, 0, 0)
  return copy
}

function endOfDay(date: Date): Date {
  const copy = new Date(date)
  copy.setHours(23, 59, 59, 999)
  return copy
}

export function CrmFollowUpsPage() {
  const toast = useToast()
  const [searchParams] = useSearchParams()
  const initialTab: TabKey = searchParams.get('overdue') === '1' ? 'overdue' : 'today'
  const [tab, setTab] = useState<TabKey>(initialTab)
  const [items, setItems] = useState<CrmFollowUp[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [rescheduleId, setRescheduleId] = useState<number | null>(null)

  async function load() {
    setLoading(true)
    setError(null)

    try {
      if (tab === 'overdue') {
        const response = await getCrmFollowUps({ overdue: true, per_page: 50 })
        setItems(response.data.items)
      } else {
        const response = await getCrmFollowUps({ status: 'SCHEDULED', per_page: 50 })
        setItems(response.data.items)
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل المتابعات.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab])

  const filtered = useMemo(() => {
    const now = new Date()
    const todayStart = startOfDay(now).getTime()
    const todayEnd = endOfDay(now).getTime()

    if (tab === 'overdue') return items

    if (tab === 'today') {
      return items.filter((item) => {
        const at = new Date(item.scheduled_at).getTime()
        return at >= todayStart && at <= todayEnd && item.status !== 'COMPLETED' && item.status !== 'CANCELLED'
      })
    }

    return items.filter((item) => {
      const at = new Date(item.scheduled_at).getTime()
      return at > todayEnd && item.status !== 'COMPLETED' && item.status !== 'CANCELLED'
    })
  }, [items, tab])

  async function complete(id: number) {
    setBusyId(id)
    setActionError(null)
    try {
      await completeCrmFollowUp(id)
      toast.success('تم إكمال المتابعة.')
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر إكمال المتابعة.'))
    } finally {
      setBusyId(null)
    }
  }

  async function cancel(id: number) {
    setBusyId(id)
    setActionError(null)
    try {
      await cancelCrmFollowUp(id)
      toast.success('تم إلغاء المتابعة.')
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر الإلغاء.'))
    } finally {
      setBusyId(null)
    }
  }

  async function addToCalendar(followUp: CrmFollowUp) {
    setBusyId(followUp.id)
    setActionError(null)
    try {
      await createCalendarItem({
        title: followUp.lead?.full_name
          ? `متابعة — ${followUp.lead.full_name}`
          : `متابعة #${followUp.id}`,
        description: followUp.notes,
        type: 'FOLLOW_UP',
        priority: followUp.priority || 'MEDIUM',
        source: 'CRM',
        starts_at: followUp.scheduled_at,
        assignee_ids: followUp.assigned_to ? [followUp.assigned_to] : undefined,
        related_type: 'crm_follow_up',
        related_id: followUp.id,
      })
      toast.success('أُضيفت المتابعة إلى الجدول التشغيلي.')
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر إضافة المتابعة للجدول.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleReschedule(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (rescheduleId == null) return
    const form = new FormData(event.currentTarget)
    setBusyId(rescheduleId)
    setActionError(null)
    try {
      await rescheduleCrmFollowUp(
        rescheduleId,
        String(form.get('scheduled_at') || ''),
        String(form.get('notes') || '').trim() || undefined,
      )
      toast.success('تمت إعادة الجدولة.')
      setRescheduleId(null)
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر إعادة الجدولة.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <DashboardSection title="المتابعات" description="اليوم والمتأخرة والقادمة مع إعادة الجدولة والإلغاء.">
      <div className="flex flex-wrap gap-2">
        {(
          [
            { key: 'today', label: 'اليوم' },
            { key: 'overdue', label: 'متأخرة' },
            { key: 'upcoming', label: 'قادمة' },
          ] as const
        ).map((item) => (
          <button
            key={item.key}
            type="button"
            onClick={() => setTab(item.key)}
            className={`min-h-10 rounded-xl px-3 text-sm ${
              tab === item.key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {loading ? <DashboardPanelSkeleton label="جاري تحميل المتابعات..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {actionError ? <FeedbackBanner kind="error">{actionError}</FeedbackBanner> : null}

      {rescheduleId != null ? (
        <form onSubmit={(event) => void handleReschedule(event)} className="grid gap-3 rounded-2xl border border-amber-200 bg-amber-50/40 p-4 sm:grid-cols-2">
          <h3 className="font-semibold sm:col-span-2">إعادة جدولة المتابعة #{rescheduleId}</h3>
          <input required name="scheduled_at" type="datetime-local" className={fieldClass} />
          <input name="notes" placeholder="ملاحظات" className={fieldClass} />
          <div className="flex gap-2 sm:col-span-2">
            <button type="submit" disabled={busyId === rescheduleId} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
              حفظ
            </button>
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setRescheduleId(null)}>
              إلغاء
            </button>
          </div>
        </form>
      ) : null}

      {!loading && !error && filtered.length === 0 ? (
        <DashboardEmptyState title="لا متابعات في هذه القائمة." description="جدولة متابعة من صفحة العميل المحتمل." />
      ) : null}

      {!loading && !error && filtered.length > 0 ? (
        <ul className="space-y-3">
          {filtered.map((followUp) => (
            <li key={followUp.id} className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-white px-4 py-3 text-sm">
              <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                  <StatusBadge
                    status={followUp.status}
                    label={CRM_FOLLOW_UP_STATUS_LABELS[followUp.status] ?? followUp.status}
                  />
                  <span>{CRM_FOLLOW_UP_TYPE_LABELS[followUp.type] ?? followUp.type}</span>
                  <span className="text-slate-500">{crmPriorityLabel(followUp.priority)}</span>
                </div>
                <p className="font-medium">
                  {followUp.lead ? (
                    <Link to={`/crm/leads/${followUp.lead.id}`} className="hover:underline">
                      {followUp.lead.full_name}
                    </Link>
                  ) : (
                    `متابعة #${followUp.id}`
                  )}
                </p>
                <p className="text-xs text-slate-500">
                  {new Date(followUp.scheduled_at).toLocaleString('ar-SA')}
                  {followUp.assignee?.name ? ` · ${followUp.assignee.name}` : ''}
                </p>
                {followUp.notes ? <p className="text-slate-700">{followUp.notes}</p> : null}
              </div>
              {followUp.status !== 'COMPLETED' && followUp.status !== 'CANCELLED' ? (
                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    disabled={busyId === followUp.id}
                    className="min-h-10 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60"
                    onClick={() => void complete(followUp.id)}
                  >
                    إكمال
                  </button>
                  <button
                    type="button"
                    disabled={busyId === followUp.id}
                    className="min-h-10 rounded-xl border border-amber-300 px-4 text-sm text-amber-950 disabled:opacity-60"
                    onClick={() => void addToCalendar(followUp)}
                  >
                    إضافة للجدول
                  </button>
                  <button
                    type="button"
                    disabled={busyId === followUp.id}
                    className="min-h-10 rounded-xl border px-4 text-sm disabled:opacity-60"
                    onClick={() => setRescheduleId(followUp.id)}
                  >
                    إعادة جدولة
                  </button>
                  <button
                    type="button"
                    disabled={busyId === followUp.id}
                    className="min-h-10 rounded-xl border border-red-300 px-4 text-sm text-red-800 disabled:opacity-60"
                    onClick={() => void cancel(followUp.id)}
                  >
                    إلغاء
                  </button>
                </div>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </DashboardSection>
  )
}
