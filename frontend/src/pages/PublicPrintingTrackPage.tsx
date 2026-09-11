import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { getPublicPrintingTrack, type PublicPrintingTrack } from '../services/printingQuotations'
import { describeApiError } from '../utils/errors'

function timelineLabel(event: NonNullable<PublicPrintingTrack['timeline']>[number]): string {
  if (event.label) return event.label
  if (event.status_label) return event.status_label
  if (event.meta && typeof event.meta.status_label === 'string') return event.meta.status_label
  if (event.event === 'accepted') return 'تم قبول العرض'
  if (event.event === 'sent') return 'تم إرسال العرض'
  if (event.event === 'viewed') return 'تمت مشاهدة العرض'
  if (event.event === 'payment_recorded') return 'تم تسجيل دفعة'
  if (event.event === 'status_changed') return 'تحديث حالة الطلب'
  if (event.event === 'rejected') return 'تم رفض العرض'
  return event.event || 'حدث'
}

export function PublicPrintingTrackPage() {
  const { token } = useParams()
  const [data, setData] = useState<PublicPrintingTrack | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load() {
    if (!token) {
      setError('رابط غير صالح.')
      setLoading(false)
      return
    }

    setLoading(true)
    setError(null)
    try {
      const payload = await getPublicPrintingTrack(token)
      setData(payload)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل حالة الطلب.'))
      setData(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token])

  if (loading) {
    return (
      <div dir="rtl" className="min-h-screen bg-brand-canvas px-4 py-10">
        <div className="mx-auto max-w-md text-center text-sm text-slate-600">جاري تحميل حالة الطلب...</div>
      </div>
    )
  }

  if (error || !data) {
    return (
      <div dir="rtl" className="min-h-screen bg-brand-canvas px-4 py-10">
        <div className="mx-auto max-w-md space-y-4 rounded-2xl border border-red-200 bg-white p-5 text-center">
          <p className="text-sm text-red-800">{error || 'الطلب غير متاح.'}</p>
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

  const timeline = data.timeline ?? []

  return (
    <div dir="rtl" className="min-h-screen bg-gradient-to-b from-brand-canvas via-slate-50 to-brand-canvas">
      <div className="mx-auto w-full max-w-md space-y-5 px-4 py-8 sm:max-w-lg">
        <header className="space-y-3 text-center">
          <BrandLogo size="auth" to="/" className="justify-center" />
          <div>
            <p className="text-sm text-amber-800">حبر وأبعاد</p>
            <h1 className="text-2xl font-semibold text-slate-900">متابعة الطلب</h1>
            {data.reference ? (
              <p className="mt-1 font-mono text-sm text-slate-600" dir="ltr">
                {data.reference}
              </p>
            ) : null}
          </div>
          <span className="inline-flex rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-950">
            {data.status_label || data.status_key}
          </span>
        </header>

        <section className="rounded-2xl border border-slate-200 bg-brand-paper p-4 text-sm">
          <p className="font-medium text-slate-900">{data.product_name || 'طلب طباعة'}</p>
          <p className="mt-2 text-slate-600">
            الموعد المطلوب:{' '}
            {data.required_date ? new Date(data.required_date).toLocaleDateString('ar-SA') : '—'}
          </p>
          {data.delivered_at ? (
            <p className="mt-1 text-slate-600">
              تاريخ التسليم: {new Date(data.delivered_at).toLocaleString('ar-SA')}
            </p>
          ) : null}
        </section>

        <section className="rounded-2xl border border-slate-200 bg-white p-4">
          <h2 className="mb-3 text-sm font-semibold text-slate-900">الجدول الزمني</h2>
          {timeline.length === 0 ? (
            <p className="text-sm text-slate-500">لا أحداث بعد.</p>
          ) : (
            <ol className="relative space-y-4 border-s-2 border-slate-200 ps-4">
              {timeline.map((event, index) => (
                <li key={`${event.event ?? 'e'}-${event.created_at ?? index}`} className="relative">
                  <span className="absolute -start-[1.35rem] top-1.5 size-2.5 rounded-full bg-amber-500" />
                  <p className="text-sm font-medium text-slate-900">{timelineLabel(event)}</p>
                  {event.created_at ? (
                    <p className="text-xs text-slate-500">
                      {new Date(event.created_at).toLocaleString('ar-SA')}
                    </p>
                  ) : null}
                </li>
              ))}
            </ol>
          )}
        </section>

        {data.files && data.files.length > 0 ? (
          <section className="rounded-2xl border border-slate-200 bg-white p-4">
            <h2 className="mb-2 text-sm font-semibold">ملفات</h2>
            <ul className="space-y-1 text-sm">
              {data.files.map((file, index) => (
                <li key={file.id ?? index}>
                  {file.url ? (
                    <a href={file.url} className="underline" target="_blank" rel="noreferrer">
                      {file.name || `ملف ${index + 1}`}
                    </a>
                  ) : (
                    <span>{file.name || `ملف ${index + 1}`}</span>
                  )}
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </div>
    </div>
  )
}
