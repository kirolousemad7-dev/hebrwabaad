import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { FormEvent, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { useAuth } from '../context/AuthContext'
import { ApiRequestError } from '../services/api'

export function RegisterPage() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)

    if (password !== passwordConfirmation) {
      setError('تأكيد كلمة المرور غير مطابق.')
      return
    }

    setLoading(true)

    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      navigate('/dashboard', { replace: true })
    } catch (caught) {
      if (caught instanceof ApiRequestError) {
        const details = caught.body?.errors
          ? Object.values(caught.body.errors).flat().join(' ')
          : caught.message
        setError(details)
      } else {
        setError('تعذر إنشاء الحساب.')
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
      <h1 className="text-center text-2xl font-semibold">إنشاء حساب</h1>
      <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        {error ? (
          <FeedbackBanner kind="error">{error}</FeedbackBanner>
        ) : null}
        <label htmlFor="register-name" className="block space-y-1 text-sm">
          <span>الاسم</span>
          <input
            id="register-name"
            autoComplete="name"
            required
            value={name}
            onChange={(event) => setName(event.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
        </label>
        <label htmlFor="register-email" className="block space-y-1 text-sm">
          <span>البريد الإلكتروني</span>
          <input
            id="register-email"
            type="email"
            autoComplete="email"
            required
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
        </label>
        <label htmlFor="register-password" className="block space-y-1 text-sm">
          <span>كلمة المرور</span>
          <input
            id="register-password"
            type="password"
            autoComplete="new-password"
            required
            minLength={8}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
        </label>
        <label htmlFor="register-password-confirmation" className="block space-y-1 text-sm">
          <span>تأكيد كلمة المرور</span>
          <input
            id="register-password-confirmation"
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
          disabled={loading}
          className="min-h-11 w-full rounded-lg bg-slate-900 px-4 text-white shadow-sm disabled:opacity-60"
        >
          {loading ? 'جاري الإنشاء...' : 'تسجيل'}
        </button>
      </form>
      <p className="text-center text-sm text-slate-600">
        لديك حساب؟ <Link to="/login" className="underline">تسجيل الدخول</Link>
      </p>
    </section>
  )
}
