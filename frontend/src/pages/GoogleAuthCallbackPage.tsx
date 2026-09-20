import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { FeedbackBanner } from '../components/ui/FeedbackBanner'
import { useAuth } from '../context/AuthContext'
import { exchangeGoogleAuthCode } from '../services/googleAuth'
import { describeApiError } from '../utils/errors'
import { isSafeInternalPath } from '../utils/orderIntent'
import { homePathForRole, isCatalogManager } from '../utils/roles'

export function GoogleAuthCallbackPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { acceptSession } = useAuth()
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    const code = searchParams.get('code')
    const next = searchParams.get('next')

    if (!code) {
      setError('رمز تسجيل الدخول عبر Google غير صالح.')
      return
    }

    exchangeGoogleAuthCode(code)
      .then((response) => {
        if (cancelled) {
          return
        }

        const user = acceptSession({
          token: response.data.token,
          user: response.data.user,
        })

        const destinationCandidate = next || response.data.next || null
        if (
          destinationCandidate
          && !isCatalogManager(user.role)
          && isSafeInternalPath(destinationCandidate)
        ) {
          navigate(destinationCandidate, { replace: true })
          return
        }

        navigate(homePathForRole(user.role), { replace: true })
      })
      .catch((caught) => {
        if (!cancelled) {
          setError(describeApiError(caught, 'تعذر إكمال تسجيل الدخول عبر Google.'))
        }
      })

    return () => {
      cancelled = true
    }
  }, [acceptSession, navigate, searchParams])

  return (
    <section className="mx-auto max-w-md space-y-6">
      <div className="flex justify-center">
        <BrandLogo size="auth" to="/" />
      </div>
      <h1 className="text-center text-2xl font-semibold">إكمال الدخول عبر Google</h1>
      {error ? (
        <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <FeedbackBanner kind="error">{error}</FeedbackBanner>
          <p className="text-center text-sm text-slate-600">
            <Link to="/login" className="underline">العودة لتسجيل الدخول</Link>
          </p>
        </div>
      ) : (
        <p className="text-center text-sm text-slate-600">جاري تأكيد الهوية...</p>
      )}
    </section>
  )
}
