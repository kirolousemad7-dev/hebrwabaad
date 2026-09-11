import { FormEvent, useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAuth } from '../../context/AuthContext'
import { createCustomerQuoteRequest, type QuoteRequest } from '../../services/quoteRequests'
import { uploadManagedFile } from '../../services/files'
import { describeApiError } from '../../utils/errors'
import { quoteRequestSourceLabel } from '../../utils/quoteRequests'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315CFF]'

const SUBTITLE =
  'أرسل تفاصيل احتياجك وسيقوم فريق حبر وأبعاد بمراجعة الطلب وإرسال عرض السعر لك عبر المنصة.'

function parsePayload(raw: string | null): Record<string, unknown> | null {
  if (!raw) return null
  try {
    const parsed = JSON.parse(raw) as unknown
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
      ? (parsed as Record<string, unknown>)
      : { value: parsed }
  } catch {
    return null
  }
}

export function RequestQuotePage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const sourceType = searchParams.get('source_type') || 'SERVICE'
  const sourceIdRaw = searchParams.get('source_id')
  const sourceId = sourceIdRaw ? Number(sourceIdRaw) : null
  const prefillTitle = searchParams.get('title') || ''
  const prefillCity = searchParams.get('city') || ''
  const prefillNotes = searchParams.get('notes') || ''
  const prefillPayload = useMemo(() => parsePayload(searchParams.get('payload')), [searchParams])

  const [title, setTitle] = useState(prefillTitle || 'طلب تسعير')
  const [requiredDate, setRequiredDate] = useState('')
  const [city, setCity] = useState(prefillCity)
  const [budgetMin, setBudgetMin] = useState('')
  const [budgetMax, setBudgetMax] = useState('')
  const [customerNotes, setCustomerNotes] = useState(prefillNotes)
  const [file, setFile] = useState<File | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [created, setCreated] = useState<QuoteRequest | null>(null)

  if (user && user.role !== 'CUSTOMER') {
    return (
      <div dir="rtl" className="min-h-screen bg-[#F7F5EF] px-4 py-10">
        <div className="mx-auto max-w-md space-y-3 rounded-2xl border bg-white p-6 text-center text-sm">
          <p>طلب التسعير متاح لحسابات العملاء فقط.</p>
          <Link to="/" className="text-[#315CFF] underline">
            العودة للرئيسية
          </Link>
        </div>
      </div>
    )
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (busy) return
    if (!title.trim()) {
      setError('عنوان الطلب مطلوب.')
      return
    }

    setBusy(true)
    setError(null)
    try {
      let fileIds: number[] | undefined
      if (file) {
        const body = new FormData()
        body.append('file', file)
        const uploaded = await uploadManagedFile('customer', body)
        fileIds = [uploaded.data.id]
      }

      const response = await createCustomerQuoteRequest({
        source_type: sourceType,
        source_id: sourceId && Number.isFinite(sourceId) ? sourceId : null,
        title: title.trim(),
        required_date: requiredDate || null,
        budget_min: budgetMin || null,
        budget_max: budgetMax || null,
        city: city.trim() || null,
        customer_notes: customerNotes.trim() || null,
        payload: prefillPayload,
        file_ids: fileIds,
        idempotency_key:
          sourceId && Number.isFinite(sourceId)
            ? `${sourceType}-${sourceId}-${user?.id ?? 'x'}`
            : undefined,
      })
      setCreated(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إرسال طلب التسعير.'))
    } finally {
      setBusy(false)
    }
  }

  if (created) {
    return (
      <div dir="rtl" className="min-h-screen bg-[#F7F5EF] px-4 py-10">
        <div className="mx-auto max-w-lg space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <p className="text-sm text-[#315CFF]">حبر وأبعاد</p>
          <h1 className="text-2xl font-semibold text-[#111318]">تم استلام طلب التسعير</h1>
          <p className="text-sm text-slate-600">
            رقم المرجع:{' '}
            <span className="font-mono font-medium text-[#111318]" dir="ltr">
              {created.reference}
            </span>
          </p>
          <p className="text-sm text-slate-600">سنراجع طلبك ونرسل لك عرض السعر عبر المنصة والإشعارات.</p>
          <div className="flex flex-wrap gap-2">
            <Link
              to={`/dashboard/quote-requests/${created.id}`}
              className="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#315CFF] px-4 text-sm font-medium text-white"
            >
              عرض الطلب
            </Link>
            <Link
              to="/dashboard/quote-requests"
              className="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm text-[#111318]"
            >
              طلباتي
            </Link>
            <button
              type="button"
              className="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm"
              onClick={() => navigate('/dashboard')}
            >
              لوحة التحكم
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div dir="rtl" className="min-h-screen bg-[#F7F5EF] px-4 py-10">
      <div className="mx-auto w-full max-w-2xl space-y-6">
        <header className="space-y-2">
          <p className="text-sm font-medium text-[#315CFF]">حبر وأبعاد</p>
          <h1 className="text-3xl font-semibold text-[#111318]">اطلب عرض سعر</h1>
          <p className="text-sm leading-7 text-slate-600">{SUBTITLE}</p>
        </header>

        <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
          <p>
            المصدر: <span className="font-medium text-[#111318]">{quoteRequestSourceLabel(sourceType)}</span>
            {prefillTitle ? ` · ${prefillTitle}` : ''}
          </p>
          {prefillPayload ? (
            <p className="mt-1 text-xs text-slate-500">تم تعبئة تفاصيل المصدر تلقائياً من الكتالوج.</p>
          ) : null}
        </div>

        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

        <form
          onSubmit={(event) => void handleSubmit(event)}
          className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <label className="block space-y-1 text-sm">
            <span className="font-medium">عنوان الطلب</span>
            <input className={fieldClass} value={title} onChange={(e) => setTitle(e.target.value)} required />
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block space-y-1 text-sm">
              <span className="font-medium">الموعد المطلوب</span>
              <input
                type="date"
                className={fieldClass}
                value={requiredDate}
                onChange={(e) => setRequiredDate(e.target.value)}
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span className="font-medium">المدينة</span>
              <input className={fieldClass} value={city} onChange={(e) => setCity(e.target.value)} />
            </label>
            <label className="block space-y-1 text-sm">
              <span className="font-medium">الميزانية من</span>
              <input
                type="number"
                min="0"
                step="0.01"
                className={fieldClass}
                value={budgetMin}
                onChange={(e) => setBudgetMin(e.target.value)}
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span className="font-medium">الميزانية إلى</span>
              <input
                type="number"
                min="0"
                step="0.01"
                className={fieldClass}
                value={budgetMax}
                onChange={(e) => setBudgetMax(e.target.value)}
              />
            </label>
          </div>

          <label className="block space-y-1 text-sm">
            <span className="font-medium">ملاحظات</span>
            <textarea
              className={fieldClass}
              rows={4}
              value={customerNotes}
              onChange={(e) => setCustomerNotes(e.target.value)}
              placeholder="صف احتياجك أو أي تفاصيل إضافية..."
            />
          </label>

          <label className="block space-y-1 text-sm">
            <span className="font-medium">ملف مرفق (اختياري)</span>
            <input
              type="file"
              className="block w-full text-sm"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            />
            <span className="text-xs text-slate-500">يُرفع عبر مكتبة ملفات العميل ويُربط بالطلب.</span>
          </label>

          <button
            type="submit"
            disabled={busy}
            className="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-[#315CFF] px-4 text-sm font-medium text-white disabled:opacity-60 sm:w-auto"
          >
            {busy ? 'جاري الإرسال...' : 'إرسال طلب التسعير'}
          </button>
        </form>
      </div>
    </div>
  )
}
