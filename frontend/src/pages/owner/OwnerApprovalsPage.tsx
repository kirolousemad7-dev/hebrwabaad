import { Link } from 'react-router-dom'
import { useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  approveRequest,
  getApprovalsInbox,
  getMyApprovals,
  rejectRequest,
  type ApprovalRequest,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

function statusLabel(status: string): string {
  const normalized = status.toLowerCase()
  if (normalized === 'pending') return 'قيد الانتظار'
  if (normalized === 'approved') return 'موافق عليه'
  if (normalized === 'rejected') return 'مرفوض'
  return status
}

function isPending(status: string): boolean {
  return status.toLowerCase() === 'pending'
}

function isSynthetic(item: ApprovalRequest): boolean {
  return Boolean(item.synthetic) || item.type === 'crm_quotation_approval' || typeof item.id === 'string'
}

function numericId(item: ApprovalRequest): number | null {
  return typeof item.id === 'number' ? item.id : null
}

export function OwnerApprovalsPage() {
  const [tab, setTab] = useState<'inbox' | 'mine'>('inbox')
  const [items, setItems] = useState<ApprovalRequest[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [rejectId, setRejectId] = useState<number | null>(null)
  const [rejectNote, setRejectNote] = useState('')

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = tab === 'inbox' ? await getApprovalsInbox('pending') : await getMyApprovals()
      setItems(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الموافقات.'))
      setItems([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab])

  async function handleApprove(id: number) {
    setBusyId(id)
    setError(null)
    try {
      await approveRequest(id)
      setNotice('تمت الموافقة.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر اعتماد الطلب.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleReject() {
    if (rejectId == null) return
    if (!rejectNote.trim()) {
      setError('ملاحظة الرفض مطلوبة.')
      return
    }

    setBusyId(rejectId)
    setError(null)
    try {
      await rejectRequest(rejectId, rejectNote.trim())
      setNotice('تم الرفض.')
      setRejectId(null)
      setRejectNote('')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر رفض الطلب.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold text-slate-900">الموافقات</h1>
        <p className="mt-1 text-sm text-slate-600">صندوق الوارد وطلباتك المرسلة.</p>
      </header>

      <div className="flex gap-2">
        <button
          type="button"
          onClick={() => setTab('inbox')}
          className={`rounded-full px-3 py-1.5 text-sm ${
            tab === 'inbox' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
          }`}
        >
          الوارد
        </button>
        <button
          type="button"
          onClick={() => setTab('mine')}
          className={`rounded-full px-3 py-1.5 text-sm ${
            tab === 'mine' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
          }`}
        >
          طلباتي
        </button>
      </div>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل الموافقات..." /> : null}
      {!loading && items.length === 0 && !error ? (
        <DashboardEmptyState title="لا موافقات." description="لا توجد عناصر في هذه القائمة." />
      ) : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}

      {!loading && items.length > 0 ? (
        <ul className="space-y-3">
          {items.map((item) => {
            const synthetic = isSynthetic(item)
            const id = numericId(item)
            const actionHref = item.action_href || item.href

            return (
              <li key={String(item.id)} className="rounded-2xl border border-slate-200 bg-white p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h2 className="font-semibold text-slate-900">{item.title}</h2>
                    <p className="mt-1 text-xs text-slate-500">
                      {statusLabel(String(item.status))} · من: {item.requester?.name ?? '—'} · إلى:{' '}
                      {item.assignee?.name ?? '—'}
                      {synthetic ? ' · عرض سعر CRM' : ''}
                    </p>
                    {item.notes ? <p className="mt-2 text-sm text-slate-600">{item.notes}</p> : null}
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {synthetic && actionHref ? (
                      <Link
                        to={actionHref}
                        className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white"
                      >
                        فتح العرض
                      </Link>
                    ) : null}
                    {tab === 'inbox' && !synthetic && id != null && isPending(String(item.status)) ? (
                      <>
                        <button
                          type="button"
                          disabled={busyId === id}
                          onClick={() => void handleApprove(id)}
                          className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white disabled:opacity-50"
                        >
                          موافقة
                        </button>
                        <button
                          type="button"
                          disabled={busyId === id}
                          onClick={() => {
                            setRejectId(id)
                            setRejectNote('')
                          }}
                          className="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-800 disabled:opacity-50"
                        >
                          رفض
                        </button>
                      </>
                    ) : null}
                  </div>
                </div>
              </li>
            )
          })}
        </ul>
      ) : null}

      {rejectId != null ? (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center">
          <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5">
            <h2 className="text-lg font-semibold">رفض الطلب</h2>
            <p className="mt-1 text-sm text-slate-600">ملاحظة الرفض مطلوبة.</p>
            <textarea
              value={rejectNote}
              onChange={(event) => setRejectNote(event.target.value)}
              rows={4}
              className={`mt-3 ${fieldClass}`}
            />
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm"
                onClick={() => setRejectId(null)}
              >
                إلغاء
              </button>
              <button
                type="button"
                className="rounded-lg bg-red-800 px-4 py-2 text-sm text-white"
                onClick={() => void handleReject()}
              >
                تأكيد الرفض
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
