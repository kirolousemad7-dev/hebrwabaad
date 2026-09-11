import { FormEvent, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { BrandLogo } from '../../components/brand/BrandLogo'
import { ApiRequestError } from '../../services/api'
import {
  acceptPublicCommercialQuotation,
  checkoutPublicCommercialQuotation,
  downloadPublicCommercialQuotationPdf,
  getPublicCommercialQuotation,
  rejectPublicCommercialQuotation,
  requestPublicCommercialRevision,
  type PublicCommercialQuotation,
} from '../../services/quoteRequests'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'
import { isSafeCheckoutRedirect, PAYMENT_COPY } from '../../utils/payments'
import {
  REVISION_REASON_OPTIONS,
  commercialQuotationStatusLabel,
  formatQuoteDate,
  paymentPolicyMessagingAr,
  paymentStatusLabelAr,
} from '../../utils/quoteRequests'
import { printingPaymentPolicyLabel } from '../../utils/printingQuotations'

const QUOTE_TOKEN_KEY = 'hebr_cq_token'

function StatusBanner({ data }: { data: PublicCommercialQuotation }) {
  if (data.status === 'ACCEPTED') {
    return (
      <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950">
        تم قبول العرض
      </div>
    )
  }
  if (data.status === 'REJECTED') {
    return (
      <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">
        تم رفض العرض
      </div>
    )
  }
  if (data.status === 'EXPIRED') {
    return (
      <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-950">
        انتهت الصلاحية
      </div>
    )
  }
  if (data.expires_in_days != null && data.expires_in_days >= 0) {
    return (
      <div className="rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950">
        ينتهي خلال {data.expires_in_days} {data.expires_in_days === 1 ? 'يوم' : 'أيام'}
      </div>
    )
  }
  return null
}

export function PublicCommercialQuotationPage() {
  const { token } = useParams()
  const [data, setData] = useState<PublicCommercialQuotation | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [expired, setExpired] = useState(false)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [revisionReason, setRevisionReason] = useState('')
  const [revisionReasonCode, setRevisionReasonCode] = useState('price')
  const [showReject, setShowReject] = useState(false)
  const [showRevision, setShowRevision] = useState(false)
  const [pdfBusy, setPdfBusy] = useState(false)
  const [pdfAvailable, setPdfAvailable] = useState(true)

  async function load() {
    if (!token) {
      setError('رابط غير صالح.')
      setLoading(false)
      return
    }

    setLoading(true)
    setError(null)
    setExpired(false)
    try {
      const payload = await getPublicCommercialQuotation(token)
      setData(payload)
      if (payload.status === 'EXPIRED') {
        setExpired(true)
      }
    } catch (caught) {
      if (caught instanceof ApiRequestError) {
        const msg = caught.message || ''
        if (msg.includes('expired') || msg.includes('منته') || caught.status === 422) {
          setExpired(true)
          setError(null)
          setData(null)
        } else {
          setError(describeApiError(caught, 'تعذر تحميل عرض السعر.'))
          setData(null)
        }
      } else {
        setError(describeApiError(caught, 'تعذر تحميل عرض السعر.'))
        setData(null)
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token])

  async function handleAccept() {
    if (!token || busy) return
    setBusy(true)
    setActionError(null)
    try {
      const payload = await acceptPublicCommercialQuotation(token)
      setData(payload)
    } catch (caught) {
      if (caught instanceof ApiRequestError && (caught.message.includes('expired') || caught.message.includes('منته'))) {
        setExpired(true)
      }
      setActionError(describeApiError(caught, 'تعذر قبول العرض.'))
    } finally {
      setBusy(false)
    }
  }

  async function handlePayNow() {
    if (!token || busy) return
    setBusy(true)
    setActionError(null)
    try {
      try {
        sessionStorage.setItem(QUOTE_TOKEN_KEY, token)
      } catch {
        /* ignore */
      }
      const checkout = await checkoutPublicCommercialQuotation(token)
      if (!isSafeCheckoutRedirect(checkout.checkout_url)) {
        setActionError(PAYMENT_COPY.gatewayMissing)
        return
      }
      window.location.assign(checkout.checkout_url)
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر بدء عملية الدفع.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleReject(event: FormEvent) {
    event.preventDefault()
    if (!token || busy) return
    setBusy(true)
    setActionError(null)
    try {
      const payload = await rejectPublicCommercialQuotation(token, rejectReason.trim() || undefined)
      setData(payload)
      setShowReject(false)
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر رفض العرض.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleRevision(event: FormEvent) {
    event.preventDefault()
    if (!token || busy) return
    setBusy(true)
    setActionError(null)
    try {
      const payload = await requestPublicCommercialRevision(token, {
        reason: revisionReason.trim() || undefined,
        reason_code: revisionReasonCode || undefined,
      })
      setData(payload)
      setShowRevision(false)
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر طلب التعديل.'))
    } finally {
      setBusy(false)
    }
  }

  async function handlePdf() {
    if (!token || !data || pdfBusy) return
    setPdfBusy(true)
    setActionError(null)
    try {
      await downloadPublicCommercialQuotationPdf(token, data.reference)
    } catch (caught) {
      if (caught instanceof ApiRequestError && (caught.status === 404 || caught.status === 501)) {
        setPdfAvailable(false)
      }
      setActionError(describeApiError(caught, 'تعذر تنزيل PDF.'))
    } finally {
      setPdfBusy(false)
    }
  }

  if (loading) {
    return (
      <div dir="rtl" className="min-h-screen bg-[#F7F5EF] px-4 py-10">
        <div className="mx-auto max-w-md text-center text-sm text-slate-600">جاري تحميل عرض السعر...</div>
      </div>
    )
  }

  if (expired) {
    return (
      <div dir="rtl" className="min-h-screen bg-[#F7F5EF] px-4 py-10">
        <div className="mx-auto max-w-md space-y-4 rounded-2xl border border-amber-200 bg-white p-6 text-center">
          <BrandLogo size="auth" to="/" className="justify-center" />
          <h1 className="text-xl font-semibold text-[#111318]">انتهت صلاحية العرض</h1>
          <p className="text-sm text-slate-600">هذا العرض لم يعد صالحاً. تواصل مع فريق حبر وأبعاد لطلب عرض محدّث.</p>
          <Link to="/" className="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#315CFF] px-4 text-sm text-white">
            الرئيسية
          </Link>
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div dir="rtl" className="min-h-screen bg-[#F7F5EF] px-4 py-10">
        <div className="mx-auto max-w-md space-y-4 rounded-2xl border border-red-200 bg-white p-5 text-center">
          <p className="text-sm text-red-800">{error}</p>
          <button
            type="button"
            className="min-h-11 rounded-xl bg-[#111318] px-4 text-sm text-white"
            onClick={() => void load()}
          >
            إعادة المحاولة
          </button>
        </div>
      </div>
    )
  }

  if (!data) {
    return (
      <div dir="rtl" className="min-h-screen bg-[#F7F5EF] px-4 py-10">
        <div className="mx-auto max-w-md rounded-2xl border bg-white p-5 text-center text-sm text-slate-600">
          العرض غير متاح. تحقق من الرابط أو تواصل مع الفريق.
        </div>
      </div>
    )
  }

  const actionable = data.status === 'SENT' || data.status === 'VIEWED'
  const canAccept = data.can_accept ?? actionable
  const canRevise = data.can_request_revision ?? actionable
  const canReject = data.can_reject ?? actionable
  const currency = data.currency || 'EGP'
  const payment = data.payment_summary
  const amountDueNow = Number(payment?.amount_due_now ?? payment?.remaining ?? 0)
  const policy = String(data.payment_policy || payment?.payment_policy || 'NONE')
  const canPayOnline =
    data.can_checkout === true ||
    (data.status === 'ACCEPTED' &&
      (policy === 'DEPOSIT' || policy === 'FULL') &&
      amountDueNow > 0 &&
      !payment?.requirement_met)
  const items = data.items ?? []
  const revisions = data.revisions ?? []

  return (
    <div dir="rtl" className="min-h-screen bg-gradient-to-b from-[#F7F5EF] via-white to-[#F7F5EF]">
      <div className="mx-auto w-full max-w-3xl space-y-5 px-4 py-8">
        <header className="space-y-3 text-center">
          <BrandLogo size="auth" to="/" className="justify-center" />
          <div>
            <p className="text-sm text-[#315CFF]">حبر وأبعاد</p>
            <h1 className="text-2xl font-semibold text-[#111318]">عرض سعر</h1>
            <p className="mt-1 font-mono text-sm text-slate-600" dir="ltr">
              {data.reference}
              {data.revision != null ? ` · R${data.revision}` : ''}
            </p>
            {data.quote_request_reference ? (
              <p className="mt-1 text-xs text-slate-500" dir="ltr">
                طلب: {data.quote_request_reference}
              </p>
            ) : null}
            {data.customer?.name ? (
              <p className="mt-1 text-sm text-[#111318]">إلى: {data.customer.name}</p>
            ) : null}
          </div>
          <div className="flex flex-wrap items-center justify-center gap-2">
            <span className="inline-flex rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-800">
              {commercialQuotationStatusLabel(data.status)}
            </span>
            {pdfAvailable ? (
              <button
                type="button"
                disabled={pdfBusy}
                className="inline-flex rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-[#111318] disabled:opacity-60"
                onClick={() => void handlePdf()}
              >
                {pdfBusy ? 'جاري التنزيل...' : 'تنزيل PDF'}
              </button>
            ) : null}
          </div>
        </header>

        <StatusBanner data={data} />

        {actionError ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{actionError}</div>
        ) : null}

        {data.status === 'ACCEPTED' ? (
          <div className="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 text-sm">
            <p className="font-medium text-emerald-950">{paymentPolicyMessagingAr(policy)}</p>
            {data.latest_payment_status ? (
              <p className="text-xs text-emerald-900">
                حالة الدفع: {paymentStatusLabelAr(data.latest_payment_status)}
              </p>
            ) : null}
            {payment ? (
              <div className="grid grid-cols-2 gap-2 text-xs text-emerald-950 sm:grid-cols-4">
                <div>
                  <p className="text-emerald-800">قيمة العرض</p>
                  <p className="font-semibold">{formatMoney(payment.total ?? data.total, currency)}</p>
                </div>
                <div>
                  <p className="text-emerald-800">المطلوب الآن</p>
                  <p className="font-semibold">{formatMoney(amountDueNow, currency)}</p>
                </div>
                <div>
                  <p className="text-emerald-800">المدفوع</p>
                  <p className="font-semibold">{formatMoney(payment.paid ?? 0, currency)}</p>
                </div>
                <div>
                  <p className="text-emerald-800">المتبقي</p>
                  <p className="font-semibold">{formatMoney(payment.remaining ?? 0, currency)}</p>
                </div>
              </div>
            ) : null}
            {canPayOnline ? (
              <button
                type="button"
                disabled={busy}
                className="min-h-11 w-full rounded-xl bg-[#315CFF] px-4 text-sm font-medium text-white disabled:opacity-60"
                onClick={() => void handlePayNow()}
              >
                {busy
                  ? PAYMENT_COPY.processing
                  : data.payment_cta_label || PAYMENT_COPY.payNow}
              </button>
            ) : null}
          </div>
        ) : null}

        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-200 bg-[#F7F5EF] px-4 py-2 text-sm font-semibold text-[#111318]">
            البنود
          </div>
          <ul className="divide-y divide-slate-100">
            {items.map((item, index) => (
              <li key={index} className="space-y-1 px-4 py-3 text-sm">
                <p className="font-medium text-[#111318]">{item.description || 'بند'}</p>
                <div className="flex flex-wrap justify-between gap-2 text-slate-600">
                  <span>الكمية: {Number(item.quantity ?? 0).toLocaleString('ar-SA')}</span>
                  <span>سعر الوحدة: {formatMoney(item.unit_price ?? 0, currency)}</span>
                  <span>
                    {formatMoney(
                      item.subtotal ?? Number(item.quantity ?? 0) * Number(item.unit_price ?? 0),
                      currency,
                    )}
                  </span>
                </div>
              </li>
            ))}
          </ul>
          <div className="space-y-1 border-t border-slate-200 bg-[#F7F5EF] px-4 py-3 text-sm">
            <p>المجموع الفرعي: {formatMoney(data.subtotal ?? 0, currency)}</p>
            {Number(data.discount_amount) > 0 ? <p>الخصم: {formatMoney(data.discount_amount ?? 0, currency)}</p> : null}
            {Number(data.tax_amount) > 0 ? <p>الضريبة: {formatMoney(data.tax_amount ?? 0, currency)}</p> : null}
            {Number(data.shipping_amount) > 0 ? <p>الشحن: {formatMoney(data.shipping_amount ?? 0, currency)}</p> : null}
            {Number(data.rental_amount) > 0 ? <p>التأجير: {formatMoney(data.rental_amount ?? 0, currency)}</p> : null}
            <p className="text-base font-semibold text-[#111318]">الإجمالي: {formatMoney(data.total ?? 0, currency)}</p>
            {data.payment_policy ? (
              <p className="text-xs text-slate-600">
                سياسة الدفع: {printingPaymentPolicyLabel(data.payment_policy)}
                {data.deposit_required != null && Number(data.deposit_required) > 0
                  ? ` · عربون ${formatMoney(data.deposit_required, currency)}`
                  : ''}
              </p>
            ) : null}
            <p className="text-xs text-slate-600">صالح حتى: {formatQuoteDate(data.valid_until)}</p>
            {data.execution_duration ? (
              <p className="text-xs text-slate-600">مدة التنفيذ: {data.execution_duration}</p>
            ) : null}
          </div>
        </section>

        {data.notes ? (
          <section className="rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
            <h2 className="mb-2 font-semibold text-[#111318]">ملاحظات</h2>
            <p className="whitespace-pre-wrap text-slate-700">{data.notes}</p>
          </section>
        ) : null}

        {data.terms ? (
          <section className="rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
            <h2 className="mb-2 font-semibold text-[#111318]">الشروط</h2>
            <p className="whitespace-pre-wrap text-slate-700">{data.terms}</p>
          </section>
        ) : null}

        {revisions.length > 0 ? (
          <section className="rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
            <h2 className="mb-3 font-semibold text-[#111318]">سجل المراجعات</h2>
            <ul className="space-y-2">
              {revisions.map((rev) => (
                <li key={rev.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2">
                  <span className="font-mono text-xs" dir="ltr">
                    {rev.reference || `#${rev.id}`} · R{rev.revision ?? '—'}
                  </span>
                  <span>{commercialQuotationStatusLabel(String(rev.status || ''))}</span>
                  {rev.is_current ? (
                    <span className="rounded-full bg-[#315CFF]/10 px-2 py-0.5 text-xs text-[#315CFF]">الحالية</span>
                  ) : (
                    <span className="text-xs text-slate-500">مستبدلة</span>
                  )}
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        {canAccept || canRevise || canReject ? (
          <div className="flex flex-col gap-3">
            {canAccept ? (
              <button
                type="button"
                disabled={busy}
                className="min-h-11 w-full rounded-xl bg-emerald-700 px-5 text-sm font-medium text-white disabled:opacity-60"
                onClick={() => void handleAccept()}
              >
                قبول العرض
              </button>
            ) : null}
            {canRevise ? (
              <button
                type="button"
                disabled={busy}
                className="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-5 text-sm text-[#111318] disabled:opacity-60"
                onClick={() => {
                  setShowRevision(true)
                  setShowReject(false)
                }}
              >
                طلب تعديل
              </button>
            ) : null}
            {canReject ? (
              <button
                type="button"
                disabled={busy}
                className="min-h-11 w-full rounded-xl border border-red-300 bg-white px-5 text-sm text-red-800 disabled:opacity-60"
                onClick={() => {
                  setShowReject(true)
                  setShowRevision(false)
                }}
              >
                رفض العرض
              </button>
            ) : null}
          </div>
        ) : data.status !== 'ACCEPTED' ? (
          <p className="text-center text-sm text-slate-600">حالة العرض الحالية لا تسمح بالرد.</p>
        ) : null}

        {showRevision ? (
          <form onSubmit={(event) => void handleRevision(event)} className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <fieldset className="space-y-2">
              <legend className="text-sm font-medium text-[#111318]">سبب طلب التعديل</legend>
              <div className="grid gap-2 sm:grid-cols-2">
                {REVISION_REASON_OPTIONS.map((option) => (
                  <label key={option.code} className="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input
                      type="radio"
                      name="revision_reason_code"
                      value={option.code}
                      checked={revisionReasonCode === option.code}
                      onChange={() => setRevisionReasonCode(option.code)}
                    />
                    {option.label}
                  </label>
                ))}
              </div>
            </fieldset>
            <textarea
              value={revisionReason}
              onChange={(e) => setRevisionReason(e.target.value)}
              rows={3}
              placeholder="تفاصيل إضافية (اختياري)"
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
            />
            <div className="flex gap-2">
              <button type="submit" disabled={busy} className="min-h-11 rounded-xl bg-[#315CFF] px-4 text-sm text-white disabled:opacity-60">
                إرسال طلب التعديل
              </button>
              <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setShowRevision(false)}>
                إلغاء
              </button>
            </div>
          </form>
        ) : null}

        {showReject ? (
          <form onSubmit={(event) => void handleReject(event)} className="space-y-3 rounded-2xl border border-red-200 bg-red-50/50 p-4">
            <textarea
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              rows={3}
              placeholder="سبب الرفض (اختياري)"
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
            />
            <div className="flex gap-2">
              <button type="submit" disabled={busy} className="min-h-11 rounded-xl bg-red-700 px-4 text-sm text-white disabled:opacity-60">
                تأكيد الرفض
              </button>
              <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setShowReject(false)}>
                إلغاء
              </button>
            </div>
          </form>
        ) : null}
      </div>
    </div>
  )
}
