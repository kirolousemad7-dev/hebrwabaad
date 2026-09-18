import { FormEvent, useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { QuotationSourcingPanel } from '../../components/quotes/QuotationSourcingPanel'
import { MediaUploader } from '../../components/files/MediaUploader'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  downloadCommercialQuotationPdf,
  getCommercialQuotation,
  previewCommercialQuotation,
  reviseCommercialQuotation,
  sendCommercialQuotation,
  updateCommercialQuotation,
  type CommercialQuotation,
  type PublicCommercialQuotation,
} from '../../services/quoteRequests'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'
import { absolutePublicPath, printingPaymentPolicyLabel } from '../../utils/printingQuotations'
import {
  QUOTE_LINE_CATEGORIES,
  QUOTE_LINE_CATEGORY_LABELS,
  absoluteCommercialQuotePath,
  commercialQuotationStatusLabel,
  formatQuoteDate,
} from '../../utils/quoteRequests'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315CFF]'

type DraftLine = {
  key: string
  description: string
  quantity: string
  unit_price: string
  category: string
}

function newLine(partial?: Partial<DraftLine>): DraftLine {
  return {
    key: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    description: partial?.description ?? '',
    quantity: partial?.quantity ?? '1',
    unit_price: partial?.unit_price ?? '0',
    category: partial?.category ?? 'OTHER',
  }
}

function linesFromQuotation(quotation: CommercialQuotation): DraftLine[] {
  const items = quotation.items ?? []
  if (items.length === 0) {
    return [newLine()]
  }
  return items.map((item) =>
    newLine({
      description: item.description ?? '',
      quantity: String(item.quantity ?? '1'),
      unit_price: String(item.unit_price ?? '0'),
      category: String(item.category ?? 'OTHER'),
    }),
  )
}

function SummaryRows({
  currency,
  subtotal,
  discount,
  tax,
  shipping,
  rental,
  total,
  estimated,
}: {
  currency: string
  subtotal: number
  discount: number
  tax: number
  shipping: number
  rental: number
  total: number
  estimated: boolean
}) {
  return (
    <dl className="space-y-2 text-sm">
      {estimated ? (
        <p className="text-xs text-slate-500">تقديري — الإجمالي النهائي يُحسب من الخادم بعد الحفظ</p>
      ) : null}
      <div className="flex justify-between gap-3">
        <dt className="text-slate-600">الإجمالي الفرعي</dt>
        <dd className="font-medium text-[#111318]">{formatMoney(subtotal, currency)}</dd>
      </div>
      <div className="flex justify-between gap-3">
        <dt className="text-slate-600">الخصم</dt>
        <dd>{formatMoney(discount, currency)}</dd>
      </div>
      <div className="flex justify-between gap-3">
        <dt className="text-slate-600">الضريبة</dt>
        <dd>{formatMoney(tax, currency)}</dd>
      </div>
      <div className="flex justify-between gap-3">
        <dt className="text-slate-600">الشحن</dt>
        <dd>{formatMoney(shipping, currency)}</dd>
      </div>
      <div className="flex justify-between gap-3">
        <dt className="text-slate-600">التأجير</dt>
        <dd>{formatMoney(rental, currency)}</dd>
      </div>
      <div className="flex justify-between gap-3 border-t border-slate-200 pt-2 text-base">
        <dt className="font-semibold text-[#111318]">الإجمالي النهائي</dt>
        <dd className="font-semibold text-[#315CFF]">{formatMoney(total, currency)}</dd>
      </div>
    </dl>
  )
}

export function OwnerCommercialQuotationEditorPage() {
  const { id } = useParams()
  const quotationId = Number(id)

  const [quotation, setQuotation] = useState<CommercialQuotation | null>(null)
  const [preview, setPreview] = useState<PublicCommercialQuotation | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [publicLink, setPublicLink] = useState<string | null>(null)
  const [dirty, setDirty] = useState(false)
  const [showSendConfirm, setShowSendConfirm] = useState(false)
  const [pdfBusy, setPdfBusy] = useState(false)
  const [activeTab, setActiveTab] = useState<'customer' | 'internal' | 'sourcing'>('internal')

  const [lines, setLines] = useState<DraftLine[]>([newLine()])
  const [discount, setDiscount] = useState('')
  const [tax, setTax] = useState('')
  const [shipping, setShipping] = useState('')
  const [rental, setRental] = useState('')
  const [deposit, setDeposit] = useState('')
  const [paymentPolicy, setPaymentPolicy] = useState('NONE')
  const [validUntil, setValidUntil] = useState('')
  const [duration, setDuration] = useState('')
  const [revisionCount, setRevisionCount] = useState('')
  const [terms, setTerms] = useState('')
  const [notes, setNotes] = useState('')
  const [internalNotes, setInternalNotes] = useState('')

  function markDirty() {
    setDirty(true)
  }

  function applyQuotation(data: CommercialQuotation) {
    setQuotation(data)
    setLines(linesFromQuotation(data))
    setDiscount(data.discount_amount != null ? String(data.discount_amount) : '')
    setTax(data.tax_amount != null ? String(data.tax_amount) : '')
    setShipping(data.shipping_amount != null ? String(data.shipping_amount) : '')
    setRental(data.rental_amount != null ? String(data.rental_amount) : '')
    setDeposit(data.deposit_required != null ? String(data.deposit_required) : '')
    setPaymentPolicy(String(data.payment_policy || 'NONE'))
    setValidUntil(data.valid_until ? String(data.valid_until).slice(0, 10) : '')
    setDuration(data.execution_duration ?? '')
    setRevisionCount(data.revision_count != null ? String(data.revision_count) : '')
    setTerms(data.terms ?? '')
    setNotes(data.notes ?? '')
    setInternalNotes(data.internal_notes ?? '')
    setDirty(false)
  }

  async function load() {
    if (!Number.isFinite(quotationId) || quotationId <= 0) {
      setError('معرّف غير صالح.')
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    try {
      const response = await getCommercialQuotation(quotationId)
      applyQuotation(response.data)
      try {
        const previewResponse = await previewCommercialQuotation(quotationId)
        setPreview(previewResponse.data)
      } catch {
        setPreview(null)
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل العرض.'))
      setQuotation(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [quotationId])

  const liveSubtotal = useMemo(() => {
    return lines.reduce((sum, line) => {
      const qty = Number(line.quantity) || 0
      const price = Number(line.unit_price) || 0
      return sum + qty * price
    }, 0)
  }, [lines])

  const liveDiscount = Number(discount) || 0
  const liveTax = Number(tax) || 0
  const liveShipping = Number(shipping) || 0
  const liveRental = Number(rental) || 0

  const liveTotal = useMemo(() => {
    return liveSubtotal - liveDiscount + liveTax + liveShipping + liveRental
  }, [liveSubtotal, liveDiscount, liveTax, liveShipping, liveRental])

  const useServerTotals = Boolean(quotation) && !dirty
  const summarySubtotal = useServerTotals ? Number(quotation?.subtotal ?? 0) : liveSubtotal
  const summaryDiscount = useServerTotals ? Number(quotation?.discount_amount ?? 0) : liveDiscount
  const summaryTax = useServerTotals ? Number(quotation?.tax_amount ?? 0) : liveTax
  const summaryShipping = useServerTotals ? Number(quotation?.shipping_amount ?? 0) : liveShipping
  const summaryRental = useServerTotals ? Number(quotation?.rental_amount ?? 0) : liveRental
  const summaryTotal = useServerTotals ? Number(quotation?.total ?? 0) : liveTotal

  async function persistDraft(): Promise<CommercialQuotation | null> {
    if (!quotation) return null
    const validLines = lines.filter((line) => line.description.trim())
    if (validLines.length === 0) {
      setError('أضف بنداً واحداً على الأقل.')
      return null
    }

    const response = await updateCommercialQuotation(quotation.id, {
      discount_amount: discount || '0',
      tax_amount: tax || '0',
      shipping_amount: shipping || '0',
      rental_amount: rental || '0',
      deposit_required: deposit || null,
      payment_policy: paymentPolicy,
      valid_until: validUntil || null,
      execution_duration: duration || null,
      revision_count: revisionCount ? Number(revisionCount) : null,
      terms: terms || null,
      notes: notes || null,
      internal_notes: internalNotes || null,
      items: validLines.map((line) => ({
        description: line.description.trim(),
        quantity: line.quantity || '1',
        unit_price: line.unit_price || '0',
        category: line.category || 'OTHER',
      })),
    })
    applyQuotation(response.data)
    return response.data
  }

  async function handleSave(event?: FormEvent) {
    event?.preventDefault()
    if (!quotation || busy) return
    setBusy(true)
    setError(null)
    setNotice(null)
    try {
      const saved = await persistDraft()
      if (!saved) return
      setNotice('تم حفظ العرض.')
      const previewResponse = await previewCommercialQuotation(saved.id)
      setPreview(previewResponse.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ العرض.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleSendConfirmed() {
    if (!quotation || busy) return
    setBusy(true)
    setError(null)
    setPublicLink(null)
    setShowSendConfirm(false)
    try {
      const saved = await persistDraft()
      if (!saved) return
      const response = await sendCommercialQuotation(saved.id)
      applyQuotation(response.data.quotation)
      const path = response.data.public_url || `/cq/${response.data.public_token}`
      setPublicLink(absolutePublicPath(path))
      setNotice('تم إرسال العرض. انسخ الرابط الآن — يظهر مرة واحدة فقط.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إرسال العرض.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleRevise() {
    if (!quotation || busy) return
    setBusy(true)
    setError(null)
    try {
      const response = await reviseCommercialQuotation(quotation.id)
      applyQuotation(response.data)
      setNotice(`تم إنشاء مراجعة جديدة R${response.data.revision}`)
      navigateToId(response.data.id)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إنشاء مراجعة.'))
    } finally {
      setBusy(false)
    }
  }

  function navigateToId(nextId: number) {
    if (nextId !== quotationId) {
      window.location.assign(`/owner/commercial-quotations/${nextId}`)
    }
  }

  async function handleCopyLink() {
    if (!publicLink) return
    try {
      await navigator.clipboard.writeText(publicLink)
      setNotice('تم نسخ الرابط.')
    } catch {
      setNotice(`الرابط: ${publicLink}`)
    }
  }

  async function handlePdfDownload() {
    if (!quotation || pdfBusy) return
    setPdfBusy(true)
    setError(null)
    try {
      await downloadCommercialQuotationPdf(quotation.id, quotation.reference)
      setNotice('تم تنزيل ملف PDF.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنزيل PDF — قد لا يكون متاحاً بعد.'))
    } finally {
      setPdfBusy(false)
    }
  }

  if (loading) {
    return <DashboardPanelSkeleton label="جاري تحميل العرض..." />
  }

  if (error && !quotation) {
    return <DashboardErrorState message={error} onRetry={() => void load()} />
  }

  if (!quotation) {
    return (
      <DashboardEmptyState title="العرض غير موجود." description="تحقق من الرابط أو عد إلى طلب التسعير." />
    )
  }

  const isDraft = String(quotation.status) === 'DRAFT'
  const currency = quotation.currency || 'EGP'
  const orderId = quotation.order_id ?? quotation.linkages?.order_id ?? null
  const projectId = quotation.project_id ?? quotation.linkages?.project_id ?? null
  const payment = quotation.payment_summary
  const revisions = quotation.revisions ?? []

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link
            to={`/owner/quote-requests/${quotation.quote_request_id}`}
            className="text-sm text-[#315CFF] underline"
          >
            ← طلب التسعير
          </Link>
          <h1 className="mt-2 text-2xl font-semibold text-[#111318]">تحرير عرض السعر</h1>
          <p className="mt-1 font-mono text-sm text-slate-500" dir="ltr">
            {quotation.reference} · R{quotation.revision}
          </p>
          <p className="text-sm text-slate-600">{commercialQuotationStatusLabel(String(quotation.status))}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            disabled={pdfBusy}
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-50"
            onClick={() => void handlePdfDownload()}
          >
            {pdfBusy ? 'جاري التنزيل...' : 'تنزيل PDF'}
          </button>
          {isDraft ? (
            <>
              <button
                type="button"
                disabled={busy}
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-50"
                onClick={() => void handleSave()}
              >
                حفظ
              </button>
              <button
                type="button"
                disabled={busy}
                className="rounded-lg bg-[#315CFF] px-3 py-2 text-sm text-white disabled:opacity-50"
                onClick={() => setShowSendConfirm(true)}
              >
                إرسال عرض السعر
              </button>
            </>
          ) : (
            <button
              type="button"
              disabled={busy}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-50"
              onClick={() => void handleRevise()}
            >
              إنشاء مراجعة
            </button>
          )}
        </div>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        {(
          [
            ['customer', 'عرض العميل'],
            ['internal', 'العرض الداخلي'],
            ['sourcing', 'توريد الموردين'],
          ] as const
        ).map(([key, label]) => (
          <button
            key={key}
            type="button"
            className={`rounded-lg px-3 py-2 text-sm ${
              activeTab === key ? 'bg-[#315CFF] text-white' : 'border border-slate-300 bg-white text-slate-700'
            }`}
            onClick={() => setActiveTab(key)}
          >
            {label}
          </button>
        ))}
      </div>

      {activeTab === 'customer' ? (
        <DashboardSection title="معاينة العميل (بدون بيانات الموردين)">
          {preview ? (
            <div className="space-y-4 text-sm">
              <p className="text-slate-600">
                هذه المعاينة تطابق ما يراه العميل عبر الرابط العام أو PDF — دون هوية المورد أو التكلفة أو الهوامش.
              </p>
              <dl className="grid gap-2 sm:grid-cols-2">
                <div>
                  <dt className="text-xs text-slate-500">المرجع</dt>
                  <dd className="font-mono" dir="ltr">
                    {preview.reference}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-slate-500">الإجمالي</dt>
                  <dd className="font-semibold text-[#315CFF]">{formatMoney(Number(preview.total), currency)}</dd>
                </div>
              </dl>
              <ul className="space-y-2">
                {(preview.items || []).map((item, index) => (
                  <li key={`${item.description}-${index}`} className="rounded-lg border border-slate-100 bg-[#F7F5EF] px-3 py-2">
                    <div className="font-medium text-[#111318]">{item.description}</div>
                    <div className="mt-1 text-xs text-slate-600">
                      الكمية: {item.quantity} · سعر العميل: {formatMoney(Number(item.unit_price), currency)} ·{' '}
                      {formatMoney(Number(item.subtotal), currency)}
                    </div>
                  </li>
                ))}
              </ul>
              <SummaryRows
                currency={currency}
                subtotal={Number(preview.subtotal ?? 0)}
                discount={Number(preview.discount_amount ?? 0)}
                tax={Number(preview.tax_amount ?? 0)}
                shipping={Number(preview.shipping_amount ?? 0)}
                rental={Number(preview.rental_amount ?? 0)}
                total={Number(preview.total ?? 0)}
                estimated={false}
              />
            </div>
          ) : (
            <p className="text-sm text-slate-500">احفظ العرض أولاً لتحميل معاينة العميل.</p>
          )}
        </DashboardSection>
      ) : null}

      {activeTab === 'sourcing' ? (
        <DashboardSection title="توريد الموردين">
          <QuotationSourcingPanel quotationId={quotation.id} currency={currency} />
          <div className="mt-4">
            <MediaUploader entityType="quotation" entityId={quotation.id} title="ملفات العرض" />
          </div>
        </DashboardSection>
      ) : null}

      {activeTab === 'internal' ? (
        <>
      {publicLink ? (
        <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm">
          <span className="break-all font-mono text-xs" dir="ltr">
            {publicLink}
          </span>
          <button type="button" className="rounded-lg bg-[#111318] px-3 py-1.5 text-xs text-white" onClick={() => void handleCopyLink()}>
            نسخ الرابط
          </button>
          <a href={publicLink} target="_blank" rel="noreferrer" className="underline">
            فتح
          </a>
        </div>
      ) : null}

      <div className="lg:hidden">
        <div className="rounded-2xl border border-slate-200 bg-[#F7F5EF] p-4">
          <h2 className="mb-3 text-sm font-semibold text-[#111318]">ملخص المبالغ</h2>
          <SummaryRows
            currency={currency}
            subtotal={summarySubtotal}
            discount={summaryDiscount}
            tax={summaryTax}
            shipping={summaryShipping}
            rental={summaryRental}
            total={summaryTotal}
            estimated={!useServerTotals}
          />
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
        <form onSubmit={(event) => void handleSave(event)} className="space-y-4">
          <DashboardSection title="بيانات الطلب">
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-slate-500">طلب التسعير</dt>
                <dd>
                  <Link
                    to={`/owner/quote-requests/${quotation.quote_request_id}`}
                    className="font-mono text-[#315CFF] underline"
                    dir="ltr"
                  >
                    {quotation.quote_request?.reference || `#${quotation.quote_request_id}`}
                  </Link>
                </dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">العميل</dt>
                <dd className="font-medium text-[#111318]">{quotation.customer?.name || '—'}</dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">رقم المراجعة</dt>
                <dd className="font-medium">R{quotation.revision}</dd>
              </div>
              {orderId ? (
                <div>
                  <dt className="text-xs text-slate-500">الطلب المرتبط</dt>
                  <dd>
                    <Link to={`/owner/orders/${orderId}`} className="text-[#315CFF] underline">
                      طلب #{orderId}
                    </Link>
                  </dd>
                </div>
              ) : null}
              {projectId ? (
                <div>
                  <dt className="text-xs text-slate-500">المشروع المرتبط</dt>
                  <dd>
                    <Link to={`/owner/projects/${projectId}`} className="text-[#315CFF] underline">
                      مشروع #{projectId}
                    </Link>
                  </dd>
                </div>
              ) : null}
            </dl>
          </DashboardSection>

          <DashboardSection title="بنود عرض السعر">
            <p className="mb-3 text-xs text-slate-500">
              إجمالي الصف = الكمية × سعر الوحدة (معاينة فقط). الإجمالي النهائي يُحسب من الخادم بعد الحفظ.
            </p>
            <div className="space-y-3">
              {lines.map((line, index) => {
                const rowSubtotal = (Number(line.quantity) || 0) * (Number(line.unit_price) || 0)
                return (
                  <div key={line.key} className="grid gap-2 rounded-lg border border-slate-100 bg-white p-3 sm:grid-cols-2">
                    <label className="space-y-1 text-sm sm:col-span-2">
                      <span>الوصف</span>
                      <input
                        className={fieldClass}
                        value={line.description}
                        disabled={!isDraft}
                        onChange={(e) => {
                          markDirty()
                          setLines((prev) =>
                            prev.map((row, i) => (i === index ? { ...row, description: e.target.value } : row)),
                          )
                        }}
                        required
                      />
                    </label>
                    <label className="space-y-1 text-sm">
                      <span>الكمية</span>
                      <input
                        className={fieldClass}
                        type="number"
                        min="0.01"
                        step="0.01"
                        value={line.quantity}
                        disabled={!isDraft}
                        onChange={(e) => {
                          markDirty()
                          setLines((prev) =>
                            prev.map((row, i) => (i === index ? { ...row, quantity: e.target.value } : row)),
                          )
                        }}
                      />
                    </label>
                    <label className="space-y-1 text-sm">
                      <span>سعر الوحدة</span>
                      <input
                        className={fieldClass}
                        type="number"
                        min="0"
                        step="0.01"
                        value={line.unit_price}
                        disabled={!isDraft}
                        onChange={(e) => {
                          markDirty()
                          setLines((prev) =>
                            prev.map((row, i) => (i === index ? { ...row, unit_price: e.target.value } : row)),
                          )
                        }}
                      />
                    </label>
                    <label className="space-y-1 text-sm sm:col-span-2">
                      <span>الفئة</span>
                      <select
                        className={fieldClass}
                        value={line.category}
                        disabled={!isDraft}
                        onChange={(e) => {
                          markDirty()
                          setLines((prev) =>
                            prev.map((row, i) => (i === index ? { ...row, category: e.target.value } : row)),
                          )
                        }}
                      >
                        {QUOTE_LINE_CATEGORIES.map((cat) => (
                          <option key={cat} value={cat}>
                            {QUOTE_LINE_CATEGORY_LABELS[cat]}
                          </option>
                        ))}
                      </select>
                    </label>
                    <p className="text-xs text-slate-500 sm:col-span-2">
                      إجمالي الصف (معاينة): {formatMoney(rowSubtotal, currency)}
                    </p>
                    {isDraft && lines.length > 1 ? (
                      <button
                        type="button"
                        className="text-sm text-red-700 underline sm:col-span-2"
                        onClick={() => {
                          markDirty()
                          setLines((prev) => prev.filter((_, i) => i !== index))
                        }}
                      >
                        حذف البند
                      </button>
                    ) : null}
                  </div>
                )
              })}
              {isDraft ? (
                <button
                  type="button"
                  className="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm"
                  onClick={() => {
                    markDirty()
                    setLines((prev) => [...prev, newLine()])
                  }}
                >
                  + إضافة بند
                </button>
              ) : null}
            </div>
          </DashboardSection>

          <DashboardSection title="الخصومات والرسوم">
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="space-y-1 text-sm">
                <span>الخصم</span>
                <input
                  className={fieldClass}
                  value={discount}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setDiscount(e.target.value)
                  }}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span>الضريبة</span>
                <input
                  className={fieldClass}
                  value={tax}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setTax(e.target.value)
                  }}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span>الشحن</span>
                <input
                  className={fieldClass}
                  value={shipping}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setShipping(e.target.value)
                  }}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span>التأجير</span>
                <input
                  className={fieldClass}
                  value={rental}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setRental(e.target.value)
                  }}
                />
              </label>
            </div>
          </DashboardSection>

          <DashboardSection title="شروط العرض">
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="space-y-1 text-sm">
                <span>صالح حتى</span>
                <input
                  type="date"
                  className={fieldClass}
                  value={validUntil}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setValidUntil(e.target.value)
                  }}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span>مدة التنفيذ</span>
                <input
                  className={fieldClass}
                  value={duration}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setDuration(e.target.value)
                  }}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span>جولات التعديل</span>
                <input
                  type="number"
                  min="0"
                  className={fieldClass}
                  value={revisionCount}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setRevisionCount(e.target.value)
                  }}
                />
              </label>
            </div>
            <div className="mt-3 space-y-3">
              <label className="block space-y-1 text-sm">
                <span>الشروط</span>
                <textarea
                  className={fieldClass}
                  rows={3}
                  value={terms}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setTerms(e.target.value)
                  }}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>ملاحظات للعميل</span>
                <textarea
                  className={fieldClass}
                  rows={2}
                  value={notes}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setNotes(e.target.value)
                  }}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>ملاحظات داخلية</span>
                <textarea
                  className={fieldClass}
                  rows={2}
                  value={internalNotes}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setInternalNotes(e.target.value)
                  }}
                />
              </label>
            </div>
          </DashboardSection>

          <DashboardSection title="الدفع">
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="space-y-1 text-sm">
                <span>سياسة الدفع</span>
                <select
                  className={fieldClass}
                  value={paymentPolicy}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setPaymentPolicy(e.target.value)
                  }}
                >
                  <option value="NONE">بدون دفع مسبق</option>
                  <option value="DEPOSIT">عربون</option>
                  <option value="FULL">دفع كامل</option>
                </select>
              </label>
              <label className="space-y-1 text-sm">
                <span>العربون</span>
                <input
                  className={fieldClass}
                  value={deposit}
                  disabled={!isDraft}
                  onChange={(e) => {
                    markDirty()
                    setDeposit(e.target.value)
                  }}
                />
              </label>
            </div>
          </DashboardSection>

          {!isDraft && payment ? (
            <DashboardSection title="ملخص الدفع">
              <div className="grid gap-3 text-sm sm:grid-cols-2">
                <p>المطلوب: {formatMoney(payment.required ?? 0, currency)}</p>
                <p>المدفوع: {formatMoney(payment.paid ?? 0, currency)}</p>
                <p>المتبقي: {formatMoney(payment.remaining ?? payment.outstanding ?? 0, currency)}</p>
                <p>المستحق الآن: {formatMoney(payment.amount_due_now ?? 0, currency)}</p>
              </div>
            </DashboardSection>
          ) : null}

          {revisions.length > 0 ? (
            <DashboardSection title="سجل المراجعات">
              <ul className="space-y-2 text-sm">
                {revisions.map((rev) => (
                  <li key={rev.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2">
                    <span className="font-mono text-xs" dir="ltr">
                      {rev.reference || `#${rev.id}`} · R{rev.revision ?? '—'}
                    </span>
                    <span>{commercialQuotationStatusLabel(String(rev.status || ''))}</span>
                    {rev.is_current ? (
                      <span className="rounded-full bg-[#315CFF]/10 px-2 py-0.5 text-xs text-[#315CFF]">الحالية</span>
                    ) : rev.superseded ? (
                      <span className="text-xs text-slate-500">مستبدلة</span>
                    ) : null}
                    <Link to={`/owner/commercial-quotations/${rev.id}`} className="text-[#315CFF] underline">
                      فتح
                    </Link>
                  </li>
                ))}
              </ul>
            </DashboardSection>
          ) : quotation.supersedes ? (
            <DashboardSection title="سجل المراجعات">
              <p className="text-sm text-slate-600">
                يستبدل{' '}
                <Link to={`/owner/commercial-quotations/${quotation.supersedes.id}`} className="text-[#315CFF] underline" dir="ltr">
                  {quotation.supersedes.reference} · R{quotation.supersedes.revision}
                </Link>
              </p>
            </DashboardSection>
          ) : null}

          {quotation.revision_reason ? (
            <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
              سبب طلب التعديل: {quotation.revision_reason}
            </div>
          ) : null}

          <DashboardSection title="المعاينة">
            {preview ? (
              <div className="space-y-3 text-sm">
                <p className="font-mono text-xs text-slate-500" dir="ltr">
                  {preview.reference} · R{preview.revision}
                </p>
                {preview.customer?.name ? <p>العميل: {preview.customer.name}</p> : null}
                <ul className="divide-y rounded-lg border">
                  {(preview.items ?? []).map((item, index) => (
                    <li key={index} className="flex justify-between gap-2 px-3 py-2">
                      <span>{item.description}</span>
                      <span>{formatMoney(item.subtotal ?? 0, preview.currency || currency)}</span>
                    </li>
                  ))}
                </ul>
                <div className="space-y-1 rounded-lg bg-[#F7F5EF] px-3 py-2">
                  <p>الإجمالي: {formatMoney(preview.total, preview.currency || currency)}</p>
                  <p className="text-xs text-slate-500">صالح حتى: {formatQuoteDate(preview.valid_until)}</p>
                </div>
                {publicLink ? (
                  <p className="text-xs text-slate-500" dir="ltr">
                    رابط عام: {publicLink}
                  </p>
                ) : quotation.public_token_hint ? (
                  <p className="text-xs text-slate-500">
                    تلميح الرمز: {quotation.public_token_hint} — الرابط الكامل يظهر مرة واحدة عند الإرسال فقط.
                  </p>
                ) : (
                  <p className="text-xs text-slate-500">
                    بعد الإرسال يظهر رابط عام بصيغة{' '}
                    <span className="font-mono" dir="ltr">
                      {absoluteCommercialQuotePath('{token}')}
                    </span>
                  </p>
                )}
              </div>
            ) : (
              <p className="text-sm text-slate-600">احفظ المسودة لعرض المعاينة.</p>
            )}
          </DashboardSection>
        </form>

        <aside className="hidden lg:block">
          <div className="sticky top-6 space-y-4">
            <div className="rounded-2xl border border-slate-200 bg-[#F7F5EF] p-4 shadow-sm">
              <h2 className="mb-3 text-sm font-semibold text-[#111318]">ملخص المبالغ</h2>
              <SummaryRows
                currency={currency}
                subtotal={summarySubtotal}
                discount={summaryDiscount}
                tax={summaryTax}
                shipping={summaryShipping}
                rental={summaryRental}
                total={summaryTotal}
                estimated={!useServerTotals}
              />
            </div>
            <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
              <p className="text-xs text-slate-500">سياسة الدفع</p>
              <p className="font-medium">{printingPaymentPolicyLabel(paymentPolicy)}</p>
              <p className="mt-2 text-xs text-slate-500">صالح حتى</p>
              <p className="font-medium">{formatQuoteDate(validUntil || quotation.valid_until)}</p>
            </div>
          </div>
        </aside>
      </div>
        </>
      ) : null}

      {showSendConfirm ? (
        <div
          className="fixed inset-0 z-50 flex items-end justify-center bg-[#111318]/50 p-3 sm:items-center"
          role="dialog"
          aria-modal="true"
          aria-labelledby="send-quote-title"
        >
          <div className="w-full max-w-md space-y-4 rounded-2xl bg-white p-5 shadow-xl">
            <h2 id="send-quote-title" className="text-lg font-semibold text-[#111318]">
              إرسال عرض السعر للعميل؟
            </h2>
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">العميل</dt>
                <dd className="font-medium">{quotation.customer?.name || '—'}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">الإجمالي</dt>
                <dd className="font-medium">
                  {formatMoney(summaryTotal, currency)} {currency}
                </dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">صالح حتى</dt>
                <dd>{formatQuoteDate(validUntil || quotation.valid_until)}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">سياسة الدفع</dt>
                <dd>{printingPaymentPolicyLabel(paymentPolicy)}</dd>
              </div>
            </dl>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                disabled={busy}
                className="min-h-11 flex-1 rounded-xl bg-[#315CFF] px-4 text-sm font-medium text-white disabled:opacity-60"
                onClick={() => void handleSendConfirmed()}
              >
                إرسال
              </button>
              <button
                type="button"
                disabled={busy}
                className="min-h-11 flex-1 rounded-xl border border-slate-300 px-4 text-sm disabled:opacity-60"
                onClick={() => setShowSendConfirm(false)}
              >
                رجوع للتعديل
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
