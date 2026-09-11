import { FormEvent, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../components/owner/DashboardSection'
import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { StatusBadge } from '../components/ui/StatusBadge'
import {
  acceptPublicQuotation,
  getPublicQuotation,
  rejectPublicQuotation,
  type PublicQuotationPayload,
} from '../services/crm'
import { formatMoney } from '../utils/catalog'
import { CRM_QUOTATION_STATUS_LABELS } from '../utils/crmNav'

export function PublicQuotationPage() {
  const { token } = useParams()
  const [data, setData] = useState<PublicQuotationPayload | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)
  const [rejectNotes, setRejectNotes] = useState('')
  const [showReject, setShowReject] = useState(false)

  async function load() {
    if (!token) {
      setError('رابط غير صالح.')
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    try {
      const payload = await getPublicQuotation(token)
      setData(payload)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'تعذر تحميل عرض السعر.')
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
      const payload = await acceptPublicQuotation(token)
      setData(payload)
    } catch (caught) {
      setActionError(caught instanceof Error ? caught.message : 'تعذر قبول العرض.')
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
      const payload = await rejectPublicQuotation(token, rejectNotes.trim() || undefined)
      setData(payload)
      setShowReject(false)
    } catch (caught) {
      setActionError(caught instanceof Error ? caught.message : 'تعذر رفض العرض.')
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10">
        <DashboardPanelSkeleton label="جاري تحميل عرض السعر..." />
      </div>
    )
  }

  if (error) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10">
        <DashboardErrorState message={error} onRetry={() => void load()} />
      </div>
    )
  }

  if (!data) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10">
        <DashboardEmptyState title="العرض غير متاح." description="تحقق من الرابط أو تواصل مع فريق المبيعات." />
      </div>
    )
  }

  const canRespond = data.status === 'SENT' || data.status === 'VIEWED'

  return (
    <div className="min-h-screen bg-gradient-to-b from-slate-50 to-amber-50/40">
      <div className="mx-auto max-w-3xl space-y-6 px-4 py-10">
        <header className="space-y-2 text-center">
          <p className="text-sm text-slate-500">حبر وأبعاد</p>
          <h1 className="text-3xl font-semibold text-slate-900">عرض سعر</h1>
          <p className="font-mono text-sm text-slate-600" dir="ltr">
            {data.number}
          </p>
          <div className="flex justify-center">
            <StatusBadge status={data.status} label={CRM_QUOTATION_STATUS_LABELS[data.status] ?? data.status} />
          </div>
        </header>

        {actionError ? <FeedbackBanner kind="error">{actionError}</FeedbackBanner> : null}

        <DashboardSection title="البنود">
          <div className="overflow-x-auto rounded-2xl border bg-white">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-right">
                <tr>
                  <th className="px-3 py-2">الوصف</th>
                  <th className="px-3 py-2">الكمية</th>
                  <th className="px-3 py-2">سعر الوحدة</th>
                  <th className="px-3 py-2">الإجمالي</th>
                </tr>
              </thead>
              <tbody>
                {data.items.map((item, index) => (
                  <tr key={index} className="border-t">
                    <td className="px-3 py-2">{item.description}</td>
                    <td className="px-3 py-2">{Number(item.quantity).toLocaleString('ar-SA')}</td>
                    <td className="px-3 py-2">{formatMoney(item.unit_price, data.currency || 'SAR')}</td>
                    <td className="px-3 py-2">
                      {formatMoney(item.line_total ?? Number(item.quantity) * Number(item.unit_price), data.currency || 'SAR')}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </DashboardSection>

        <div className="grid gap-3 rounded-2xl border bg-white p-4 text-sm sm:grid-cols-2">
          <p>المجموع الفرعي: {formatMoney(data.subtotal, data.currency || 'SAR')}</p>
          <p>الخصم: {formatMoney(data.discount_amount, data.currency || 'SAR')}</p>
          <p>الضريبة: {formatMoney(data.tax_amount, data.currency || 'SAR')}</p>
          <p className="text-lg font-semibold">الإجمالي: {formatMoney(data.total, data.currency || 'SAR')}</p>
          <p>صالح حتى: {data.valid_until ? new Date(data.valid_until).toLocaleDateString('ar-SA') : '—'}</p>
          {data.delivery_time ? <p>مدة التسليم: {data.delivery_time}</p> : null}
        </div>

        {data.notes ? (
          <section className="rounded-2xl border bg-white p-4 text-sm">
            <h2 className="mb-2 font-semibold">ملاحظات</h2>
            <p className="whitespace-pre-wrap text-slate-700">{data.notes}</p>
          </section>
        ) : null}

        {data.terms ? (
          <section className="rounded-2xl border bg-white p-4 text-sm">
            <h2 className="mb-2 font-semibold">الشروط</h2>
            <p className="whitespace-pre-wrap text-slate-700">{data.terms}</p>
          </section>
        ) : null}

        {canRespond ? (
          <div className="flex flex-wrap justify-center gap-3">
            <button
              type="button"
              disabled={busy}
              className="min-h-11 rounded-xl bg-emerald-700 px-6 text-sm text-white disabled:opacity-60"
              onClick={() => void handleAccept()}
            >
              قبول العرض
            </button>
            <button
              type="button"
              disabled={busy}
              className="min-h-11 rounded-xl border border-red-300 px-6 text-sm text-red-800 disabled:opacity-60"
              onClick={() => setShowReject(true)}
            >
              رفض
            </button>
          </div>
        ) : (
          <p className="text-center text-sm text-slate-600">حالة العرض الحالية لا تسمح بالرد.</p>
        )}

        {showReject ? (
          <form onSubmit={(event) => void handleReject(event)} className="space-y-3 rounded-2xl border border-red-200 bg-red-50/40 p-4">
            <textarea
              value={rejectNotes}
              onChange={(event) => setRejectNotes(event.target.value)}
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
