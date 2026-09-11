import { FormEvent, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { ApiRequestError } from '../../services/api'
import { submitContactInquiry } from '../../services/marketing'
import { LandingCta } from './LandingCta'

export function ContactSection() {
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
    <section id="contact" className="scroll-mt-24 bg-white py-16 sm:py-20">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-2">
        <div className="space-y-4">
          <p className="text-sm font-medium text-brand-primary">تواصل معنا</p>
          <h2 className="text-3xl font-semibold text-brand-ink-900">خلينا نتكلم عن مشروعك</h2>
          <p className="leading-8 text-slate-600">
            أرسل فكرتك عبر النموذج، أو أنشئ حساباً للدخول إلى المنصة ومتابعة مشروعك مباشرة.
          </p>
          <div className="rounded-3xl border border-dashed border-slate-300 bg-brand-paper p-5 text-sm leading-7 text-slate-600">
            بيانات التواصل الرسمية تُعرض هنا عند توفرها من إعدادات المنصة. حالياً يمكنك الإرسال عبر النموذج أو إنشاء حساب.
          </div>
          <LandingCta to="/register" variant="navy">
            إنشاء حساب
          </LandingCta>
        </div>
        {done ? (
          <FeedbackBanner kind="success">وصلنا رسالتك. سنعود إليك عبر البريد.</FeedbackBanner>
        ) : (
          <form
            onSubmit={(event) => void handleSubmit(event)}
            className="space-y-4 rounded-3xl border border-slate-200 bg-brand-paper p-6"
          >
            {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
            <label className="block space-y-1 text-sm">
              <span>الاسم</span>
              <input required value={name} onChange={(event) => setName(event.target.value)} className="w-full" />
            </label>
            <label className="block space-y-1 text-sm">
              <span>البريد الإلكتروني</span>
              <input
                required
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                className="w-full"
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span>الهاتف (اختياري)</span>
              <input value={phone} onChange={(event) => setPhone(event.target.value)} className="w-full" />
            </label>
            <label className="block space-y-1 text-sm">
              <span>الرسالة</span>
              <textarea
                required
                minLength={10}
                value={message}
                onChange={(event) => setMessage(event.target.value)}
                className="w-full"
              />
            </label>
            <button
              type="submit"
              disabled={loading}
              className="min-h-11 rounded-xl bg-brand-ink-900 px-5 text-sm text-white disabled:opacity-60"
            >
              {loading ? 'جاري الإرسال...' : 'إرسال'}
            </button>
          </form>
        )}
      </div>
    </section>
  )
}
