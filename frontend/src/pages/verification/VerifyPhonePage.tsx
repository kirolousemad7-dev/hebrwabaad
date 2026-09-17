import { FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { VerificationScreen, type VerificationUiStatus } from '../../components/verification/VerificationScreen'
import { requestSupplierPhoneOtp, verifySupplierPhoneOtp } from '../../services/supplierPortal'
import { describeApiError } from '../../utils/errors'

export function VerifyPhonePage() {
  const [phone, setPhone] = useState('')
  const [code, setCode] = useState('')
  const [sent, setSent] = useState(false)
  const [status, setStatus] = useState<VerificationUiStatus>('pending')
  const [error, setError] = useState<string | null>(null)
  const [info, setInfo] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function sendCode(event: FormEvent) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    try {
      await requestSupplierPhoneOtp(phone || undefined)
      setSent(true)
      setStatus('pending')
      setInfo('إن وُجد رقم هاتف صالح فسيصلك رمز عبر الرسائل القصيرة.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إرسال رمز الهاتف.'))
    } finally {
      setLoading(false)
    }
  }

  async function verify(event: FormEvent) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    try {
      await verifySupplierPhoneOtp({ code, phone: phone || undefined })
      setStatus('verified')
      setInfo('تم تأكيد رقم الهاتف بنجاح.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر التحقق من الرمز.'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <VerificationScreen
      title="تأكيد الهاتف"
      description="أدخل رقم الجوال واستلم رمزاً رقمياً لمرة واحدة عبر مزود الرسائل المُعدّ."
      status={status}
      error={error}
      info={info}
    >
      {status === 'verified' ? (
        <Link className="block text-center text-sm underline" to="/supplier">العودة للوحة المورد</Link>
      ) : (
        <form onSubmit={(event) => void (sent ? verify(event) : sendCode(event))} className="space-y-3">
          <label className="block space-y-1 text-sm">
            <span>رقم الهاتف</span>
            <input
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="0500000000"
              className="w-full rounded-lg border px-3 py-2"
            />
          </label>
          {sent ? (
            <label className="block space-y-1 text-sm">
              <span>رمز التحقق</span>
              <input
                required
                value={code}
                onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 8))}
                inputMode="numeric"
                maxLength={8}
                className="w-full rounded-lg border px-3 py-2 tracking-widest"
              />
            </label>
          ) : null}
          <button
            type="submit"
            disabled={loading}
            className="min-h-11 w-full rounded-xl bg-slate-900 text-sm text-white disabled:opacity-60"
          >
            {sent ? 'تأكيد الرمز' : 'إرسال الرمز'}
          </button>
          {sent ? (
            <button
              type="button"
              disabled={loading}
              onClick={() => {
                void (async () => {
                  setLoading(true)
                  setError(null)
                  try {
                    await requestSupplierPhoneOtp(phone || undefined)
                    setInfo('أُعيد إرسال الرمز إن أمكن.')
                  } catch (caught) {
                    setError(describeApiError(caught, 'تعذر إعادة الإرسال.'))
                  } finally {
                    setLoading(false)
                  }
                })()
              }}
              className="w-full text-sm underline disabled:opacity-60"
            >
              إعادة الإرسال
            </button>
          ) : null}
        </form>
      )}
    </VerificationScreen>
  )
}
