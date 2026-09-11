import { FormEvent, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  cancelQuoteRequest,
  createCommercialQuotationFromRequest,
  getOwnerQuoteRequest,
  requestQuoteInformation,
  startQuoteRequestReview,
  type CommercialQuotation,
  type QuoteRequest,
} from '../../services/quoteRequests'
import { ApiRequestError } from '../../services/api'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'
import {
  commercialQuotationStatusLabel,
  extractLatestRevisionReason,
  formatQuoteDate,
  quoteEventLabelAr,
  quoteRequestSourceLabel,
  quoteRequestStatusLabel,
  revisionReasonLabelAr,
} from '../../utils/quoteRequests'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function OwnerQuoteRequestDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const quoteId = Number(id)

  const [item, setItem] = useState<QuoteRequest | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [infoMessage, setInfoMessage] = useState('')
  const [showInfo, setShowInfo] = useState(false)
  const [cancelReason, setCancelReason] = useState('')
  const [showCancel, setShowCancel] = useState(false)

  async function load() {
    if (!Number.isFinite(quoteId) || quoteId <= 0) {
      setError('معرّف غير صالح.')
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    try {
      const response = await getOwnerQuoteRequest(quoteId)
      setItem(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الطلب.'))
      setItem(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [quoteId])

  async function runAction(label: string, action: () => Promise<unknown>) {
    if (busy) return
    setBusy(true)
    setError(null)
    setNotice(null)
    try {
      await action()
      setNotice(label)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنفيذ الإجراء.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleStartReview() {
    await runAction('بدأت المراجعة.', () => startQuoteRequestReview(quoteId))
  }

  async function handleRequestInfo(event: FormEvent) {
    event.preventDefault()
    if (!infoMessage.trim()) return
    await runAction('تم طلب معلومات إضافية.', async () => {
      await requestQuoteInformation(quoteId, infoMessage.trim())
      setShowInfo(false)
      setInfoMessage('')
    })
  }

  async function handleCancel(event: FormEvent) {
    event.preventDefault()
    await runAction('تم إلغاء الطلب.', async () => {
      await cancelQuoteRequest(quoteId, cancelReason.trim() || null)
      setShowCancel(false)
    })
  }

  async function handleCreateQuotation() {
    if (busy || !item) return
    if (String(item.source_type) === 'PRINTING_REQUEST') {
      setNotice('طلبات الطباعة تُسعَّر عبر عروض الطباعة الحالية.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      const response = await createCommercialQuotationFromRequest(quoteId)
      setNotice(`تم إنشاء العرض ${response.data.reference}`)
      navigate(`/owner/commercial-quotations/${response.data.id}`)
    } catch (caught) {
      if (caught instanceof ApiRequestError && caught.status === 422) {
        setError(caught.message || 'تعذر إنشاء العرض.')
      } else {
        setError(describeApiError(caught, 'تعذر إنشاء العرض.'))
      }
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return <DashboardPanelSkeleton label="جاري تحميل الطلب..." />
  }

  if (error && !item) {
    return <DashboardErrorState message={error} onRetry={() => void load()} />
  }

  if (!item) {
    return <DashboardEmptyState title="الطلب غير موجود." description="تحقق من الرابط أو عد إلى قائمة الطلبات." />
  }

  const quotations = (item.commercial_quotations ?? []) as CommercialQuotation[]
  const payload = item.payload && typeof item.payload === 'object' ? item.payload : {}
  const lineItems = Array.isArray((payload as { line_items?: unknown }).line_items)
    ? ((payload as { line_items: Array<Record<string, unknown>> }).line_items)
    : []
  const isPrinting = String(item.source_type) === 'PRINTING_REQUEST'
  const isRevisionRequested = String(item.status) === 'REVISION_REQUESTED'
  const latestRevision = extractLatestRevisionReason(item.events)
  const latestQuotation = quotations.length
    ? [...quotations].sort((a, b) => (b.revision ?? 0) - (a.revision ?? 0))[0]
    : null
  const orderId = item.order_id ?? item.order?.id ?? null
  const projectId =
    typeof (payload as { project_id?: unknown }).project_id === 'number'
      ? (payload as { project_id: number }).project_id
      : latestQuotation?.project_id ?? latestQuotation?.linkages?.project_id ?? null

  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <Link to="/owner/quote-requests" className="text-sm text-[#315CFF] underline">
          ← طلبات التسعير
        </Link>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-[#111318]">{item.title}</h1>
            <p className="mt-1 font-mono text-sm text-slate-500" dir="ltr">
              {item.reference}
            </p>
            <p className="mt-1 text-sm text-slate-600">
              {quoteRequestStatusLabel(String(item.status))} ·{' '}
              {quoteRequestSourceLabel(String(item.source_type))}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              disabled={busy}
              className="rounded-lg bg-[#111318] px-3 py-2 text-sm text-white disabled:opacity-50"
              onClick={() => void handleStartReview()}
            >
              بدء المراجعة
            </button>
            <button
              type="button"
              disabled={busy}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-50"
              onClick={() => setShowInfo((v) => !v)}
            >
              طلب معلومات
            </button>
            {isPrinting ? (
              <Link
                to={`/owner/printing-quotations?printing_request_id=${item.source_id ?? ''}`}
                className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950"
              >
                عروض الطباعة
              </Link>
            ) : (
              <button
                type="button"
                disabled={busy}
                className="rounded-lg bg-[#315CFF] px-3 py-2 text-sm text-white disabled:opacity-50"
                onClick={() => void handleCreateQuotation()}
              >
                إنشاء عرض سعر
              </button>
            )}
            <button
              type="button"
              disabled={busy}
              className="rounded-lg border border-red-300 px-3 py-2 text-sm text-red-800 disabled:opacity-50"
              onClick={() => setShowCancel((v) => !v)}
            >
              إلغاء
            </button>
          </div>
        </div>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      {isRevisionRequested ? (
        <div className="space-y-2 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-4">
          <p className="text-sm font-semibold text-amber-950">طلب تعديل من العميل — يحتاج متابعة</p>
          {latestRevision?.reason_code ? (
            <p className="text-sm text-amber-900">
              السبب: {revisionReasonLabelAr(latestRevision.reason_code)}
              {latestRevision.reason ? ` — ${latestRevision.reason}` : ''}
            </p>
          ) : latestRevision?.reason ? (
            <p className="text-sm text-amber-900">السبب: {latestRevision.reason}</p>
          ) : (
            <p className="text-sm text-amber-900">راجع تفاصيل طلب التعديل في الخط الزمني.</p>
          )}
          {latestQuotation ? (
            <Link
              to={`/owner/commercial-quotations/${latestQuotation.id}`}
              className="inline-flex rounded-lg bg-[#315CFF] px-3 py-2 text-sm text-white"
            >
              فتح محرر عرض السعر
            </Link>
          ) : null}
        </div>
      ) : null}

      {(orderId || projectId) ? (
        <div className="flex flex-wrap gap-3 text-sm">
          {orderId ? (
            <Link to={`/owner/orders/${orderId}`} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[#315CFF] underline">
              الطلب المرتبط #{orderId}
            </Link>
          ) : null}
          {projectId ? (
            <Link to={`/owner/projects/${projectId}`} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[#315CFF] underline">
              المشروع المرتبط #{projectId}
            </Link>
          ) : null}
        </div>
      ) : null}

      {isPrinting ? (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
          مصدر هذا الطلب طباعة — استخدم مسار{' '}
          <Link to="/owner/printing-quotations" className="underline">
            عروض الطباعة (PrintingQuotation)
          </Link>{' '}
          بدلاً من العرض التجاري.
        </div>
      ) : null}

      {showInfo ? (
        <form onSubmit={(event) => void handleRequestInfo(event)} className="space-y-3 rounded-xl border bg-white p-4">
          <label className="block space-y-1 text-sm">
            <span>رسالة طلب المعلومات</span>
            <textarea
              className={fieldClass}
              rows={3}
              value={infoMessage}
              onChange={(e) => setInfoMessage(e.target.value)}
              required
            />
          </label>
          <button type="submit" disabled={busy} className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">
            إرسال
          </button>
        </form>
      ) : null}

      {showCancel ? (
        <form onSubmit={(event) => void handleCancel(event)} className="space-y-3 rounded-xl border border-red-200 bg-red-50/40 p-4">
          <label className="block space-y-1 text-sm">
            <span>سبب الإلغاء (اختياري)</span>
            <textarea
              className={fieldClass}
              rows={2}
              value={cancelReason}
              onChange={(e) => setCancelReason(e.target.value)}
            />
          </label>
          <button type="submit" disabled={busy} className="rounded-lg bg-red-700 px-4 py-2 text-sm text-white">
            تأكيد الإلغاء
          </button>
        </form>
      ) : null}

      <DashboardSection title="بيانات العميل">
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-xs text-slate-500">الاسم</dt>
            <dd className="font-medium">{item.customer?.name || '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">البريد</dt>
            <dd className="font-medium" dir="ltr">
              {item.customer?.email || '—'}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">الهاتف</dt>
            <dd className="font-medium" dir="ltr">
              {item.customer?.phone || '—'}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">المدينة / الموعد</dt>
            <dd className="font-medium">
              {item.city || '—'} · {formatQuoteDate(item.required_date)}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">الميزانية</dt>
            <dd className="font-medium">
              {item.budget_min != null ? formatMoney(item.budget_min) : '—'} —{' '}
              {item.budget_max != null ? formatMoney(item.budget_max) : '—'}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">المسؤول</dt>
            <dd className="font-medium">{item.assignee?.name || '—'}</dd>
          </div>
        </dl>
      </DashboardSection>

      {item.customer_notes ? (
        <DashboardSection title="ملاحظات العميل">
          <p className="whitespace-pre-wrap text-sm text-slate-700">{item.customer_notes}</p>
        </DashboardSection>
      ) : null}

      {item.information_request ? (
        <DashboardSection title="طلب معلومات مفتوح">
          <p className="whitespace-pre-wrap text-sm text-amber-950">{item.information_request}</p>
        </DashboardSection>
      ) : null}

      {item.internal_notes ? (
        <DashboardSection title="ملاحظات داخلية">
          <p className="whitespace-pre-wrap text-sm text-slate-700">{item.internal_notes}</p>
        </DashboardSection>
      ) : null}

      {lineItems.length > 0 ? (
        <DashboardSection title="بنود المصدر">
          <ul className="space-y-2 text-sm">
            {lineItems.map((line, index) => (
              <li key={index} className="rounded-lg border border-slate-100 px-3 py-2">
                <span className="font-medium">{String(line.description ?? 'بند')}</span>
                <span className="text-slate-500">
                  {' '}
                  · كمية {String(line.quantity ?? 1)}
                  {line.unit_price != null ? ` · ${formatMoney(line.unit_price as string | number)}` : ''}
                </span>
              </li>
            ))}
          </ul>
        </DashboardSection>
      ) : null}

      {item.files && item.files.length > 0 ? (
        <DashboardSection title="الملفات">
          <ul className="space-y-1 text-sm">
            {item.files.map((file) => (
              <li key={file.id}>
                #{file.id} — {file.original_name || 'ملف'}
              </li>
            ))}
          </ul>
        </DashboardSection>
      ) : null}

      <DashboardSection title="عروض الأسعار المرتبطة">
        {quotations.length === 0 ? (
          <p className="text-sm text-slate-600">لا توجد عروض بعد.</p>
        ) : (
          <ul className="space-y-2 text-sm">
            {quotations.map((q) => (
              <li key={q.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2">
                <span className="font-mono text-xs" dir="ltr">
                  {q.reference} · R{q.revision}
                </span>
                <span>{commercialQuotationStatusLabel(String(q.status))}</span>
                <span>{formatMoney(q.total, q.currency || 'EGP')}</span>
                <Link to={`/owner/commercial-quotations/${q.id}`} className="underline">
                  تحرير
                </Link>
              </li>
            ))}
          </ul>
        )}
      </DashboardSection>

      <DashboardSection title="الخط الزمني">
        {!item.events?.length ? (
          <p className="text-sm text-slate-600">لا أحداث بعد.</p>
        ) : (
          <ul className="space-y-2 text-sm">
            {item.events.map((event) => {
              const metaReason =
                typeof event.meta?.reason === 'string' ? event.meta.reason : null
              const metaCode =
                typeof event.meta?.reason_code === 'string' ? event.meta.reason_code : null
              return (
                <li key={event.id} className="rounded-lg border border-slate-100 px-3 py-2">
                  <div className="flex flex-wrap justify-between gap-2">
                    <span className="font-medium">{quoteEventLabelAr(event.event_type)}</span>
                    <span className="text-xs text-slate-500">{formatQuoteDate(event.created_at)}</span>
                  </div>
                  <p className="text-xs text-slate-500">
                    {event.actor?.name || event.actor_type || '—'}
                  </p>
                  {metaCode || metaReason ? (
                    <p className="mt-1 text-xs text-slate-700">
                      {metaCode ? revisionReasonLabelAr(metaCode) : null}
                      {metaCode && metaReason ? ' — ' : null}
                      {metaReason}
                    </p>
                  ) : null}
                </li>
              )
            })}
          </ul>
        )}
      </DashboardSection>
    </section>
  )
}
