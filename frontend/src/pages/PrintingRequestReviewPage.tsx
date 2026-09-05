import { FormEvent, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PrintingPricingStatus } from '../components/printing/PrintingPricingStatus'
import { PrintingRequestSpecs } from '../components/printing/PrintingRequestSpecs'
import { useAsyncData } from '../hooks/useAsyncData'
import {
  downloadAdminPrintingRequestFile,
  getAdminPrintingRequest,
  markPrintingRequestQuoteRequired,
  providePrintingRequestQuote,
  setPrintingRequestEstimate,
} from '../services/printingRequests'
import type { PrintingRequest } from '../types/api'
import { describeApiError } from '../utils/errors'

const fieldClass =
  'w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type PrintingRequestReviewBodyProps = {
  id: number
}

function PrintingRequestReviewBody({ id }: PrintingRequestReviewBodyProps) {
  const { state, reload } = useAsyncData(() => getAdminPrintingRequest(id))
  const [current, setCurrent] = useState<PrintingRequest | null>(null)
  const [estimatedPrice, setEstimatedPrice] = useState('')
  const [quotedPrice, setQuotedPrice] = useState('')
  const [notes, setNotes] = useState('')
  const [saving, setSaving] = useState<'estimate' | 'quote-required' | 'quote' | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [downloading, setDownloading] = useState(false)

  useEffect(() => {
    if (state.status !== 'ready' || current !== null) {
      return
    }

    setCurrent(state.data)
    setEstimatedPrice(state.data.estimated_price ?? '')
    setQuotedPrice(state.data.quoted_price ?? '')
    setNotes(state.data.pricing_notes ?? '')
  }, [state, current])

  function applyRequest(request: PrintingRequest) {
    setCurrent(request)
    setEstimatedPrice(request.estimated_price ?? '')
    setQuotedPrice(request.quoted_price ?? '')
    setNotes(request.pricing_notes ?? '')
  }

  async function run(action: 'estimate' | 'quote-required' | 'quote', task: () => Promise<{ data: PrintingRequest }>) {
    setError(null)
    setNotice(null)
    setSaving(action)

    try {
      const response = await task()
      applyRequest(response.data)
      setNotice('تم حفظ التسعير.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ التسعير.'))
    } finally {
      setSaving(null)
    }
  }

  async function handleEstimate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    await run('estimate', () => setPrintingRequestEstimate(id, estimatedPrice, notes))
  }

  async function handleQuoteRequired() {
    await run('quote-required', () => markPrintingRequestQuoteRequired(id, notes))
  }

  async function handleQuote(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    await run('quote', () => providePrintingRequestQuote(id, quotedPrice, notes))
  }

  async function handleDownload(filename: string) {
    setError(null)
    setDownloading(true)

    try {
      await downloadAdminPrintingRequestFile(id, filename)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنزيل الملف.'))
    } finally {
      setDownloading(false)
    }
  }

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل الطلب..." />
  }

  if (state.status === 'error') {
    if (state.message.toLowerCase().includes('not found')) {
      return (
        <CatalogEmptyState
          title="لم نجد هذا الطلب."
          description="قد يكون الرقم غير صحيح."
          actions={[{ to: '/printing-requests', label: 'كل الطلبات', variant: 'primary' }]}
        />
      )
    }

    return <CatalogErrorState message={`تعذر تحميل الطلب. ${state.message}`} onRetry={() => void reload()} />
  }

  if (current === null) {
    return <CatalogSkeleton variant="list" label="جاري تحميل الطلب..." />
  }

  const request = current

  return (
    <article className="space-y-6">
      <header className="space-y-2">
        <p className="text-sm text-slate-500">طلب #{request.id}</p>
        <h1 className="text-2xl font-semibold">{request.product_name}</h1>
      </header>

      {error ? (
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      {notice ? (
        <p className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</p>
      ) : null}

      <section className="space-y-2 rounded-xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold">العميل</h2>
        <p className="text-sm">{request.customer?.name}</p>
        <p className="text-sm text-slate-600">{request.customer?.email}</p>
      </section>

      <PrintingPricingStatus request={request} />

      <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold">المواصفات</h2>
        <PrintingRequestSpecs request={request} />
        <button
          type="button"
          onClick={() => void handleDownload(request.filename)}
          disabled={downloading}
          className="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:opacity-60"
        >
          {downloading ? 'جاري التنزيل...' : 'تنزيل ملف التصميم'}
        </button>
      </section>

      <section className="grid gap-4 lg:grid-cols-3">
        <form onSubmit={handleEstimate} className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold">تعيين سعر تقديري</h2>
          <label className="block space-y-1 text-sm">
            <span>السعر التقديري (ر.س)</span>
            <input
              type="number"
              min="0"
              step="0.01"
              required
              value={estimatedPrice}
              onChange={(event) => setEstimatedPrice(event.target.value)}
              className={fieldClass}
            />
          </label>
          <button
            type="submit"
            disabled={saving !== null}
            className="rounded-md bg-slate-900 px-4 py-2.5 text-sm text-white disabled:opacity-60"
          >
            {saving === 'estimate' ? 'جاري الحفظ...' : 'حفظ التقدير'}
          </button>
        </form>

        <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold">طلب عرض سعر</h2>
          <p className="text-sm text-slate-600">استخدم هذا عندما لا يمكن تقدير الطلب بأمان.</p>
          <button
            type="button"
            onClick={() => void handleQuoteRequired()}
            disabled={saving !== null}
            className="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm disabled:opacity-60"
          >
            {saving === 'quote-required' ? 'جاري الحفظ...' : 'يحتاج عرض سعر'}
          </button>
        </div>

        <form onSubmit={handleQuote} className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold">إصدار عرض سعر</h2>
          <label className="block space-y-1 text-sm">
            <span>عرض السعر (ر.س)</span>
            <input
              type="number"
              min="0"
              step="0.01"
              required
              value={quotedPrice}
              onChange={(event) => setQuotedPrice(event.target.value)}
              className={fieldClass}
            />
          </label>
          <button
            type="submit"
            disabled={saving !== null}
            className="rounded-md bg-slate-900 px-4 py-2.5 text-sm text-white disabled:opacity-60"
          >
            {saving === 'quote' ? 'جاري الحفظ...' : 'حفظ عرض السعر'}
          </button>
        </form>
      </section>

      <label className="block space-y-1 rounded-xl border border-slate-200 bg-white p-5 text-sm">
        <span>ملاحظات التسعير</span>
        <textarea rows={4} value={notes} onChange={(event) => setNotes(event.target.value)} className={fieldClass} />
      </label>

      <p className="text-sm">
        <Link
          to="/printing-requests"
          className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          العودة إلى الطلبات
        </Link>
      </p>
    </article>
  )
}

export function PrintingRequestReviewPage() {
  const { id = '' } = useParams()
  const numericId = Number.parseInt(id, 10)

  if (!Number.isInteger(numericId) || numericId < 1) {
    return (
      <CatalogEmptyState
        title="لم نجد هذا الطلب."
        description="رقم الطلب غير صالح."
        actions={[{ to: '/printing-requests', label: 'كل الطلبات', variant: 'primary' }]}
      />
    )
  }

  return <PrintingRequestReviewBody key={numericId} id={numericId} />
}
