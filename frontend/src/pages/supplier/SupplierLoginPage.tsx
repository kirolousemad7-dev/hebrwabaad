import { FormEvent, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { BrandLogo } from '../../components/brand/BrandLogo'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAuth } from '../../context/AuthContext'
import { persistSession } from '../../services/auth'
import { loginSupplier, requestSupplierOtp, verifySupplierOtp } from '../../services/supplierPortal'
import { describeApiError } from '../../utils/errors'

export function SupplierLoginPage() {
  const navigate = useNavigate()
  const { refreshUser } = useAuth()
  const [mode, setMode] = useState<'password' | 'otp'>('password')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [code, setCode] = useState('')
  const [otpSent, setOtpSent] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [info, setInfo] = useState<string | null>(null)
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

  async function sendOtp(event: FormEvent) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    try {
      await requestSupplierOtp(email)
      setOtpSent(true)
      setInfo('إن وُجد حساب مورد بهذا البريد فسيصلك رمز خلال دقائق.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إرسال الرمز.'))
    } finally {
      setLoading(false)
    }
  }

  async function submitOtp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    try {
      const response = await verifySupplierOtp({ email, code })
      persistSession({ token: response.data.token, user: response.data.user })
      await refreshUser()
      navigate('/supplier', { replace: true })
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر التحقق من الرمز.'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="mx-auto max-w-md space-y-6">
      <div className="flex justify-center"><BrandLogo size="auth" to="/" /></div>
      <h1 className="text-center text-2xl font-semibold">دخول الموردين</h1>
      <div className="flex justify-center gap-2">
        <button type="button" className={`rounded-full px-4 py-2 text-sm ${mode === 'password' ? 'bg-slate-900 text-white' : 'border'}`} onClick={() => setMode('password')}>كلمة مرور</button>
        <button type="button" className={`rounded-full px-4 py-2 text-sm ${mode === 'otp' ? 'bg-slate-900 text-white' : 'border'}`} onClick={() => setMode('otp')}>رمز لمرة واحدة</button>
      </div>
      <form onSubmit={(event) => void (mode === 'password' ? submitPassword(event) : otpSent ? submitOtp(event) : sendOtp(event))} className="space-y-4 rounded-2xl border bg-white p-6 shadow-sm">
        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
        {info ? <FeedbackBanner kind="success">{info}</FeedbackBanner> : null}
        <label className="block space-y-1 text-sm">
          <span>البريد</span>
          <input required type="email" value={email} onChange={(e) => setEmail(e.target.value)} className="w-full rounded-lg border px-3 py-2" />
        </label>
        {mode === 'password' ? (
          <label className="block space-y-1 text-sm">
            <span>كلمة المرور</span>
            <input required type="password" value={password} onChange={(e) => setPassword(e.target.value)} className="w-full rounded-lg border px-3 py-2" />
          </label>
        ) : otpSent ? (
          <label className="block space-y-1 text-sm">
            <span>رمز التحقق</span>
            <input required value={code} onChange={(e) => setCode(e.target.value)} maxLength={6} className="w-full rounded-lg border px-3 py-2 tracking-widest" />
          </label>
        ) : null}
        <button type="submit" disabled={loading} className="min-h-11 w-full rounded-xl bg-slate-900 text-sm text-white disabled:opacity-60">
          {mode === 'password' ? 'دخول' : otpSent ? 'تحقق ودخول' : 'إرسال الرمز'}
        </button>
        <p className="text-center text-sm">
          <Link className="underline" to="/supplier/register">إنشاء حساب مورد</Link>
          {' · '}
          <Link className="underline" to="/login">دخول عام</Link>
        </p>
      </form>
    </section>
  )
}
