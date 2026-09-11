import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { usePlatformSettings } from '../context/PlatformSettingsContext'
import {
  getCustomerPortal,
  type CustomerPortalPayload,
  type CustomerPortalPayment,
} from '../services/phase7'
import { formatMoney } from '../utils/catalog'
import { describeApiError } from '../utils/errors'
import { printingQuotationStatusLabel } from '../utils/printingQuotations'

function paymentItems(payload: CustomerPortalPayload): CustomerPortalPayment[] {
  const payments = payload.payments
  if (Array.isArray(payments)) return payments
  return payments?.items ?? []
}

export function CustomerPortalPage() {
  const { token } = useParams()
  const { settings } = usePlatformSettings()
  const [data, setData] = useState<CustomerPortalPayload | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!token) {
      setError('رابط غير صالح.')
      setLoading(false)
      return
    }

    let cancelled = false
    ;(async () => {
      setLoading(true)
      setError(null)
      try {
        const payload = await getCustomerPortal(token)
        if (!cancelled) setData(payload)
      } catch (caught) {
        if (!cancelled) {
          setError(describeApiError(caught, 'تعذر تحميل بوابة العميل.'))
          setData(null)
        }
      } finally {
        if (!cancelled) setLoading(false)
      }
    })()

    return () => {
      cancelled = true
    }
  }, [token])

  if (loading) {
    return (
      <div dir="rtl" className="min-h-screen bg-brand-canvas px-4 py-10">
        <p className="mx-auto max-w-md text-center text-sm text-slate-600">جاري تحميل البوابة…</p>
      </div>
    )
  }

  if (error || !data) {
    return (
      <div dir="rtl" className="min-h-screen bg-brand-canvas px-4 py-10">
        <div className="mx-auto max-w-md rounded-2xl border border-red-200 bg-white p-5 text-center text-sm text-red-800">
          {error || 'البوابة غير متاحة.'}
        </div>
      </div>
    )
  }

  const quotes = data.quotes ?? data.quotations ?? []
  const payments = paymentItems(data)
  const printing = data.printing ?? []
  const approvals = data.approvals ?? []
  const cards = data.summary_cards ?? []
  const timeline = data.timeline ?? []
  const documents = data.documents ?? data.files ?? []

  return (
    <div dir="rtl" className="min-h-screen bg-gradient-to-b from-brand-canvas via-white to-brand-canvas">
      <div className="mx-auto w-full max-w-md space-y-5 px-4 py-8 sm:max-w-lg">
        <header className="space-y-2 text-center">
          <BrandLogo size="auth" to="/" className="justify-center" />
          <h1 className="text-2xl font-semibold text-slate-900">بوابة العميل</h1>
          <p className="text-sm text-slate-600">
            {settings.customer.welcome_text || data.customer?.name || 'مرحباً بك'}
          </p>
          {settings.customer.support_contact ? (
            <p className="text-xs text-slate-500">الدعم: {settings.customer.support_contact}</p>
          ) : null}
        </header>

        {cards.length > 0 ? (
          <section className="grid grid-cols-2 gap-2">
            {cards.map((card) => (
              <div key={card.key} className="rounded-2xl border border-slate-200 bg-white p-3 text-center">
                <p className="text-lg font-semibold text-slate-900">{card.count.toLocaleString('ar-SA')}</p>
                <p className="text-xs text-slate-600">{card.label}</p>
              </div>
            ))}
          </section>
        ) : null}

        {timeline.length > 0 ? (
          <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
            <h2 className="text-sm font-semibold text-slate-900">الجدول الزمني</h2>
            <ul className="space-y-2">
              {timeline.map((event, index) => (
                <li key={`${event.type}-${index}`} className="rounded-xl border border-slate-100 px-3 py-2 text-sm">
                  <p className="font-medium text-slate-900">{event.title}</p>
                  {event.subtitle ? <p className="text-xs text-slate-600">{event.subtitle}</p> : null}
                  {event.at ? <p className="text-xs text-slate-500">{event.at}</p> : null}
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
          <h2 className="text-sm font-semibold text-slate-900">عروض الأسعار</h2>
          {quotes.length === 0 ? (
            <p className="text-sm text-slate-500">لا عروض حالياً.</p>
          ) : (
            <ul className="space-y-2">
              {quotes.map((quote, index) => (
                <li key={quote.id ?? index} className="rounded-xl border border-slate-100 px-3 py-2 text-sm">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-mono text-xs">{quote.reference || '—'}</span>
                    <span className="text-xs text-slate-600">
                      {printingQuotationStatusLabel(quote.status || '')}
                    </span>
                  </div>
                  {quote.total != null ? (
                    <p className="mt-1">{formatMoney(quote.total, quote.currency || 'EGP')}</p>
                  ) : null}
                  {quote.public_path ? (
                    <Link to={quote.public_path} className="mt-1 inline-block text-xs underline">
                      فتح العرض
                    </Link>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
          <h2 className="text-sm font-semibold text-slate-900">المدفوعات</h2>
          {payments.length === 0 ? (
            <p className="text-sm text-slate-500">لا مدفوعات مسجّلة.</p>
          ) : (
            <ul className="space-y-2">
              {payments.map((payment, index) => (
                <li key={payment.id ?? index} className="rounded-xl border border-slate-100 px-3 py-2 text-sm">
                  <p>{formatMoney(payment.amount ?? 0, payment.currency || 'EGP')}</p>
                  <p className="text-xs text-slate-600">{payment.status || '—'}</p>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
          <h2 className="text-sm font-semibold text-slate-900">طلبات الطباعة</h2>
          {printing.length === 0 ? (
            <p className="text-sm text-slate-500">لا طلبات طباعة.</p>
          ) : (
            <ul className="space-y-2">
              {printing.map((row, index) => (
                <li key={row.id ?? index} className="rounded-xl border border-slate-100 px-3 py-2 text-sm">
                  <p className="font-medium">{row.product_name || `طلب #${row.id}`}</p>
                  <p className="text-xs text-slate-600">{row.status_label || row.status_key || '—'}</p>
                  {row.tracking_path ? (
                    <Link to={row.tracking_path} className="mt-1 inline-block text-xs underline">
                      تتبع الطلب
                    </Link>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
          <h2 className="text-sm font-semibold text-slate-900">الموافقات</h2>
          {approvals.length === 0 ? (
            <p className="text-sm text-slate-500">لا موافقات معلّقة.</p>
          ) : (
            <ul className="space-y-2">
              {approvals.map((row, index) => (
                <li key={row.id ?? index} className="rounded-xl border border-slate-100 px-3 py-2 text-sm">
                  <p className="font-medium">{row.title || row.type || 'موافقة'}</p>
                  <p className="text-xs text-slate-600">{row.status || '—'}</p>
                  {row.action_path ? (
                    <Link to={row.action_path} className="mt-1 inline-block text-xs underline">
                      فتح
                    </Link>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
          <h2 className="text-sm font-semibold text-slate-900">المستندات</h2>
          {documents.length === 0 ? (
            <p className="text-sm text-slate-500">لا مستندات ظاهرة.</p>
          ) : (
            <ul className="space-y-2">
              {documents.map((doc, index) => (
                <li key={`${doc.printing_request_id}-${index}`} className="rounded-xl border border-slate-100 px-3 py-2 text-sm">
                  <p className="font-medium">{doc.name || 'ملف'}</p>
                  <p className="text-xs text-slate-600">{doc.kind || 'document'}</p>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  )
}
