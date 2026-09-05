import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { createCustomerPackageOrder } from '../../services/orders'
import { describeApiError } from '../../utils/errors'
import { PACKAGE_ORDER_COPY } from '../../utils/orderIntent'
import { customerPayPath } from '../../utils/payments'

export function CustomerPackageOrderPage() {
  const { slug } = useParams()
  const [searchParams] = useSearchParams()
  const tier = searchParams.get('tier')
  const navigate = useNavigate()
  const started = useRef(false)
  const [attempt, setAttempt] = useState(0)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (started.current) {
      return
    }

    if (!slug) {
      setError(PACKAGE_ORDER_COPY.unavailable)
      return
    }

    started.current = true
    setError(null)

    void createCustomerPackageOrder(slug, tier)
      .then((response) => {
        navigate(customerPayPath(response.data.id), { replace: true })
      })
      .catch((caught) => {
        setError(describeApiError(caught, PACKAGE_ORDER_COPY.createError))
      })
  }, [attempt, navigate, slug, tier])

  function retry() {
    started.current = false
    setAttempt((current) => current + 1)
  }

  return (
    <section className="mx-auto max-w-xl space-y-5">
      <header className="space-y-2">
        <h1 className="text-2xl font-semibold">تجهيز طلب الباقة</h1>
        <p className="text-sm leading-7 text-slate-600">
          نتحقق من الباقة وننشئ طلبك بالمبلغ المسجل في HEBR، ثم ننقلك إلى صفحة الدفع الآمنة.
        </p>
      </header>

      {error ? (
        <>
          <FeedbackBanner kind="error">{error}</FeedbackBanner>
          <div className="flex flex-wrap gap-3">
            <button
              type="button"
              onClick={retry}
              className="min-h-11 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white"
            >
              إعادة المحاولة
            </button>
            <Link
              to="/packages"
              className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 text-sm"
            >
              كل الباقات
            </Link>
          </div>
        </>
      ) : (
        <FeedbackBanner kind="info">جاري إنشاء الطلب والانتقال إلى الدفع...</FeedbackBanner>
      )}
    </section>
  )
}
