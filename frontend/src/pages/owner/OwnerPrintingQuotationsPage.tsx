import { FormEvent, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  createPrintingQuotation,
  downloadPrintingQuotationPdf,
  getPrintingQuotation,
  getPrintingQuotationEligibility,
  getPrintingQuotations,
  recordPrintingQuotationPayment,
  sendPrintingQuotation,
  type PrintingExecutionEligibility,
  type PrintingQuotation,
} from '../../services/printingQuotations'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'
import {
  absolutePublicPath,
  printingPaymentPolicyLabel,
  printingPublicQuotePath,
  printingQuotationStatusLabel,
} from '../../utils/printingQuotations'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function OwnerPrintingQuotationsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const selectedId = searchParams.get('id') ? Number(searchParams.get('id')) : null
  const requestFromQuery = searchParams.get('printing_request_id') || ''

  const [items, setItems] = useState<PrintingQuotation[]>([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
  const [page, setPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [detail, setDetail] = useState<PrintingQuotation | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [eligibility, setEligibility] = useState<PrintingExecutionEligibility | null>(null)
  const [busy, setBusy] = useState(false)
  const [copyLink, setCopyLink] = useState<string | null>(null)

  const [showCreate, setShowCreate] = useState(Boolean(requestFromQuery))
  const [printingRequestId, setPrintingRequestId] = useState(requestFromQuery)
  const [total, setTotal] = useState('')
  const [depositRequired, setDepositRequired] = useState('')
  const [paymentPolicy, setPaymentPolicy] = useState('NONE')
  const [validUntil, setValidUntil] = useState('')
  const [notes, setNotes] = useState('')
  const [terms, setTerms] = useState('')

  const [payAmount, setPayAmount] = useState('')
  const [payMethod, setPayMethod] = useState('INSTAPAY')
  const [payReference, setPayReference] = useState('')
  const [payMarkPaid, setPayMarkPaid] = useState(true)

  async function loadList() {
    setLoading(true)
    setError(null)
    try {
      const response = await getPrintingQuotations({
        page,
        per_page: 20,
        status: statusFilter || undefined,
        printing_request_id: requestFromQuery ? Number(requestFromQuery) : undefined,
      })
      setItems(response.data.items ?? [])
      setMeta(response.data.meta)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل عروض أسعار الطباعة.'))
      setItems([])
    } finally {
      setLoading(false)
    }
  }

  async function loadDetail(id: number) {
    setDetailLoading(true)
    try {
      const response = await getPrintingQuotation(id)
      setDetail(response.data)
      if (response.data.printing_request_id) {
        try {
          const elig = await getPrintingQuotationEligibility(response.data.printing_request_id)
          setEligibility(elig.data)
        } catch {
          setEligibility(null)
        }
      } else {
        setEligibility(null)
      }
    } catch (caught) {
      setNotice(describeApiError(caught, 'تعذر تحميل العرض.'))
      setDetail(null)
      setEligibility(null)
    } finally {
      setDetailLoading(false)
    }
  }

  useEffect(() => {
    void loadList()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, statusFilter, requestFromQuery])

  useEffect(() => {
    if (selectedId && Number.isFinite(selectedId)) {
      void loadDetail(selectedId)
    } else {
      setDetail(null)
      setEligibility(null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedId])

  function selectRow(id: number) {
    const next = new URLSearchParams(searchParams)
    next.set('id', String(id))
    setSearchParams(next)
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault()
    const requestId = Number(printingRequestId)
    if (!Number.isFinite(requestId) || requestId <= 0) {
      setError('معرّف طلب الطباعة مطلوب.')
      return
    }

    setBusy(true)
    setError(null)
    setNotice(null)
    try {
      const response = await createPrintingQuotation({
        printing_request_id: requestId,
        total: total || undefined,
        subtotal: total || undefined,
        deposit_required: depositRequired || undefined,
        payment_policy: paymentPolicy,
        valid_until: validUntil || null,
        notes: notes || null,
        terms: terms || null,
        currency: 'EGP',
      })
      setShowCreate(false)
      setNotice(`تم إنشاء العرض ${response.data.reference}`)
      selectRow(response.data.id)
      await loadList()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إنشاء العرض.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleSend() {
    if (!detail || busy) return
    setBusy(true)
    setError(null)
    setCopyLink(null)
    try {
      const response = await sendPrintingQuotation(detail.id)
      const token = response.data.public_token
      const path =
        response.data.public_path ||
        response.data.public_url ||
        (token ? printingPublicQuotePath(token) : null)
      if (path) {
        setCopyLink(absolutePublicPath(path))
        setNotice('تم الإرسال. انسخ الرابط الآن — يظهر مرة واحدة فقط.')
      } else {
        setNotice('تم إرسال العرض.')
      }
      setDetail(response.data)
      await loadList()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إرسال العرض.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleCopyLink() {
    if (!copyLink) return
    try {
      await navigator.clipboard.writeText(copyLink)
      setNotice('تم نسخ الرابط.')
    } catch {
      setNotice(`الرابط: ${copyLink}`)
    }
  }

  async function handlePdf() {
    if (!detail || busy) return
    setBusy(true)
    try {
      await downloadPrintingQuotationPdf(detail.id, detail.reference)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنزيل PDF.'))
    } finally {
      setBusy(false)
    }
  }

  async function handlePayment(event: FormEvent) {
    event.preventDefault()
    if (!detail || busy) return
    setBusy(true)
    setError(null)
    try {
      const response = await recordPrintingQuotationPayment(detail.id, {
        method: payMethod,
        amount: payAmount || undefined,
        reference_number: payReference || null,
        mark_paid: payMarkPaid,
      })
      setDetail(response.data.quotation)
      setNotice('تم تسجيل الدفعة.')
      setPayAmount('')
      setPayReference('')
      if (detail.printing_request_id) {
        const elig = await getPrintingQuotationEligibility(detail.printing_request_id)
        setEligibility(elig.data)
      }
      await loadList()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تسجيل الدفعة.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">عروض أسعار الطباعة</h1>
          <p className="mt-1 text-sm text-slate-600">إنشاء وإرسال العروض وتسجيل الدفعات ومتابعة الأهلية.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link to="/owner/printing-ops" className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            تشغيل الطباعة
          </Link>
          <button
            type="button"
            className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white"
            onClick={() => setShowCreate((value) => !value)}
          >
            {showCreate ? 'إخفاء النموذج' : 'عرض جديد'}
          </button>
        </div>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      {copyLink ? (
        <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm">
          <span className="break-all font-mono text-xs" dir="ltr">
            {copyLink}
          </span>
          <button type="button" className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white" onClick={() => void handleCopyLink()}>
            نسخ الرابط
          </button>
        </div>
      ) : null}

      {showCreate ? (
        <DashboardSection title="إنشاء عرض من طلب طباعة">
          <form onSubmit={(event) => void handleCreate(event)} className="grid gap-3 sm:grid-cols-2">
            <label className="space-y-1 text-sm">
              <span>معرّف طلب الطباعة</span>
              <input
                className={fieldClass}
                value={printingRequestId}
                onChange={(event) => setPrintingRequestId(event.target.value)}
                required
              />
            </label>
            <label className="space-y-1 text-sm">
              <span>الإجمالي</span>
              <input className={fieldClass} value={total} onChange={(event) => setTotal(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span>سياسة الدفع</span>
              <select className={fieldClass} value={paymentPolicy} onChange={(event) => setPaymentPolicy(event.target.value)}>
                <option value="NONE">بدون دفع مسبق</option>
                <option value="DEPOSIT">عربون</option>
                <option value="FULL">دفع كامل</option>
              </select>
            </label>
            <label className="space-y-1 text-sm">
              <span>العربون المطلوب</span>
              <input
                className={fieldClass}
                value={depositRequired}
                onChange={(event) => setDepositRequired(event.target.value)}
              />
            </label>
            <label className="space-y-1 text-sm">
              <span>صالح حتى</span>
              <input
                type="date"
                className={fieldClass}
                value={validUntil}
                onChange={(event) => setValidUntil(event.target.value)}
              />
            </label>
            <label className="space-y-1 text-sm sm:col-span-2">
              <span>ملاحظات</span>
              <textarea className={fieldClass} rows={2} value={notes} onChange={(event) => setNotes(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm sm:col-span-2">
              <span>الشروط</span>
              <textarea className={fieldClass} rows={2} value={terms} onChange={(event) => setTerms(event.target.value)} />
            </label>
            <button
              type="submit"
              disabled={busy}
              className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white disabled:opacity-50 sm:col-span-2"
            >
              إنشاء
            </button>
          </form>
        </DashboardSection>
      ) : null}

      <div className="flex flex-wrap gap-2">
        <select
          className={`${fieldClass} max-w-xs`}
          value={statusFilter}
          onChange={(event) => {
            setPage(1)
            setStatusFilter(event.target.value)
          }}
        >
          <option value="">كل الحالات</option>
          {['DRAFT', 'SENT', 'VIEWED', 'ACCEPTED', 'REJECTED', 'EXPIRED', 'CANCELLED'].map((status) => (
            <option key={status} value={status}>
              {printingQuotationStatusLabel(status)}
            </option>
          ))}
        </select>
      </div>

      {loading ? <DashboardPanelSkeleton label="جاري تحميل العروض..." /> : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void loadList()} />
      ) : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا عروض بعد" description="أنشئ عرضاً من طلب طباعة لبدء الإرسال." />
      ) : null}

      {!loading && items.length > 0 ? (
        <DashboardSection title={`العروض (${meta.total.toLocaleString('ar-SA')})`}>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-slate-500">
                  <th className="px-2 py-2 text-start font-medium">المرجع</th>
                  <th className="px-2 py-2 text-start font-medium">الطلب</th>
                  <th className="px-2 py-2 text-start font-medium">الحالة</th>
                  <th className="px-2 py-2 text-start font-medium">الإجمالي</th>
                  <th className="px-2 py-2 text-start font-medium"> </th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id} className="border-b border-slate-100">
                    <td className="px-2 py-2 font-mono text-xs">
                      {item.reference}
                      <span className="text-slate-400"> · R{item.revision}</span>
                    </td>
                    <td className="px-2 py-2">
                      {item.printing_request?.product_name || `#${item.printing_request_id}`}
                    </td>
                    <td className="px-2 py-2">{printingQuotationStatusLabel(item.status)}</td>
                    <td className="px-2 py-2">{formatMoney(item.total, item.currency || 'EGP')}</td>
                    <td className="px-2 py-2">
                      <button type="button" className="underline" onClick={() => selectRow(item.id)}>
                        تفاصيل
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {meta.last_page > 1 ? (
            <div className="mt-3 flex gap-2">
              <button
                type="button"
                disabled={page <= 1}
                className="rounded border px-3 py-1 text-xs disabled:opacity-40"
                onClick={() => setPage((value) => Math.max(1, value - 1))}
              >
                السابق
              </button>
              <span className="text-xs text-slate-500">
                {page} / {meta.last_page}
              </span>
              <button
                type="button"
                disabled={page >= meta.last_page}
                className="rounded border px-3 py-1 text-xs disabled:opacity-40"
                onClick={() => setPage((value) => value + 1)}
              >
                التالي
              </button>
            </div>
          ) : null}
        </DashboardSection>
      ) : null}

      {detailLoading ? <DashboardPanelSkeleton label="جاري تحميل التفاصيل..." /> : null}

      {detail && !detailLoading ? (
        <DashboardSection title={`${detail.reference} · R${detail.revision}`}>
          <div className="mb-4 flex flex-wrap gap-2">
            <span className="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs">
              {printingQuotationStatusLabel(detail.status)}
            </span>
            {eligibility ? (
              <span
                className={`rounded-full border px-3 py-1 text-xs ${
                  eligibility.eligible
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
                    : 'border-amber-300 bg-amber-50 text-amber-950'
                }`}
              >
                {eligibility.eligible ? 'مؤهل للتنفيذ' : `غير مؤهل: ${(eligibility.reasons || []).join('، ') || '—'}`}
              </span>
            ) : null}
            {detail.payment_policy ? (
              <span className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs">
                {printingPaymentPolicyLabel(detail.payment_policy)}
              </span>
            ) : null}
          </div>

          <div className="grid gap-2 text-sm sm:grid-cols-2">
            <p>الإجمالي: {formatMoney(detail.total, detail.currency || 'EGP')}</p>
            <p>
              الطلب:{' '}
              <Link to={`/printing-requests/${detail.printing_request_id}`} className="underline">
                #{detail.printing_request_id}
              </Link>
            </p>
            <p>صالح حتى: {detail.valid_until || '—'}</p>
            <p>العميل: {detail.customer?.name || '—'}</p>
          </div>

          <div className="mt-4 flex flex-wrap gap-2">
            {detail.status === 'DRAFT' || detail.status === 'SENT' ? (
              <button
                type="button"
                disabled={busy}
                className="rounded-lg bg-emerald-700 px-3 py-2 text-sm text-white disabled:opacity-50"
                onClick={() => void handleSend()}
              >
                إرسال العرض
              </button>
            ) : null}
            <button
              type="button"
              disabled={busy}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-50"
              onClick={() => void handlePdf()}
            >
              تنزيل PDF
            </button>
          </div>

          {(detail.status === 'ACCEPTED' || detail.status === 'VIEWED' || detail.status === 'SENT') && (
            <form onSubmit={(event) => void handlePayment(event)} className="mt-6 grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-2">
              <h3 className="text-sm font-semibold sm:col-span-2">تسجيل دفعة</h3>
              <label className="space-y-1 text-sm">
                <span>المبلغ</span>
                <input className={fieldClass} value={payAmount} onChange={(event) => setPayAmount(event.target.value)} />
              </label>
              <label className="space-y-1 text-sm">
                <span>الطريقة</span>
                <select className={fieldClass} value={payMethod} onChange={(event) => setPayMethod(event.target.value)}>
                  <option value="INSTAPAY">Instapay</option>
                  <option value="BANK_TRANSFER">تحويل بنكي</option>
                </select>
              </label>
              <label className="space-y-1 text-sm">
                <span>رقم المرجع</span>
                <input
                  className={fieldClass}
                  value={payReference}
                  onChange={(event) => setPayReference(event.target.value)}
                />
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={payMarkPaid}
                  onChange={(event) => setPayMarkPaid(event.target.checked)}
                />
                تعليم كمدفوع فوراً
              </label>
              <button
                type="submit"
                disabled={busy}
                className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50 sm:col-span-2"
              >
                حفظ الدفعة
              </button>
            </form>
          )}
        </DashboardSection>
      ) : null}
    </section>
  )
}
