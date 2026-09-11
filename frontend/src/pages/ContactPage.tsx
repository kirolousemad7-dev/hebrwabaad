import { FormEvent, useState } from 'react'
import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { LandingCta } from '../components/landing/LandingCta'
import { ApiRequestError } from '../services/api'
import { submitContactInquiry } from '../services/marketing'

export function ContactPage() {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setLoading(true)

    try {
      await submitContactInquiry({ name, email, phone: phone || undefined, message })
      setDone(true)
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إرسال الرسالة.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="grid gap-10 lg:grid-cols-2">
      <div className="space-y-4">
        <h1 className="text-3xl font-semibold">تواصل معنا</h1>
        <p className="leading-8 text-slate-600">
          أرسل استفسارك، أو ابدأ مباشرة بإنشاء حساب للدخول إلى المنصة.
        </p>
        <div className="flex flex-wrap gap-3">
          <LandingCta to="/register" variant="navy">
            ابدأ مشروعك
          </LandingCta>
          <LandingCta to="/consultant" variant="ghost">
            المستشار الذكي
          </LandingCta>
        </div>
      </div>
      {done ? (
        <FeedbackBanner kind="success">وصلنا رسالتك. سنعود إليك عبر البريد.</FeedbackBanner>
      ) : (
        <form onSubmit={(event) => void handleSubmit(event)} className="space-y-4 rounded-3xl border border-slate-200 bg-white p-6">
          {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
          <label className="block space-y-1 text-sm">
            <span>الاسم</span>
            <input required value={name} onChange={(event) => setName(event.target.value)} />
          </label>
          <label className="block space-y-1 text-sm">
            <span>البريد الإلكتروني</span>
            <input required type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
          </label>
          <label className="block space-y-1 text-sm">
            <span>الهاتف (اختياري)</span>
            <input value={phone} onChange={(event) => setPhone(event.target.value)} />
          </label>
          <label className="block space-y-1 text-sm">
            <span>الرسالة</span>
            <textarea required minLength={10} value={message} onChange={(event) => setMessage(event.target.value)} />
          </label>
          <button
            type="submit"
            disabled={loading}
            className="min-h-11 rounded-xl bg-slate-900 px-5 text-sm text-white disabled:opacity-60"
          >
            {loading ? 'جاري الإرسال...' : 'إرسال'}
          </button>
        </form>
      )}
    </section>
  )
}
