import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useToast } from '../../context/ToastContext'
import { getCrmInbox, markCrmInboxRead, type CrmInboxItem } from '../../services/crm'
import { describeApiError } from '../../utils/errors'

export function CrmInboxPage() {
  const toast = useToast()
  const [items, setItems] = useState<CrmInboxItem[]>([])
  const [unread, setUnread] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmInbox()
      setItems(response.data.items ?? [])
      setUnread(response.data.unread_count ?? 0)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل صندوق الوارد.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function markAll() {
    setBusy(true)
    setActionError(null)
    try {
      await markCrmInboxRead({ all: true })
      toast.success('تم تعليم الكل كمقروء.')
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر التحديث.'))
    } finally {
      setBusy(false)
    }
  }

  async function markOne(id: string) {
    setBusy(true)
    setActionError(null)
    try {
      await markCrmInboxRead({ ids: [id] })
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر تعليم العنصر.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <DashboardSection
      title="صندوق الوارد"
      description={`${unread.toLocaleString('ar-SA')} غير مقروء`}
      action={
        unread > 0 ? (
          <button type="button" disabled={busy} className="min-h-11 rounded-xl border px-4 text-sm disabled:opacity-60" onClick={() => void markAll()}>
            تعليم الكل كمقروء
          </button>
        ) : undefined
      }
    >
      {loading ? <DashboardPanelSkeleton label="جاري تحميل الوارد..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {actionError ? <FeedbackBanner kind="error">{actionError}</FeedbackBanner> : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="صندوق الوارد فارغ." description="الإشعارات والمتابعات المتأخرة تظهر هنا." />
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <ul className="space-y-3">
          {items.map((item) => {
            const unreadItem = !item.read_at
            return (
              <li
                key={item.id}
                className={`rounded-2xl border px-4 py-3 text-sm ${unreadItem ? 'border-amber-200 bg-amber-50/40' : 'bg-white'}`}
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <StatusBadge status={item.type} label={item.type.replace(/^crm_/, '')} tone={unreadItem ? 'warning' : 'progress'} />
                      <span className="font-medium">{item.title}</span>
                    </div>
                    {item.body ? <p className="text-slate-700">{item.body}</p> : null}
                    <p className="text-xs text-slate-500">
                      {item.created_at ? new Date(item.created_at).toLocaleString('ar-SA') : '—'}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {item.href ? (
                      <Link to={item.href} className="min-h-10 rounded-xl border px-3 text-sm leading-10">
                        فتح
                      </Link>
                    ) : null}
                    {unreadItem && item.source === 'notification' ? (
                      <button
                        type="button"
                        disabled={busy}
                        className="min-h-10 rounded-xl bg-slate-900 px-3 text-sm text-white disabled:opacity-60"
                        onClick={() => void markOne(item.id)}
                      >
                        مقروء
                      </button>
                    ) : null}
                  </div>
                </div>
              </li>
            )
          })}
        </ul>
      ) : null}
    </DashboardSection>
  )
}
