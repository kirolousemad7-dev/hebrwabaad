import { FormEvent, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { BrandLogo } from '../../components/brand/BrandLogo'
import { ContinueWithGoogleButton } from '../../components/auth/ContinueWithGoogleButton'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAuth } from '../../context/AuthContext'
import { persistSession } from '../../services/auth'
import { loginSupplier } from '../../services/supplierPortal'
import { describeApiError } from '../../utils/errors'

export function SupplierLoginPage() {
  const navigate = useNavigate()
  const { refreshUser } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function submitPassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    try {
      const response = await loginSupplier({ email, password })
      persistSession({ token: response.data.token, user: response.data.user })
      await refreshUser()
      navigate('/supplier', { replace: true })
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تسجيل الدخول.'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="mx-auto max-w-md space-y-6">
      <div className="flex justify-center"><BrandLogo size="auth" to="/" /></div>
      <h1 className="text-center text-2xl font-semibold">دخول الموردين</h1>
      <form onSubmit={(event) => void submitPassword(event)} className="space-y-4 rounded-2xl border bg-white p-6 shadow-sm">
        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
        <label className="block space-y-1 text-sm">
          <span>البريد</span>
          <input required type="email" value={email} onChange={(e) => setEmail(e.target.value)} className="w-full rounded-lg border px-3 py-2" />
        </label>
        <label className="block space-y-1 text-sm">
          <span>كلمة المرور</span>
          <input required type="password" value={password} onChange={(e) => setPassword(e.target.value)} className="w-full rounded-lg border px-3 py-2" />
        </label>
        <button type="submit" disabled={loading} className="min-h-11 w-full rounded-xl bg-slate-900 text-sm text-white disabled:opacity-60">
          دخول
        </button>
        <div className="relative py-1 text-center text-xs text-slate-400">
          <span className="bg-white px-2">أو</span>
        </div>
        <ContinueWithGoogleButton intent="supplier" next="/supplier" />
        <p className="text-center text-sm">
          <Link className="underline" to={`/login/code${email ? `?email=${encodeURIComponent(email)}` : ''}`}>دخول برمز لمرة واحدة</Link>
          {' · '}
          <Link className="underline" to="/supplier/register">إنشاء حساب مورد</Link>
          {' · '}
          <Link className="underline" to="/login">دخول عام</Link>
        </p>
      </form>
    </section>
  )
}
