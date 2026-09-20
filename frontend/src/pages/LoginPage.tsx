import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { FormEvent, useMemo, useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { ContinueWithGoogleButton, googleErrorMessage } from '../components/auth/ContinueWithGoogleButton'
import { useAuth } from '../context/AuthContext'
import { requestCustomerOtp, verifyCustomerOtp } from '../services/auth'
import { describeApiError } from '../utils/errors'
import { isSafeInternalPath } from '../utils/orderIntent'
import { homePathForRole, isCatalogManager } from '../utils/roles'

type LoginMode = 'password' | 'otp'

export function LoginPage() {
  const { login, acceptSession } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const [mode, setMode] = useState<LoginMode>('password')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [otpCode, setOtpCode] = useState('')
  const [otpSent, setOtpSent] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(() => googleErrorMessage(searchParams.get('google_error')))
  const [loading, setLoading] = useState(false)

  const nextPath = useMemo(() => {
    const fromState = (location.state as { from?: string } | null)?.from
    const fromQuery = searchParams.get('next')
    return fromState || fromQuery
  }, [location.state, searchParams])

  function navigateAfterAuth(role: string) {
    const from = nextPath
    if (from && !isCatalogManager(role) && isSafeInternalPath(from)) {
      navigate(from, { replace: true })
      return
    }
    navigate(homePathForRole(role), { replace: true })
  }

  async function handlePasswordSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setNotice(null)
    setLoading(true)

    const formData = new FormData(event.currentTarget)
    const submittedEmail = String(formData.get('email') ?? email).trim()
    const submittedPassword = String(formData.get('password') ?? password)
    setEmail(submittedEmail)
    setPassword(submittedPassword)

    try {
      const user = await login(submittedEmail, submittedPassword)
      navigateAfterAuth(user.role)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تسجيل الدخول.'))
    } finally {
      setLoading(false)
    }
  }

  async function handleSendOtp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setNotice(null)
    setLoading(true)

    const submittedEmail = email.trim()
    try {
      await requestCustomerOtp(submittedEmail)
      setOtpSent(true)
      setNotice('إذا كان البريد مسجلاً كعميل، سيصلك رمز الدخول خلال لحظات.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إرسال رمز الدخول.'))
    } finally {
      setLoading(false)
    }
  }

  async function handleVerifyOtp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setNotice(null)
    setLoading(true)

    try {
      const response = await verifyCustomerOtp({
        email: email.trim(),
        code: otpCode.trim(),
      })
      const user = acceptSession(response.data)
      navigateAfterAuth(user.role)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر التحقق من الرمز.'))
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

      <div className="flex rounded-lg border border-slate-200 bg-slate-50 p-1 text-sm">
        <button
          type="button"
          className={`min-h-10 flex-1 rounded-md px-3 ${mode === 'password' ? 'bg-white font-medium shadow-sm' : 'text-slate-600'}`}
          onClick={() => {
            setMode('password')
            setError(null)
            setNotice(null)
          }}
        >
          كلمة المرور
        </button>
        <button
          type="button"
          className={`min-h-10 flex-1 rounded-md px-3 ${mode === 'otp' ? 'bg-white font-medium shadow-sm' : 'text-slate-600'}`}
          onClick={() => {
            setMode('otp')
            setError(null)
            setNotice(null)
          }}
        >
          رمز البريد (OTP)
        </button>
      </div>

      {mode === 'password' ? (
        <form onSubmit={handlePasswordSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
          <label htmlFor="login-email" className="block space-y-1 text-sm">
            <span>البريد الإلكتروني</span>
            <input
              id="login-email"
              name="email"
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
              name="password"
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
          <div className="relative py-1 text-center text-xs text-slate-400">
            <span className="bg-white px-2">أو</span>
          </div>
          <ContinueWithGoogleButton intent="login" next={nextPath} />
        </form>
      ) : (
        <form
          onSubmit={otpSent ? handleVerifyOtp : handleSendOtp}
          className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
          {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
          <label htmlFor="otp-email" className="block space-y-1 text-sm">
            <span>البريد الإلكتروني</span>
            <input
              id="otp-email"
              name="email"
              type="email"
              autoComplete="email"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            />
          </label>
          {otpSent ? (
            <label htmlFor="otp-code" className="block space-y-1 text-sm">
              <span>رمز الدخول</span>
              <input
                id="otp-code"
                name="code"
                inputMode="numeric"
                autoComplete="one-time-code"
                required
                minLength={6}
                maxLength={6}
                value={otpCode}
                onChange={(event) => setOtpCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 tracking-widest focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                dir="ltr"
              />
            </label>
          ) : null}
          <button
            type="submit"
            disabled={loading}
            className="min-h-11 w-full rounded-lg bg-slate-900 px-4 text-white shadow-sm disabled:opacity-60"
          >
            {loading ? 'جاري المعالجة...' : otpSent ? 'تأكيد الرمز والدخول' : 'إرسال رمز الدخول'}
          </button>
          {otpSent ? (
            <button
              type="button"
              className="w-full text-sm text-slate-600 underline"
              disabled={loading}
              onClick={() => {
                setOtpSent(false)
                setOtpCode('')
                setNotice(null)
              }}
            >
              إعادة إرسال الرمز
            </button>
          ) : null}
          <div className="relative py-1 text-center text-xs text-slate-400">
            <span className="bg-white px-2">أو</span>
          </div>
          <ContinueWithGoogleButton intent="login" next={nextPath} />
        </form>
      )}

      <p className="text-center text-sm text-slate-600">
        <Link to="/forgot-password" className="underline">نسيت كلمة المرور؟</Link>
      </p>
      <p className="text-center text-sm text-slate-600">
        ليس لديك حساب؟ <Link to="/register" className="underline">إنشاء حساب</Link>
      </p>
    </section>
  )
}
