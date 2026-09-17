import { FormEvent, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  listSupplierSourcingRequests,
  respondSupplierSourcingRequest,
  type SupplierSourcingRequest,
} from '../../services/quotationSourcing'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315CFF]'

export function SupplierSourcingPage() {
  const [items, setItems] = useState<SupplierSourcingRequest[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [activeId, setActiveId] = useState<number | null>(null)
  const [cost, setCost] = useState('')
  const [deliveryDays, setDeliveryDays] = useState('')
  const [notes, setNotes] = useState('')
  const [files, setFiles] = useState<FileList | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await listSupplierSourcingRequests()
      setItems(response.data.items)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل طلبات التوريد.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function handleRespond(event: FormEvent, id: number) {
    event.preventDefault()
    if (busyId) return
    setBusyId(id)
    setError(null)
    setNotice(null)
    try {
      await respondSupplierSourcingRequest(id, {
        cost,
        delivery_days: deliveryDays || undefined,
        notes: notes || undefined,
        attachments: files,
      })
      setNotice('تم إرسال عرضك.')
      setActiveId(null)
      setCost('')
      setDeliveryDays('')
      setNotes('')
      setFiles(null)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إرسال العرض.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold text-[#111318]">طلبات التوريد</h1>
        <p className="mt-1 text-sm text-slate-600">
          عروض الأسعار المطلوبة منك للتنفيذ الداخلي عبر حبر وأبعاد. هوية العميل وسعره النهائي غير ظاهرة هنا.
        </p>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      {loading ? (
        <p className="text-sm text-slate-500">جاري التحميل...</p>
      ) : items.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
          لا توجد طلبات توريد حالياً.
        </p>
      ) : (
        <div className="space-y-3">
          {items.map((item) => (
            <article key={item.id} className="rounded-2xl border border-slate-200 bg-white p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-mono text-xs text-slate-500" dir="ltr">
                    {item.quotation?.reference || `#${item.id}`}
                  </p>
                  <h2 className="mt-1 text-lg font-semibold text-[#111318]">
                    {item.item?.description || 'بند توريد'}
                  </h2>
                  <p className="text-sm text-slate-600">
                    الحالة: {item.status_label_ar || item.status}
                    {item.cost != null
                      ? ` · تكلفتك: ${formatMoney(Number(item.cost), item.currency || 'EGP')}`
                      : ''}
                  </p>
                </div>
                {['REQUESTED', 'RECEIVED', 'UNDER_REVIEW'].includes(String(item.status)) ? (
                  <button
                    type="button"
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    onClick={() => {
                      setActiveId(item.id)
                      setCost(item.cost != null ? String(item.cost) : '')
                      setDeliveryDays(item.delivery_days != null ? String(item.delivery_days) : '')
                      setNotes(item.notes || '')
                    }}
                  >
                    تقديم / تحديث عرض
                  </button>
                ) : null}
              </div>

              {activeId === item.id ? (
                <form className="mt-4 grid gap-3 sm:grid-cols-2" onSubmit={(event) => void handleRespond(event, item.id)}>
                  <label className="space-y-1 text-sm">
                    <span>التكلفة</span>
                    <input className={fieldClass} type="number" min="0" step="0.01" required value={cost} onChange={(e) => setCost(e.target.value)} />
                  </label>
                  <label className="space-y-1 text-sm">
                    <span>أيام التسليم</span>
                    <input className={fieldClass} type="number" min="0" value={deliveryDays} onChange={(e) => setDeliveryDays(e.target.value)} />
                  </label>
                  <label className="space-y-1 text-sm sm:col-span-2">
                    <span>ملاحظات</span>
                    <textarea className={fieldClass} rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} />
                  </label>
                  <label className="space-y-1 text-sm sm:col-span-2">
                    <span>مرفقات</span>
                    <input className={fieldClass} type="file" multiple onChange={(e) => setFiles(e.target.files)} />
                  </label>
                  <div className="flex gap-2 sm:col-span-2">
                    <button
                      type="submit"
                      disabled={busyId === item.id}
                      className="rounded-lg bg-[#315CFF] px-4 py-2 text-sm text-white disabled:opacity-50"
                    >
                      {busyId === item.id ? 'جاري الإرسال...' : 'إرسال العرض'}
                    </button>
                    <button type="button" className="rounded-lg border border-slate-300 px-4 py-2 text-sm" onClick={() => setActiveId(null)}>
                      إلغاء
                    </button>
                  </div>
                </form>
              ) : null}
            </article>
          ))}
        </div>
      )}

      <p className="text-xs text-slate-500">
        <Link to="/supplier/dashboard" className="text-[#315CFF] underline">
          العودة للوحة المورد
        </Link>
      </p>
    </section>
  )
}
