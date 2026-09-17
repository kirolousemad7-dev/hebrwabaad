import { FormEvent, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { VerificationScreen, type VerificationUiStatus } from '../../components/verification/VerificationScreen'
import { useAuth } from '../../context/AuthContext'
import {
  getSupplierEmailStatus,
  resendSupplierEmailVerification,
  verifySupplierEmail,
} from '../../services/supplierPortal'
import { describeApiError } from '../../utils/errors'

export function VerifyEmailPage() {
  const { isAuthenticated, user, refreshUser } = useAuth()
  const [params] = useSearchParams()
  const [status, setStatus] = useState<VerificationUiStatus>('loading')
  const [error, setError] = useState<string | null>(null)
  const [info, setInfo] = useState<string | null>(null)
  const [resending, setResending] = useState(false)

  useEffect(() => {
    let cancelled = false

    async function run() {
      const id = params.get('id')
      const hash = params.get('hash')
      const expires = params.get('expires')
      const signature = params.get('signature')

      if (id && hash && expires && signature) {
        try {
          await verifySupplierEmail({ id, hash, expires, signature })
          if (cancelled) return
          setStatus('verified')
          setInfo('تم تأكيد البريد الإلكتروني بنجاح.')
          if (isAuthenticated) {
            await refreshUser()
          }
          return
        } catch (caught) {
          if (cancelled) return
          setError(describeApiError(caught, 'تعذر تأكيد البريد.'))
          setStatus('expired')
        }
      }

      if (isAuthenticated && user?.role === 'SUPPLIER') {
        try {
          const response = await getSupplierEmailStatus()
          if (cancelled) return
          const next = response.data.status
          setStatus(next === 'verified' || next === 'pending' || next === 'expired' ? next : 'pending')
        } catch (caught) {
          if (cancelled) return
          setError(describeApiError(caught, 'تعذر جلب حالة التحقق.'))
          setStatus('error')
        }
        return
      }

      if (!id) {
        setStatus('pending')
        setInfo('افتح الرابط من رسالة التأكيد، أو سجّل الدخول لإعادة الإرسال.')
      }
    }

    void run()
    return () => {
      cancelled = true
    }
  }, [params, isAuthenticated, user?.role, refreshUser])

  async function resend(event: FormEvent) {
    event.preventDefault()
    setResending(true)
    setError(null)
    try {
      const response = await resendSupplierEmailVerification()
      setStatus(response.data.status === 'verified' ? 'verified' : 'pending')
      setInfo('أُعيد إرسال رابط التحقق إن كان الحساب بحاجة لذلك.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إعادة الإرسال.'))
    } finally {
      setResending(false)
    }
  }

  return (
    <VerificationScreen
      title="تأكيد البريد"
      description="تحقق من بريدك عبر الرابط الموقع، أو أعد الإرسال عند انتهاء الصلاحية."
      status={status}
      error={error}
      info={info}
    >
      {status === 'verified' ? (
        <Link className="block text-center text-sm underline" to={isAuthenticated ? '/supplier' : '/supplier/login'}>
          المتابعة إلى بوابة المورد
        </Link>
      ) : null}

      {isAuthenticated && user?.role === 'SUPPLIER' && status !== 'verified' ? (
        <form onSubmit={(event) => void resend(event)} className="space-y-3">
          <button
            type="submit"
            disabled={resending}
            className="min-h-11 w-full rounded-xl bg-slate-900 text-sm text-white disabled:opacity-60"
          >
            إعادة إرسال رابط التحقق
          </button>
        </form>
      ) : null}

      {!isAuthenticated ? (
        <p className="text-center text-sm">
          <Link className="underline" to="/supplier/login">دخول المورد</Link>
          {' · '}
          <Link className="underline" to="/login/code">دخول برمز لمرة واحدة</Link>
        </p>
      ) : null}
    </VerificationScreen>
  )
}
