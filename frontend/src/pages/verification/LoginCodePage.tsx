import { FormEvent, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { VerificationScreen } from '../../components/verification/VerificationScreen'
import { useAuth } from '../../context/AuthContext'
import { persistSession } from '../../services/auth'
import { requestSupplierOtp, verifySupplierOtp } from '../../services/supplierPortal'
import { describeApiError } from '../../utils/errors'

export function LoginCodePage() {
  const navigate = useNavigate()
  const { refreshUser } = useAuth()
  const [params] = useSearchParams()
  const [email, setEmail] = useState(params.get('email') ?? '')
  const [code, setCode] = useState('')
  const [sent, setSent] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [info, setInfo] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function sendCode(event: FormEvent) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    try {
      await requestSupplierOtp(email)
      setSent(true)
      setInfo('إن وُجد حساب مورد بهذا البريد فسيصلك رمز خلال دقائق.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إرسال الرمز.'))
    } finally {
      setLoading(false)
    }
  }

  async function verify(event: FormEvent) {
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
    <VerificationScreen
      title="دخول برمز لمرة واحدة"
      description="أدخل بريد المورد لاستلام رمز دخول مؤقت. لا نكشف إن كان البريد مسجلاً أم لا."
      status="idle"
      error={error}
      info={info}
    >
      <form onSubmit={(event) => void (sent ? verify(event) : sendCode(event))} className="space-y-3">
        <label className="block space-y-1 text-sm">
          <span>البريد</span>
          <input
            required
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="w-full rounded-lg border px-3 py-2"
          />
        </label>
        {sent ? (
          <label className="block space-y-1 text-sm">
            <span>رمز الدخول</span>
            <input
              required
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
              inputMode="numeric"
              maxLength={6}
              className="w-full rounded-lg border px-3 py-2 tracking-widest"
            />
          </label>
        ) : null}
        <button
          type="submit"
          disabled={loading}
          className="min-h-11 w-full rounded-xl bg-slate-900 text-sm text-white disabled:opacity-60"
        >
          {sent ? 'تحقق ودخول' : 'إرسال الرمز'}
        </button>
        <p className="text-center text-sm">
          <Link className="underline" to="/supplier/login">دخول بكلمة مرور</Link>
          {' · '}
          <Link className="underline" to="/supplier/register">تسجيل مورد</Link>
        </p>
      </form>
    </VerificationScreen>
  )
}
