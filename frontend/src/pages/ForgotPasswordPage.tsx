import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { ApiRequestError } from '../services/api'
import { requestPasswordReset } from '../services/auth'

const ACCEPTED_COPY =
  'إذا كان هناك حساب مرتبط بهذا البريد فسنرسل رابط إعادة تعيين كلمة المرور.'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [accepted, setAccepted] = useState(false)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setLoading(true)

    try {
      await requestPasswordReset(email)
      setAccepted(true)
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إرسال الطلب. حاول مرة أخرى.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="mx-auto max-w-md space-y-6">
      <div className="flex justify-center">
        <BrandLogo size="auth" to="/" />
      </div>
      <h1 className="text-center text-2xl font-semibold">استعادة كلمة المرور</h1>
      <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        {error ? (
          <FeedbackBanner kind="error">{error}</FeedbackBanner>
        ) : null}
        {accepted ? (
          <FeedbackBanner kind="success">{ACCEPTED_COPY}</FeedbackBanner>
        ) : (
          <p className="text-sm text-slate-600">
            أدخل بريدك الإلكتروني وسنرسل رابطًا لإعادة تعيين كلمة المرور إن وُجد حساب مرتبط به.
          </p>
        )}
        <label htmlFor="forgot-email" className="block space-y-1 text-sm">
          <span>البريد الإلكتروني</span>
          <input
            id="forgot-email"
            type="email"
            autoComplete="email"
            required
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
        </label>
        <button
          type="submit"
          disabled={loading || accepted}
          className="min-h-11 w-full rounded-lg bg-slate-900 px-4 text-white shadow-sm disabled:opacity-60"
        >
          {loading ? 'جاري الإرسال...' : 'إرسال رابط الاستعادة'}
        </button>
      </form>
      <p className="text-center text-sm text-slate-600">
        تذكرت كلمة المرور؟ <Link to="/login" className="underline">تسجيل الدخول</Link>
      </p>
    </section>
  )
}
