import { FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { useAuth } from '../context/AuthContext'
import { ApiRequestError } from '../services/api'
import { submitEventRequest } from '../services/events'

const EVENT_TYPES = [
  { value: 'opening', label: 'افتتاح' },
  { value: 'conference', label: 'مؤتمر' },
  { value: 'exhibition', label: 'معرض' },
  { value: 'occasion', label: 'مناسبة' },
  { value: 'product_launch', label: 'إطلاق منتج' },
  { value: 'internal', label: 'فعالية داخلية' },
  { value: 'other', label: 'أخرى' },
] as const

export function EventsPage() {
  const { isAuthenticated, user } = useAuth()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [form, setForm] = useState({
    event_type: 'opening',
    event_date: '',
    city: '',
    attendance: '',
    venue: '',
    budget_range: '',
    buy_or_rent: 'unsure',
    notes: '',
  })

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    if (!isAuthenticated || user?.role !== 'CUSTOMER') {
      setError('سجّل الدخول كعميل لإرسال طلب فعالية.')
      return
    }

    setBusy(true)
    setError(null)
    setSuccess(null)

    try {
      await submitEventRequest({
        ...form,
        attendance: form.attendance ? Number(form.attendance) : null,
        event_date: form.event_date || null,
      })
      setSuccess('تم استلام طلب الفعالية. سيظهر كمشروع/عرض سعر مخصص بعد مراجعة الفريق.')
      setForm((current) => ({ ...current, notes: '' }))
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إرسال الطلب.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="space-y-8">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'الفعاليات' },
        ]}
      />
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold">الفعاليات كمشروع متكامل</h1>
        <p className="max-w-3xl text-slate-600">
          الفعاليات ليست شراء خدمة واحدة — نبني Concept وهوية وتصميم وطباعة وتجهيز وتصوير ضمن مشروع داخل المنصة، مع عرض
          سعر مخصص حتى تكتمل بيانات التأجير/الإنتاج.
        </p>
        <div className="flex flex-wrap gap-3">
          <PublicCta to="/event-packages">باقة الفعالية</PublicCta>
          <PublicCta to="/consultant?goal=event" variant="secondary">
            اكتشف احتياجك
          </PublicCta>
        </div>
      </header>

      <ol className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {[
          'التصميم → الاعتماد',
          'الطباعة → التجهيز',
          'التركيب → يوم الفعالية',
          'التصوير → التسليم',
        ].map((step) => (
          <li key={step} className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium">
            {step}
          </li>
        ))}
      </ol>

      <form onSubmit={onSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold">طلب فعالية</h2>
        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
        {success ? <FeedbackBanner kind="success">{success}</FeedbackBanner> : null}

        <label className="block space-y-1 text-sm">
          <span>نوع الفعالية</span>
          <select
            className="w-full rounded-lg border border-slate-300 px-3 py-2"
            value={form.event_type}
            onChange={(e) => setForm((c) => ({ ...c, event_type: e.target.value }))}
          >
            {EVENT_TYPES.map((type) => (
              <option key={type.value} value={type.value}>
                {type.label}
              </option>
            ))}
          </select>
        </label>

        <div className="grid gap-3 sm:grid-cols-2">
          <label className="block space-y-1 text-sm">
            <span>التاريخ</span>
            <input
              type="date"
              className="w-full rounded-lg border border-slate-300 px-3 py-2"
              value={form.event_date}
              onChange={(e) => setForm((c) => ({ ...c, event_date: e.target.value }))}
            />
          </label>
          <label className="block space-y-1 text-sm">
            <span>المدينة</span>
            <input
              className="w-full rounded-lg border border-slate-300 px-3 py-2"
              value={form.city}
              onChange={(e) => setForm((c) => ({ ...c, city: e.target.value }))}
              required
            />
          </label>
          <label className="block space-y-1 text-sm">
            <span>عدد الحضور التقريبي</span>
            <input
              type="number"
              min={1}
              className="w-full rounded-lg border border-slate-300 px-3 py-2"
              value={form.attendance}
              onChange={(e) => setForm((c) => ({ ...c, attendance: e.target.value }))}
            />
          </label>
          <label className="block space-y-1 text-sm">
            <span>المكان / القاعة</span>
            <input
              className="w-full rounded-lg border border-slate-300 px-3 py-2"
              value={form.venue}
              onChange={(e) => setForm((c) => ({ ...c, venue: e.target.value }))}
            />
          </label>
        </div>

        <label className="block space-y-1 text-sm">
          <span>الميزانية التقريبية (نطاق نصي — بدون اختراع أرقام)</span>
          <input
            className="w-full rounded-lg border border-slate-300 px-3 py-2"
            placeholder="مثال: سأتحدث مع الفريق"
            value={form.budget_range}
            onChange={(e) => setForm((c) => ({ ...c, budget_range: e.target.value }))}
          />
        </label>

        <label className="block space-y-1 text-sm">
          <span>شراء أو تأجير المستلزمات؟</span>
          <select
            className="w-full rounded-lg border border-slate-300 px-3 py-2"
            value={form.buy_or_rent}
            onChange={(e) => setForm((c) => ({ ...c, buy_or_rent: e.target.value }))}
          >
            <option value="buy">شراء</option>
            <option value="rent">تأجير</option>
            <option value="mix">مزيج</option>
            <option value="unsure">غير متأكد</option>
          </select>
        </label>

        <label className="block space-y-1 text-sm">
          <span>ملاحظات</span>
          <textarea
            className="min-h-28 w-full rounded-lg border border-slate-300 px-3 py-2"
            value={form.notes}
            onChange={(e) => setForm((c) => ({ ...c, notes: e.target.value }))}
          />
        </label>

        {!isAuthenticated ? (
          <p className="text-sm text-slate-600">
            <Link to="/login" className="underline">
              سجّل الدخول
            </Link>{' '}
            كعميل لإرسال الطلب.
          </p>
        ) : null}

        <button
          type="submit"
          disabled={busy || !isAuthenticated}
          className="inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white disabled:opacity-50"
        >
          {busy ? 'جاري الإرسال...' : 'إرسال طلب فعالية'}
        </button>
      </form>
    </section>
  )
}
