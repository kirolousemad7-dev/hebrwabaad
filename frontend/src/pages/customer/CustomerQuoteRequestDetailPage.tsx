import { FormEvent, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  getCustomerQuoteRequest,
  respondCustomerQuoteRequest,
  type CommercialQuotation,
} from '../../services/quoteRequests'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'
import {
  commercialQuotationStatusLabel,
  formatQuoteDate,
  paymentNextStepLabel,
  paymentPolicyMessagingAr,
  quoteEventLabelAr,
  quoteRequestSourceLabel,
  quoteRequestStatusLabel,
} from '../../utils/quoteRequests'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315CFF]'

function latestQuotation(quotations: CommercialQuotation[] | undefined): CommercialQuotation | null {
  if (!quotations?.length) return null
  return [...quotations].sort((a, b) => (b.revision ?? 0) - (a.revision ?? 0))[0] ?? null
}

export function CustomerQuoteRequestDetailPage() {
  const { id } = useParams()
  const quoteId = Number(id)
  const { state, reload } = useAsyncData(
    () => getCustomerQuoteRequest(quoteId),
    [quoteId],
  )

  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  async function handleRespond(event: FormEvent) {
    event.preventDefault()
    if (!message.trim() || busy || !Number.isFinite(quoteId)) return
    setBusy(true)
    setError(null)
    setNotice(null)
    try {
      await respondCustomerQuoteRequest(quoteId, { message: message.trim() })
      setMessage('')
      setNotice('تم إرسال الرد. سيعود الطلب للمراجعة.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إرسال الرد.'))
    } finally {
      setBusy(false)
    }
  }

  if (!Number.isFinite(quoteId) || quoteId <= 0) {
    return (
      <CatalogEmptyState
        title="طلب غير صالح."
        description="تحقق من الرابط أو عد إلى قائمة طلبات التسعير."
        actions={[{ to: '/dashboard/quote-requests', label: 'العودة', variant: 'primary' }]}
      />
    )
  }

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل الطلب..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={`تعذر تحميل الطلب. ${state.message}`} onRetry={() => void reload()} />
  }

  const item = state.data
  const quotations = (item.commercial_quotations ?? []) as CommercialQuotation[]
  const latest = latestQuotation(quotations)
  const needsInfo = String(item.status) === 'NEEDS_INFORMATION'
  const acceptedQuote = quotations.find((q) => String(q.status) === 'ACCEPTED') ?? null
  const payment = acceptedQuote?.payment_summary
  const amountDue = Number(payment?.amount_due_now ?? payment?.remaining ?? 0)

  return (
    <section className="space-y-5">
      <header className="space-y-2">
        <Link to="/dashboard/quote-requests" className="text-sm text-[#315CFF] underline">
          ← طلبات التسعير
        </Link>
        <h1 className="text-2xl font-semibold text-[#111318]">{item.title}</h1>
        <p className="font-mono text-sm text-slate-500" dir="ltr">
          {item.reference}
        </p>
        <p className="text-sm text-slate-600">
          {quoteRequestStatusLabel(String(item.status))} · {quoteRequestSourceLabel(String(item.source_type))}
        </p>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      <div className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 text-sm sm:grid-cols-2">
        <div>
          <p className="text-xs text-slate-500">الموعد المطلوب</p>
          <p className="font-medium">{formatQuoteDate(item.required_date)}</p>
        </div>
        <div>
          <p className="text-xs text-slate-500">المدينة</p>
          <p className="font-medium">{item.city || '—'}</p>
        </div>
        <div>
          <p className="text-xs text-slate-500">الميزانية</p>
          <p className="font-medium">
            {item.budget_min != null ? formatMoney(item.budget_min) : '—'} —{' '}
            {item.budget_max != null ? formatMoney(item.budget_max) : '—'}
          </p>
        </div>
        <div>
          <p className="text-xs text-slate-500">تاريخ الطلب</p>
          <p className="font-medium">{formatQuoteDate(item.requested_at ?? item.created_at)}</p>
        </div>
        {item.customer_notes ? (
          <div className="sm:col-span-2">
            <p className="text-xs text-slate-500">ملاحظاتك</p>
            <p className="mt-1 whitespace-pre-wrap text-slate-700">{item.customer_notes}</p>
          </div>
        ) : null}
        {item.information_request ? (
          <div className="sm:col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
            <p className="text-xs font-medium text-amber-900">طلب معلومات من الفريق</p>
            <p className="mt-1 whitespace-pre-wrap text-amber-950">{item.information_request}</p>
          </div>
        ) : null}
      </div>

      {acceptedQuote ? (
        <div className="space-y-2 rounded-xl border border-emerald-200 bg-emerald-50/70 p-5 text-sm">
          <h2 className="font-semibold text-emerald-950">الخطوات التالية للدفع</h2>
          <p>{paymentPolicyMessagingAr(String(acceptedQuote.payment_policy || payment?.payment_policy || 'NONE'))}</p>
          <p className="text-emerald-900">
            {paymentNextStepLabel({
              status: acceptedQuote.status,
              payment_policy: acceptedQuote.payment_policy ?? payment?.payment_policy,
              amount_due: amountDue,
              requirement_met: payment?.requirement_met,
            })}
          </p>
          {payment ? (
            <div className="grid gap-2 sm:grid-cols-3">
              <p>المطلوب الآن: {formatMoney(amountDue, acceptedQuote.currency || 'EGP')}</p>
              <p>المدفوع: {formatMoney(payment.paid ?? 0, acceptedQuote.currency || 'EGP')}</p>
              <p>المتبقي: {formatMoney(payment.remaining ?? 0, acceptedQuote.currency || 'EGP')}</p>
            </div>
          ) : null}
          <p className="text-xs text-emerald-900">
            أكمل الدفع عبر رابط عرض السعر في الإشعار إن وُجد.
          </p>
        </div>
      ) : null}

      {needsInfo ? (
        <form onSubmit={(event) => void handleRespond(event)} className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold text-[#111318]">الرد بمعلومات إضافية</h2>
          <textarea
            className={fieldClass}
            rows={4}
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            required
            placeholder="أضف التفاصيل المطلوبة..."
          />
          <button
            type="submit"
            disabled={busy}
            className="min-h-11 rounded-xl bg-[#315CFF] px-4 text-sm font-medium text-white disabled:opacity-60"
          >
            {busy ? 'جاري الإرسال...' : 'إرسال الرد'}
          </button>
        </form>
      ) : null}

      <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold text-[#111318]">عروض الأسعار</h2>
        {quotations.length === 0 ? (
          <p className="text-sm text-slate-600">لم يُرسل عرض سعر بعد.</p>
        ) : (
          <ul className="space-y-3 text-sm">
            {quotations.map((q) => (
              <li key={q.id} className="rounded-lg border border-slate-100 p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="font-mono text-xs" dir="ltr">
                    {q.reference} · R{q.revision}
                  </span>
                  <span>{commercialQuotationStatusLabel(String(q.status))}</span>
                </div>
                <p className="mt-1 font-medium">{formatMoney(q.total, q.currency || 'EGP')}</p>
              </li>
            ))}
          </ul>
        )}
        {latest && ['SENT', 'VIEWED', 'ACCEPTED', 'REJECTED'].includes(String(latest.status)) ? (
          <p className="text-sm text-slate-600">
            عرض السعر متاح عبر الإشعار. افتح الرابط من الإشعار لعرضه على{' '}
            <span className="font-mono text-xs" dir="ltr">
              /cq/:token
            </span>
            .
          </p>
        ) : null}
      </div>

      {item.events && item.events.length > 0 ? (
        <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold text-[#111318]">الخط الزمني</h2>
          <ul className="space-y-2 text-sm">
            {item.events.map((event) => (
              <li key={event.id} className="rounded-lg border border-slate-100 px-3 py-2">
                <div className="flex flex-wrap justify-between gap-2">
                  <span className="font-medium">{quoteEventLabelAr(event.event_type)}</span>
                  <span className="text-xs text-slate-500">{formatQuoteDate(event.created_at)}</span>
                </div>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </section>
  )
}
