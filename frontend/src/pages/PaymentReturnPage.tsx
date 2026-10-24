import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { publicFetch } from '../services/api'
import { describeApiError } from '../utils/errors'

const MAX_POLLS = 8
const POLL_MS = 2000

type View = 'checking' | 'paid' | 'failed' | 'cancelled' | 'pending'

type StatusPayload = {
  id: number
  status: string
  amount: string
  currency: string
}

function viewFor(status: string): View {
  const value = status.toUpperCase()
  if (value === 'PAID') return 'paid'
  if (value === 'CANCELLED') return 'cancelled'
  if (value === 'FAILED' || value === 'REJECTED') return 'failed'
  return 'pending'
}

export function PaymentReturnPage() {
  const [params] = useSearchParams()
  const paymentId = Number(params.get('payment') || params.get('payment_id') || 0)
  const cartId = params.get('cart_id') || ''
  const [view, setView] = useState<View>('checking')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!Number.isFinite(paymentId) || paymentId <= 0 || cartId === '') {
      setView('pending')
      setError('جاري انتظار تأكيد الدفع من PayTabs. لا نعتمد على صفحة العودة وحدها.')
      return
    }

    let cancelled = false
    let attempts = 0
    let timer: number | undefined

    async function poll() {
      attempts += 1
      try {
        const payload = await publicFetch<StatusPayload>(
          `/api/payments/paytabs/${paymentId}/status?cart_id=${encodeURIComponent(cartId)}`,
        )
        if (cancelled) return
        const next = viewFor(payload.status)
        if (next === 'paid' || next === 'failed' || next === 'cancelled') {
          setView(next)
          return
        }
        if (attempts >= MAX_POLLS) {
          setView('pending')
          return
        }
        setView('checking')
        timer = window.setTimeout(() => void poll(), POLL_MS)
      } catch (caught) {
        if (cancelled) return
        if (attempts >= MAX_POLLS) {
          setView('pending')
          setError(describeApiError(caught, 'لم يصل تأكيد الدفع بعد.'))
          return
        }
        timer = window.setTimeout(() => void poll(), POLL_MS)
      }
    }

    void poll()

    return () => {
      cancelled = true
      if (timer) window.clearTimeout(timer)
    }
  }, [cartId, paymentId])

  const title =
    view === 'paid'
      ? 'تم الدفع بنجاح'
      : view === 'failed'
        ? 'فشل الدفع'
        : view === 'cancelled'
          ? 'تم إلغاء الدفع'
          : view === 'pending'
            ? 'الدفع قيد المعالجة'
            : 'جاري التحقق من الدفع'

  const message =
    view === 'paid'
      ? 'أكدت PayTabs عملية الدفع. يمكنك متابعة الطلب من حسابك.'
      : view === 'failed'
        ? 'لم يكتمل الدفع. لم يتم اعتماد العملية من صفحة العودة وحدها.'
        : view === 'cancelled'
          ? 'أُلغي الدفع قبل اكتماله.'
          : view === 'pending'
            ? error || 'بانتظار إشعار PayTabs. حدّث الصفحة بعد قليل إذا تأخر التأكيد.'
            : 'نراجع حالة الدفع من الخادم، وليس من صفحة العودة فقط.'

  return (
    <div dir="rtl" className="min-h-screen bg-slate-50">
      <div className="mx-auto w-full max-w-md space-y-4 px-4 py-10 text-center">
        <BrandLogo size="auth" to="/" className="justify-center" />
        <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
        <p className="text-sm text-slate-600">{message}</p>
        <Link to="/" className="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">
          العودة للرئيسية
        </Link>
      </div>
    </div>
  )
}
