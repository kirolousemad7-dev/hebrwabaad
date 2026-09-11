import { FormEvent, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { PublicQuotationPage } from './PublicQuotationPage'
import { ApiRequestError } from '../services/api'
import {
  acceptPublicPrintingQuotation,
  checkoutPublicPrintingQuotation,
  getPublicPrintingQuotation,
  rejectPublicPrintingQuotation,
  type PublicPrintingQuotation,
} from '../services/printingQuotations'
import { formatMoney } from '../utils/catalog'
import { describeApiError } from '../utils/errors'
import { isSafeCheckoutRedirect, PAYMENT_COPY } from '../utils/payments'
import {
  absolutePublicPath,
  printingPaymentPolicyLabel,
  printingPublicTrackPath,
  printingQuotationStatusLabel,
} from '../utils/printingQuotations'

const QUOTE_TOKEN_KEY = 'hebr_quote_token'

export function PublicPrintingQuotePage() {
  const { token } = useParams()
  const [data, setData] = useState<PublicPrintingQuotation | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [fallbackCrm, setFallbackCrm] = useState(false)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [showReject, setShowReject] = useState(false)
  const [trackingToken, setTrackingToken] = useState<string | null>(null)

  async function load() {
    if (!token) {
      setError('رابط غير صالح.')
      setLoading(false)
      return
    }

    setLoading(true)
    setError(null)
    setFallbackCrm(false)
    try {
      const payload = await getPublicPrintingQuotation(token)
      setData(payload)
      if (payload.tracking_token) {
        setTrackingToken(payload.tracking_token)
      }
    } catch (caught) {
      if (caught instanceof ApiRequestError && (caught.status === 404 || caught.status === 422)) {
        setFallbackCrm(true)
        setData(null)
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
      const payload = await acceptPublicPrintingQuotation(token)
      setData(payload)
      if (payload.tracking_token) {
        setTrackingToken(payload.tracking_token)
      }
    } catch (caught) {
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
      const checkout = await checkoutPublicPrintingQuotation(token)
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
      const payload = await rejectPublicPrintingQuotation(token, rejectReason.trim() || undefined)
      setData(payload)
      setShowReject(false)
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر رفض العرض.'))
    } finally {
      setBusy(false)
    }
  }

  if (fallbackCrm) {
    return <PublicQuotationPage />
  }

  if (loading) {
    return (
      <div dir="rtl" className="min-h-screen bg-brand-canvas px-4 py-10">
        <div className="mx-auto max-w-md text-center text-sm text-slate-600">جاري تحميل عرض السعر...</div>
      </div>
    )
  }

  if (error) {
    return (
      <div dir="rtl" className="min-h-screen bg-brand-canvas px-4 py-10">
        <div className="mx-auto max-w-md space-y-4 rounded-2xl border border-red-200 bg-white p-5 text-center">
          <p className="text-sm text-red-800">{error}</p>
          <button
            type="button"
            className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white"
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
      <div dir="rtl" className="min-h-screen bg-brand-canvas px-4 py-10">
        <div className="mx-auto max-w-md rounded-2xl border bg-white p-5 text-center text-sm text-slate-600">
          العرض غير متاح. تحقق من الرابط أو تواصل مع فريق الطباعة.
        </div>
      </div>
    )
  }

  const canRespond = data.status === 'SENT' || data.status === 'VIEWED'
  const currency = data.currency || 'EGP'
  const payment = data.payment_summary
  const amountDueNow = Number(payment?.amount_due_now ?? payment?.remaining ?? 0)
  const paidAmount = Number(payment?.paid ?? 0)
  const remainingAmount = Number(payment?.remaining ?? 0)
  const canPayOnline =
    data.status === 'ACCEPTED' &&
    Boolean(data.paytabs_available) &&
    amountDueNow > 0 &&
    !(payment?.requirement_met)
  const items = data.line_items?.length
    ? data.line_items
    : [
        {
          description: data.product_name,
          quantity: data.quantity,
          unit_price: data.subtotal,
          line_total: data.subtotal,
        },
      ]
  const trackHref = trackingToken
    ? printingPublicTrackPath(trackingToken)
    : data.tracking_url
      ? data.tracking_url
      : null

  return (
    <div dir="rtl" className="min-h-screen bg-gradient-to-b from-brand-canvas via-amber-50/40 to-brand-canvas">
      <div className="mx-auto w-full max-w-md space-y-5 px-4 py-8 sm:max-w-lg">
        <header className="space-y-3 text-center">
          <BrandLogo size="auth" to="/" className="justify-center" />
          <div>
            <p className="text-sm text-amber-800">حبر وأبعاد</p>
            <h1 className="text-2xl font-semibold text-slate-900">عرض سعر طباعة</h1>
            <p className="mt-1 font-mono text-sm text-slate-600" dir="ltr">
              {data.reference}
              {data.revision != null ? ` · R${data.revision}` : ''}
            </p>
          </div>
          <span className="inline-flex rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-800">
            {printingQuotationStatusLabel(data.status)}
          </span>
        </header>

        {actionError ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {actionError}
          </div>
        ) : null}

        {data.status === 'ACCEPTED' ? (
          <div className="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 text-sm">
            <p className="font-medium text-emerald-950">تم قبول العرض.</p>
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
                  <p className="font-semibold">{formatMoney(paidAmount, currency)}</p>
                </div>
                <div>
                  <p className="text-emerald-800">المتبقي</p>
                  <p className="font-semibold">{formatMoney(remainingAmount, currency)}</p>
                </div>
                {payment.refunded_total != null && Number(payment.refunded_total) > 0 ? (
                  <div>
                    <p className="text-emerald-800">المسترد</p>
                    <p className="font-semibold">{formatMoney(payment.refunded_total, currency)}</p>
                  </div>
                ) : null}
              </div>
            ) : null}
            {payment?.requirement_met ? (
              <p className="text-emerald-900">تم استلام المبلغ المطلوب.</p>
            ) : null}
            {canPayOnline ? (
              <button
                type="button"
                disabled={busy}
                className="min-h-11 w-full rounded-xl bg-slate-900 px-4 text-sm font-medium text-white disabled:opacity-60"
                onClick={() => void handlePayNow()}
              >
                {busy ? PAYMENT_COPY.processing : PAYMENT_COPY.payNow}
              </button>
            ) : null}
            {trackHref ? (
              <>
                {trackHref.startsWith('http') ? (
                  <a href={trackHref} className="inline-block underline">
                    متابعة حالة الطلب
                  </a>
                ) : (
                  <Link to={trackHref} className="inline-block underline">
                    متابعة حالة الطلب
                  </Link>
                )}
                <p className="break-all text-xs text-emerald-900" dir="ltr">
                  {absolutePublicPath(trackHref)}
                </p>
              </>
            ) : null}
          </div>
        ) : null}

        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-brand-paper">
          <div className="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">البنود</div>
          <ul className="divide-y divide-slate-100">
            {items.map((item, index) => (
              <li key={index} className="space-y-1 px-4 py-3 text-sm">
                <p className="font-medium text-slate-900">{item.description || 'بند'}</p>
                <div className="flex flex-wrap justify-between gap-2 text-slate-600">
                  <span>الكمية: {Number(item.quantity ?? 0).toLocaleString('ar-SA')}</span>
                  <span>{formatMoney(item.line_total ?? Number(item.quantity ?? 0) * Number(item.unit_price ?? 0), currency)}</span>
                </div>
              </li>
            ))}
          </ul>
          <div className="space-y-1 border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
            <p>المجموع الفرعي: {formatMoney(data.subtotal, currency)}</p>
            {Number(data.discount_amount) > 0 ? (
              <p>الخصم: {formatMoney(data.discount_amount, currency)}</p>
            ) : null}
            {Number(data.tax_amount) > 0 ? <p>الضريبة: {formatMoney(data.tax_amount, currency)}</p> : null}
            <p className="text-base font-semibold text-slate-900">
              الإجمالي: {formatMoney(data.total, currency)}
            </p>
            {data.payment_policy ? (
              <p className="text-xs text-slate-600">
                سياسة الدفع: {printingPaymentPolicyLabel(data.payment_policy)}
                {data.deposit_required != null && Number(data.deposit_required) > 0
                  ? ` · عربون ${formatMoney(data.deposit_required, currency)}`
                  : ''}
              </p>
            ) : null}
            <p className="text-xs text-slate-600">
              صالح حتى:{' '}
              {data.valid_until ? new Date(data.valid_until).toLocaleDateString('ar-SA') : '—'}
            </p>
          </div>
        </section>

        {data.notes ? (
          <section className="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h2 className="mb-2 font-semibold">ملاحظات</h2>
            <p className="whitespace-pre-wrap text-slate-700">{data.notes}</p>
          </section>
        ) : null}

        {data.terms ? (
          <section className="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h2 className="mb-2 font-semibold">الشروط</h2>
            <p className="whitespace-pre-wrap text-slate-700">{data.terms}</p>
          </section>
        ) : null}

        {canRespond ? (
          <div className="flex flex-col gap-3 sm:flex-row sm:justify-center">
            <button
              type="button"
              disabled={busy}
              className="min-h-11 flex-1 rounded-xl bg-emerald-700 px-5 text-sm font-medium text-white disabled:opacity-60"
              onClick={() => void handleAccept()}
            >
              قبول العرض
            </button>
            <button
              type="button"
              disabled={busy}
              className="min-h-11 flex-1 rounded-xl border border-red-300 bg-white px-5 text-sm text-red-800 disabled:opacity-60"
              onClick={() => setShowReject(true)}
            >
              رفض العرض
            </button>
          </div>
        ) : data.status !== 'ACCEPTED' ? (
          <p className="text-center text-sm text-slate-600">حالة العرض الحالية لا تسمح بالرد.</p>
        ) : null}

        {showReject ? (
          <form
            onSubmit={(event) => void handleReject(event)}
            className="space-y-3 rounded-2xl border border-red-200 bg-red-50/50 p-4"
          >
            <textarea
              value={rejectReason}
              onChange={(event) => setRejectReason(event.target.value)}
              rows={3}
              placeholder="سبب الرفض (اختياري)"
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
            />
            <div className="flex gap-2">
              <button
                type="submit"
                disabled={busy}
                className="min-h-11 rounded-xl bg-red-700 px-4 text-sm text-white disabled:opacity-60"
              >
                تأكيد الرفض
              </button>
              <button
                type="button"
                className="min-h-11 rounded-xl border px-4 text-sm"
                onClick={() => setShowReject(false)}
              >
                إلغاء
              </button>
            </div>
          </form>
        ) : null}
      </div>
    </div>
  )
}
