import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { FormEvent, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { useAuth } from '../context/AuthContext'
import { ApiRequestError } from '../services/api'
import { resetPassword } from '../services/auth'

export function ResetPasswordPage() {
  const { discardSession } = useAuth()
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token') ?? ''
  const emailFromLink = searchParams.get('email') ?? ''
  const [email, setEmail] = useState(emailFromLink)
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)
  const [loading, setLoading] = useState(false)

  const missingToken = useMemo(() => token.trim() === '', [token])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)

    if (missingToken) {
      setError('رابط إعادة التعيين غير صالح أو ناقص.')
      return
    }

    if (password !== passwordConfirmation) {
      setError('تأكيد كلمة المرور غير مطابق.')
      return
    }

    setLoading(true)

    try {
      await resetPassword({
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      discardSession()
      setDone(true)
    } catch (caught) {
      if (caught instanceof ApiRequestError) {
        const details = caught.body?.errors
          ? Object.values(caught.body.errors).flat().join(' ')
          : caught.message === 'Unable to reset password.'
            ? 'تعذر إعادة تعيين كلمة المرور. قد يكون الرابط منتهيًا أو مستخدمًا مسبقًا.'
            : caught.message
        setError(details)
      } else {
        setError('تعذر إعادة تعيين كلمة المرور.')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="mx-auto max-w-md space-y-6">
      <div className="flex justify-center">
        <BrandLogo size="auth" to="/" />
      </div>
      <h1 className="text-center text-2xl font-semibold">تعيين كلمة مرور جديدة</h1>
      {done ? (
        <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <FeedbackBanner kind="success">تم تعيين كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.</FeedbackBanner>
          <Link
            to="/login"
            className="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-slate-900 px-4 text-white"
          >
            تسجيل الدخول
          </Link>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
          {missingToken ? (
            <FeedbackBanner kind="warning">
              رابط إعادة التعيين غير صالح. اطلب رابطًا جديدًا من صفحة الاستعادة.
            </FeedbackBanner>
          ) : null}
          <label htmlFor="reset-email" className="block space-y-1 text-sm">
            <span>البريد الإلكتروني</span>
            <input
              id="reset-email"
              type="email"
              autoComplete="email"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            />
          </label>
          <label htmlFor="reset-password" className="block space-y-1 text-sm">
            <span>كلمة المرور الجديدة</span>
            <input
              id="reset-password"
              type="password"
              autoComplete="new-password"
              required
              minLength={8}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            />
          </label>
          <label htmlFor="reset-password-confirmation" className="block space-y-1 text-sm">
            <span>تأكيد كلمة المرور</span>
            <input
              id="reset-password-confirmation"
              type="password"
              autoComplete="new-password"
              required
              minLength={8}
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            />
          </label>
          <button
            type="submit"
            disabled={loading || missingToken}
            className="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-white shadow-sm disabled:opacity-60"
          >
            {loading ? 'جاري الحفظ...' : 'حفظ كلمة المرور'}
          </button>
        </form>
      )}
      <p className="text-center text-sm text-slate-600">
        تحتاج رابطًا جديدًا؟ <Link to="/forgot-password" className="underline">استعادة كلمة المرور</Link>
      </p>
    </section>
  )
}
