import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { FormEvent, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { useAuth } from '../context/AuthContext'
import { ApiRequestError } from '../services/api'
import { isSafeInternalPath } from '../utils/orderIntent'
import { homePathForRole, isCatalogManager } from '../utils/roles'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setLoading(true)

    try {
      const user = await login(email, password)
      const from = (location.state as { from?: string } | null)?.from

      if (from && !isCatalogManager(user.role) && isSafeInternalPath(from)) {
        navigate(from, { replace: true })
      } else {
        navigate(homePathForRole(user.role), { replace: true })
      }
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر تسجيل الدخول.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="mx-auto max-w-md space-y-6">
      <div className="flex justify-center">
        <BrandLogo size="auth" to="/" />
      </div>
      <h1 className="text-center text-2xl font-semibold">تسجيل الدخول</h1>
      <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        {error ? (
          <FeedbackBanner kind="error">{error}</FeedbackBanner>
        ) : null}
        <label htmlFor="login-email" className="block space-y-1 text-sm">
          <span>البريد الإلكتروني</span>
          <input
            id="login-email"
            type="email"
            autoComplete="email"
            required
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
        </label>
        <label htmlFor="login-password" className="block space-y-1 text-sm">
          <span>كلمة المرور</span>
          <input
            id="login-password"
            type="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
        </label>
        <button
          type="submit"
          disabled={loading}
          className="min-h-11 w-full rounded-lg bg-slate-900 px-4 text-white shadow-sm disabled:opacity-60"
        >
          {loading ? 'جاري الدخول...' : 'دخول'}
        </button>
      </form>
      <p className="text-center text-sm text-slate-600">
        <Link to="/forgot-password" className="underline">نسيت كلمة المرور؟</Link>
      </p>
      <p className="text-center text-sm text-slate-600">
        ليس لديك حساب؟ <Link to="/register" className="underline">إنشاء حساب</Link>
      </p>
    </section>
  )
}
