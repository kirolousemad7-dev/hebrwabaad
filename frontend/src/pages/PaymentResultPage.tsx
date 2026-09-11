import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { BrandLogo } from '../components/brand/BrandLogo'
import { getPublicPaymentStatus } from '../services/printingQuotations'
import { describeApiError } from '../utils/errors'

const QUOTE_TOKEN_KEY = 'hebr_quote_token'
const MAX_POLLS = 10
const POLL_MS = 2000

type ResultState = 'checking' | 'paid' | 'failed' | 'pending'

function mapStatus(status: string | null): ResultState {
  if (!status) return 'checking'
  const value = status.toUpperCase()
  if (value === 'PAID') return 'paid'
  if (['FAILED', 'CANCELLED', 'REJECTED'].includes(value)) return 'failed'
  return 'pending'
}

export function PaymentResultPage() {
  const [params] = useSearchParams()
  const paymentId = Number(params.get('payment_id') || params.get('payment') || 0)
  const tokenFromQuery = params.get('token')
  const [token] = useState(() => {
    if (tokenFromQuery) return tokenFromQuery
    try {
      return sessionStorage.getItem(QUOTE_TOKEN_KEY)
    } catch {
      return null
    }
  })
  const [state, setState] = useState<ResultState>('checking')
  const [polls, setPolls] = useState(0)
  const [error, setError] = useState<string | null>(null)

  const canPoll = useMemo(
    () => Number.isFinite(paymentId) && paymentId > 0 && Boolean(token),
    [paymentId, token],
  )

  useEffect(() => {
    if (!canPoll || !token) {
      setState('failed')
      setError('تعذر التحقق من الدفعة. افتح رابط العرض ثم أعد المحاولة.')
      return
    }

    let cancelled = false
    let attempts = 0
    let timer: number | undefined

    async function poll() {
      attempts += 1
      setPolls(attempts)
      try {
        const payload = await getPublicPaymentStatus(paymentId, token as string)
        if (cancelled) return
        const next = mapStatus(payload.status)
        if (next === 'paid' || next === 'failed') {
          setState(next)
          return
        }
        if (attempts >= MAX_POLLS) {
          setState('pending')
          return
        }
        setState('checking')
        timer = window.setTimeout(() => void poll(), POLL_MS)
      } catch (caught) {
        if (cancelled) return
        if (attempts >= MAX_POLLS) {
          setState('failed')
          setError(describeApiError(caught, 'تعذر التأكيد'))
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
  }, [canPoll, paymentId, token])

  const title =
    state === 'paid' ? 'تم الدفع' : state === 'failed' ? 'تعذر التأكيد' : state === 'pending' ? 'جارٍ التحقق' : 'جارٍ التحقق'

  const message =
    state === 'paid'
      ? 'تم تأكيد الدفع بنجاح. يمكنك متابعة حالة الطلب من رابط التتبع.'
      : state === 'failed'
        ? error || 'لم نتمكن من تأكيد الدفع. إن خصم المبلغ من بطاقتك فتواصل مع الفريق.'
        : state === 'pending'
          ? 'ما زال التحقق قيد المعالجة. سنحدّث الحالة تلقائياً عند اكتمال التأكيد.'
          : `جارٍ التحقق من حالة الدفع… (${polls}/${MAX_POLLS})`

  return (
    <div dir="rtl" className="min-h-screen bg-gradient-to-b from-brand-canvas via-amber-50/40 to-brand-canvas">
      <div className="mx-auto w-full max-w-md space-y-5 px-4 py-10 text-center">
        <BrandLogo size="auth" to="/" className="justify-center" />
        <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
        <p className="text-sm text-slate-700">{message}</p>
        {paymentId > 0 ? (
          <p className="font-mono text-xs text-slate-500" dir="ltr">
            payment #{paymentId}
          </p>
        ) : null}
        <div className="flex flex-col gap-2">
          <Link to="/" className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
            العودة للرئيسية
          </Link>
        </div>
      </div>
    </div>
  )
}

export const PAYMENT_QUOTE_TOKEN_KEY = QUOTE_TOKEN_KEY
