import { FormEvent, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  getOwnerPayment,
  getOwnerPaymentRefunds,
  rejectOwnerPayment,
  requestOwnerPaymentRefund,
  verifyOwnerPayment,
  type PaymentRefundRow,
} from '../../services/payments'
import { describeApiError } from '../../utils/errors'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import { formatPaymentAmount } from '../../utils/payments'
import { isOwner } from '../../utils/roles'

export function OwnerPaymentDetailPage() {
  const { paymentId } = useParams()
  const numericId = Number(paymentId)
  const toast = useToast()
  const { user } = useAuth()
  const { state, reload } = useAsyncData(() => getOwnerPayment(numericId))
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [refundOpen, setRefundOpen] = useState(false)
  const [refundAmount, setRefundAmount] = useState('')
  const [refundReason, setRefundReason] = useState('')
  const [refundManual, setRefundManual] = useState(false)
  const [refunds, setRefunds] = useState<PaymentRefundRow[]>([])
  const [refundable, setRefundable] = useState<string | null>(null)

  const ownerUser = isOwner(user?.role)

  useEffect(() => {
    if (state.status !== 'ready' || state.data.status !== 'PAID' || !ownerUser) {
      return
    }

    let cancelled = false
    ;(async () => {
      try {
        const response = await getOwnerPaymentRefunds(state.data.id)
        if (cancelled) return
        setRefunds(response.data.items ?? [])
        setRefundable(response.data.refundable_amount ?? null)
      } catch {
        // optional
      }
    })()

    return () => {
      cancelled = true
    }
  }, [state, ownerUser])

  if (!Number.isInteger(numericId) || numericId <= 0) {
    return <CatalogErrorState message="تعذر تحميل الدفعة." onRetry={() => undefined} />
  }

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل الدفعة..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={state.message} onRetry={() => void reload()} />
  }

  const payment = state.data
  const canVerify = payment.status === 'PENDING_VERIFICATION'
  const canRefund = Boolean(ownerUser && (payment.can_refund ?? payment.status === 'PAID'))

  async function loadRefunds() {
    if (!ownerUser) return
    try {
      const response = await getOwnerPaymentRefunds(payment.id)
      setRefunds(response.data.items ?? [])
      setRefundable(response.data.refundable_amount ?? null)
    } catch {
      // ignore
    }
  }

  async function approve() {
    if (busy) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      await verifyOwnerPayment(payment.id)
      toast.success('تم تأكيد الدفع.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تأكيد الدفع.'))
    } finally {
      setBusy(false)
    }
  }

  async function reject(event: FormEvent) {
    event.preventDefault()

    if (busy) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      await rejectOwnerPayment(payment.id, reason.trim())
      toast.success('تم رفض التحويل.')
      setReason('')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر رفض التحويل.'))
    } finally {
      setBusy(false)
    }
  }

  async function submitRefund(event: FormEvent) {
    event.preventDefault()
    if (busy) return

    setBusy(true)
    setError(null)
    try {
      await requestOwnerPaymentRefund(payment.id, {
        amount: refundAmount.trim(),
        reason: refundReason.trim() || undefined,
        manual: refundManual,
      })
      toast.success('تم إرسال طلب الاسترداد.')
      setRefundOpen(false)
      setRefundReason('')
      await reload()
      await loadRefunds()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنفيذ الاسترداد.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <p className="text-xs text-slate-500">دفعة #{payment.id}</p>
        <h1 className="text-2xl font-semibold">تفاصيل الدفع</h1>
        <StatusBadge status={payment.status} label={payment.status_label} />
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <dl className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <div>
          <dt className="text-xs text-slate-500">العميل</dt>
          <dd>{payment.customer?.name ?? '—'}</dd>
          <dd className="text-xs text-slate-500">{payment.customer?.email}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">الطلب</dt>
          <dd>{payment.order?.title ?? '—'}</dd>
          <dd className="text-xs text-slate-500" dir="ltr">
            {payment.order?.reference}
          </dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">المشروع</dt>
          <dd>{payment.order?.project?.title ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">المبلغ</dt>
          <dd className="font-semibold">{formatPaymentAmount(payment.amount, payment.currency)}</dd>
        </div>
        {payment.net_paid != null ? (
          <div>
            <dt className="text-xs text-slate-500">صافي المدفوع</dt>
            <dd>{formatPaymentAmount(String(payment.net_paid), payment.currency)}</dd>
          </div>
        ) : null}
        {payment.refundable_amount != null ? (
          <div>
            <dt className="text-xs text-slate-500">قابل للاسترداد</dt>
            <dd>{formatPaymentAmount(String(payment.refundable_amount), payment.currency)}</dd>
          </div>
        ) : null}
        <div>
          <dt className="text-xs text-slate-500">طريقة الدفع</dt>
          <dd>{payment.payment_method_label}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">المزوّد</dt>
          <dd>{payment.provider}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">معرّف العملية</dt>
          <dd dir="ltr">{payment.provider_transaction_id ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">مرجع التحويل</dt>
          <dd dir="ltr">{payment.reference_number ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">اسم المحوّل</dt>
          <dd>{payment.payer_name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">تاريخ الإنشاء</dt>
          <dd>{formatDashboardDateTime(payment.created_at)}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">تاريخ الدفع</dt>
          <dd>{formatDashboardDateTime(payment.paid_at)}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">التحقق</dt>
          <dd>
            {payment.verified_by?.name ?? '—'} · {formatDashboardDateTime(payment.verified_at)}
          </dd>
        </div>
        <div className="sm:col-span-2">
          <dt className="text-xs text-slate-500">سبب الرفض / الفشل</dt>
          <dd>{payment.failure_reason ?? '—'}</dd>
        </div>
      </dl>

      {canRefund ? (
        <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="font-semibold">الاسترداد</h2>
            <button
              type="button"
              className="rounded-xl bg-amber-800 px-4 py-2 text-sm text-white"
              onClick={() => {
                setRefundAmount(String(refundable ?? payment.refundable_amount ?? payment.amount))
                setRefundOpen(true)
              }}
            >
              طلب استرداد
            </button>
          </div>
          {refunds.length === 0 ? (
            <p className="text-sm text-slate-500">لا طلبات استرداد بعد.</p>
          ) : (
            <ul className="space-y-2 text-sm">
              {refunds.map((row) => (
                <li key={row.id} className="rounded-lg border border-slate-100 px-3 py-2">
                  #{row.id} · {formatPaymentAmount(String(row.amount), row.currency || payment.currency)} ·{' '}
                  {row.status}
                  {row.reason ? <span className="text-xs text-slate-500"> · {row.reason}</span> : null}
                </li>
              ))}
            </ul>
          )}
        </div>
      ) : null}

      {refundOpen ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
          <form
            onSubmit={(event) => void submitRefund(event)}
            className="w-full max-w-md space-y-4 rounded-2xl bg-white p-5 shadow-xl"
          >
            <h3 className="text-lg font-semibold">تأكيد طلب الاسترداد</h3>
            <label className="block space-y-1 text-sm">
              <span>المبلغ</span>
              <input
                required
                value={refundAmount}
                onChange={(event) => setRefundAmount(event.target.value)}
                className="w-full rounded-xl border border-slate-300 px-3 py-2"
                dir="ltr"
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span>السبب</span>
              <textarea
                value={refundReason}
                onChange={(event) => setRefundReason(event.target.value)}
                className="min-h-20 w-full rounded-xl border border-slate-300 px-3 py-2"
              />
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={refundManual}
                onChange={(event) => setRefundManual(event.target.checked)}
              />
              استرداد يدوي (بدون PayTabs)
            </label>
            <div className="flex flex-wrap gap-2">
              <button
                type="submit"
                disabled={busy || !refundAmount.trim()}
                className="rounded-xl bg-amber-800 px-4 py-2 text-sm text-white disabled:opacity-50"
              >
                تأكيد الاسترداد
              </button>
              <button
                type="button"
                className="rounded-xl border border-slate-300 px-4 py-2 text-sm"
                onClick={() => setRefundOpen(false)}
              >
                إلغاء
              </button>
            </div>
          </form>
        </div>
      ) : null}

      {canVerify ? (
        <div className="space-y-4 rounded-2xl border border-amber-200 bg-amber-50 p-5">
          <h2 className="font-semibold">مراجعة التحويل ({payment.payment_method_label})</h2>
          <div className="flex flex-wrap gap-3">
            <button
              type="button"
              disabled={busy}
              onClick={() => void approve()}
              className="min-h-11 rounded-xl bg-emerald-700 px-4 py-2 text-sm text-white disabled:opacity-50"
            >
              تأكيد الدفع
            </button>
          </div>
          <form className="space-y-3" onSubmit={(event) => void reject(event)}>
            <label className="block space-y-1 text-sm">
              <span>سبب الرفض</span>
              <textarea
                required
                minLength={5}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                className="min-h-24 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
              />
            </label>
            <button
              type="submit"
              disabled={busy || reason.trim().length < 5}
              className="min-h-11 rounded-xl bg-red-700 px-4 py-2 text-sm text-white disabled:opacity-50"
            >
              رفض التحويل
            </button>
          </form>
        </div>
      ) : null}

      <div className="flex flex-wrap gap-3 text-sm">
        <Link to="/owner/payments" className="underline">
          كل المدفوعات
        </Link>
        <Link to="/owner/payments/reconciliation" className="underline">
          المطابقة
        </Link>
      </div>
    </section>
  )
}
