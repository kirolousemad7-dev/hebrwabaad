import { FormEvent, useEffect, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerOrder } from '../../services/orders'
import {
  createCustomerPayment,
  getCustomerPayment,
  getCustomerPaymentSettings,
  startCustomerCardPayment,
  submitCustomerManualTransfer,
} from '../../services/payments'
import type { CustomerPayment, PaymentMethod } from '../../types/api'
import { describeApiError } from '../../utils/errors'
import { describeOrderLoadError } from '../../utils/orderTracking'
import {
  PAYMENT_COPY,
  checkoutReturnView,
  enabledPaymentMethods,
  formatPaymentAmount,
  hasAnyPaymentMethod,
  isManualPaymentMethod,
  isSafeCheckoutRedirect,
  payableUnavailableCopy,
  selectedPaymentMethod,
} from '../../utils/payments'

export function CustomerPayPage() {
  const { orderId } = useParams()
  const [searchParams] = useSearchParams()
  const toast = useToast()
  const numericId = Number(orderId)
  const { state, reload } = useAsyncData(() => getCustomerOrder(numericId))
  const settingsState = useAsyncData(getCustomerPaymentSettings)
  const [method, setMethod] = useState<PaymentMethod | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [reference, setReference] = useState('')
  const [payerName, setPayerName] = useState('')
  const [localPayment, setLocalPayment] = useState<CustomerPayment | null>(null)
  const [retrying, setRetrying] = useState(false)

  const checkoutFlag = searchParams.get('checkout')
  const paymentIdFromQuery = Number(searchParams.get('payment'))

  useEffect(() => {
    if (!checkoutFlag) {
      return
    }

    let cancelled = false
    let attempts = 0
    let timer: number | undefined

    const poll = async () => {
      await reload()

      const paymentId = Number.isInteger(paymentIdFromQuery) && paymentIdFromQuery > 0 ? paymentIdFromQuery : null
      if (paymentId) {
        try {
          const fresh = await getCustomerPayment(paymentId)
          if (!cancelled) {
            setLocalPayment(fresh.data)
            setRetrying(false)
          }
          if (fresh.data.status === 'PAID' || fresh.data.status === 'FAILED' || fresh.data.status === 'CANCELLED') {
            return
          }
        } catch {
          if (!cancelled) {
            setError(PAYMENT_COPY.isolation)
          }
          return
        }
      }

      attempts += 1
      if (!cancelled && attempts < 6 && (checkoutFlag === 'success' || checkoutFlag === 'return')) {
        timer = window.setTimeout(() => {
          void poll()
        }, 2000)
      }
    }

    void poll()

    return () => {
      cancelled = true
      if (timer !== undefined) {
        window.clearTimeout(timer)
      }
    }
  }, [checkoutFlag, paymentIdFromQuery, reload])

  if (!Number.isInteger(numericId) || numericId <= 0) {
    return <CatalogErrorState message="تعذر تحميل طلب الدفع." onRetry={() => undefined} />
  }

  if (state.status === 'loading' || settingsState.state.status === 'loading') {
    return <CatalogSkeleton variant="list" label={PAYMENT_COPY.loading} />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={describeOrderLoadError(state.message)} onRetry={() => void reload()} />
  }

  if (settingsState.state.status === 'error') {
    return (
      <CatalogErrorState
        message={settingsState.state.message}
        onRetry={() => void settingsState.reload()}
      />
    )
  }

  const order = state.data
  const settings = settingsState.state.data
  const payment = localPayment ?? order.latest_payment ?? null
  const view = checkoutReturnView(checkoutFlag, payment)
  const amount = order.payable?.amount ?? payment?.amount
  const currency = order.payable?.currency ?? payment?.currency ?? 'SAR'
  const enabled = enabledPaymentMethods(settings)
  const resolvedMethod = selectedPaymentMethod(method, enabled)
  const showChooser =
    (view === 'idle' || retrying) &&
    Boolean(order.payable?.available) &&
    payment?.status !== 'PENDING' &&
    view !== 'verifying'
  const manualPending = isManualPaymentMethod(payment?.payment_method) && payment?.status === 'PENDING'
  const manualAccount =
    payment?.payment_method === 'BANK_TRANSFER'
      ? {
          title: PAYMENT_COPY.bankTransfer,
          rows: [
            { label: 'اسم البنك', value: settings.bank_transfer.bank_name },
            { label: 'اسم صاحب الحساب', value: settings.bank_transfer.account_name },
            { label: 'رقم الحساب', value: settings.bank_transfer.account_number },
            { label: 'IBAN', value: settings.bank_transfer.iban },
            { label: 'SWIFT/BIC', value: settings.bank_transfer.swift },
            { label: 'الفرع', value: settings.bank_transfer.branch },
          ],
          instructions: settings.bank_transfer.instructions,
          notes: settings.bank_transfer.notes,
          referenceLabel: 'رقم الحوالة',
        }
      : {
          title: PAYMENT_COPY.instapay,
          rows: [
            { label: 'اسم الحساب', value: settings.instapay.account_name },
            { label: 'البنك', value: settings.instapay.bank_name },
            { label: 'رقم الحساب / IBAN', value: settings.instapay.account_number },
            { label: 'معرّف إنستاباي', value: settings.instapay.handle },
            { label: 'رقم الهاتف المرتبط', value: settings.instapay.phone },
          ],
          instructions: settings.instapay.instructions,
          notes: settings.instapay.notes,
          referenceLabel: 'رقم العملية',
        }

  async function startCard() {
    if (busy) {
      return
    }

    setBusy(true)
    setError(null)
    setRetrying(false)

    try {
      const created =
        payment?.payment_method === 'CARD' && payment.status !== 'PAID'
          ? await startCustomerCardPayment(payment.id)
          : await createCustomerPayment({ order_id: order.id, method: 'CARD' })
      setLocalPayment(created.data)

      if (!isSafeCheckoutRedirect(created.data.checkout_url)) {
        setError(PAYMENT_COPY.gatewayMissing)
        return
      }

      window.location.assign(created.data.checkout_url as string)
    } catch (caught) {
      setError(describeApiError(caught, PAYMENT_COPY.failed))
    } finally {
      setBusy(false)
    }
  }

  async function startManual(selected: Extract<PaymentMethod, 'INSTAPAY' | 'BANK_TRANSFER'>) {
    if (busy) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      const created = await createCustomerPayment({ order_id: order.id, method: selected })
      setLocalPayment(created.data)
      setMethod(selected)
      setReference('')
    } catch (caught) {
      setError(describeApiError(caught, PAYMENT_COPY.failed))
    } finally {
      setBusy(false)
    }
  }

  async function submitManualTransfer(event: FormEvent) {
    event.preventDefault()

    if (busy || !payment) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      const submitted = await submitCustomerManualTransfer(payment.id, {
        reference_number: reference.trim(),
        payer_name: payerName.trim() || undefined,
      })
      setLocalPayment(submitted.data)
      toast.success(PAYMENT_COPY.pendingVerification)
    } catch (caught) {
      setError(describeApiError(caught, PAYMENT_COPY.failed))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <p className="text-xs text-slate-500" dir="ltr">
          {order.reference}
        </p>
        <h1 className="text-2xl font-semibold">دفع الطلب</h1>
        <p className="text-sm text-slate-600">{order.title}</p>
        {order.package ? (
          <p className="text-xs text-slate-500">
            الباقة: {order.package.name}
            {order.package_tier ? ` · المستوى: ${order.package_tier.name}` : ''}
          </p>
        ) : null}
      </header>

      <article className="rounded-2xl border border-slate-200 bg-white p-5">
        <p className="text-xs text-slate-500">{PAYMENT_COPY.amountDue}</p>
        <p className="mt-1 text-3xl font-semibold tabular-nums">
          {formatPaymentAmount(amount ?? null, currency)}
        </p>
        {payment ? <StatusBadge status={payment.status} label={payment.status_label} /> : null}
      </article>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {busy ? <FeedbackBanner kind="info">{PAYMENT_COPY.processing}</FeedbackBanner> : null}
      {!busy && view === 'verifying' ? <FeedbackBanner kind="info">{PAYMENT_COPY.verifying}</FeedbackBanner> : null}
      {!busy && view === 'processing' && checkoutFlag === null ? (
        <FeedbackBanner kind="info">{PAYMENT_COPY.processing}</FeedbackBanner>
      ) : null}

      {view === 'success' ? <FeedbackBanner kind="success">{PAYMENT_COPY.success}</FeedbackBanner> : null}
      {view === 'failed' ? <FeedbackBanner kind="error">{payment?.failure_reason ?? PAYMENT_COPY.failed}</FeedbackBanner> : null}
      {view === 'cancelled' ? <FeedbackBanner kind="warning">{PAYMENT_COPY.cancelled}</FeedbackBanner> : null}
      {view === 'pending_verification' ? (
        <FeedbackBanner kind="info">{PAYMENT_COPY.pendingVerification}</FeedbackBanner>
      ) : null}
      {view === 'rejected' ? (
        <FeedbackBanner kind="error">{payment?.failure_reason ?? PAYMENT_COPY.rejected}</FeedbackBanner>
      ) : null}

      {!order.payable?.available && view !== 'success' && view !== 'pending_verification' ? (
        <FeedbackBanner kind="info">{payableUnavailableCopy(order.payable?.reason)}</FeedbackBanner>
      ) : null}

      {showChooser && !hasAnyPaymentMethod(enabled) ? (
        <FeedbackBanner kind="warning">{PAYMENT_COPY.noMethods}</FeedbackBanner>
      ) : null}

      {showChooser ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {enabled.card ? (
            <button
              type="button"
              disabled={busy}
              onClick={() => {
                setMethod('CARD')
                void startCard()
              }}
              className="min-h-28 rounded-2xl border border-slate-200 bg-white p-5 text-start shadow-sm hover:border-slate-300 disabled:opacity-50"
            >
              <div className="flex items-center justify-between gap-3">
                <p className="font-semibold">{PAYMENT_COPY.card}</p>
                <span className="rounded-md bg-slate-900 px-2 py-1 text-[10px] font-semibold tracking-wide text-white">
                  VISA
                </span>
              </div>
              <p className="mt-2 text-sm leading-7 text-slate-600">{PAYMENT_COPY.cardHint}</p>
              <p className="mt-3 text-sm font-medium text-slate-900">{PAYMENT_COPY.payNow}</p>
            </button>
          ) : null}
          {enabled.instapay ? (
            <button
              type="button"
              disabled={busy}
              onClick={() => void startManual('INSTAPAY')}
              className="min-h-28 rounded-2xl border border-slate-200 bg-white p-5 text-start shadow-sm hover:border-slate-300 disabled:opacity-50"
            >
              <p className="font-semibold">{PAYMENT_COPY.instapay}</p>
              <p className="mt-2 text-sm leading-7 text-slate-600">{PAYMENT_COPY.instapayHint}</p>
            </button>
          ) : null}
          {enabled.bank_transfer ? (
            <button
              type="button"
              disabled={busy}
              onClick={() => void startManual('BANK_TRANSFER')}
              className="min-h-28 rounded-2xl border border-slate-200 bg-white p-5 text-start shadow-sm hover:border-slate-300 disabled:opacity-50"
            >
              <p className="font-semibold">{PAYMENT_COPY.bankTransfer}</p>
              <p className="mt-2 text-sm leading-7 text-slate-600">{PAYMENT_COPY.bankTransferHint}</p>
            </button>
          ) : null}
        </div>
      ) : null}

      {manualPending ? (
        <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold">تعليمات {manualAccount.title}</h2>
          <dl className="grid gap-3 text-sm sm:grid-cols-2">
            {manualAccount.rows
              .filter((row) => Boolean(row.value))
              .map((row) => (
                <div key={row.label}>
                  <dt className="text-xs text-slate-500">{row.label}</dt>
                  <dd dir="auto">{row.value}</dd>
                </div>
              ))}
          </dl>
          <p className="text-sm leading-7 text-slate-600">{manualAccount.instructions}</p>
          {manualAccount.notes ? (
            <p className="text-xs leading-6 text-slate-500">{manualAccount.notes}</p>
          ) : null}
          <form className="space-y-3" onSubmit={(event) => void submitManualTransfer(event)}>
            <label className="block space-y-1 text-sm">
              <span>{manualAccount.referenceLabel}</span>
              <input
                required
                value={reference}
                onChange={(event) => setReference(event.target.value)}
                className="w-full rounded-xl border border-slate-300 px-3 py-2"
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span>اسم المحوّل (اختياري)</span>
              <input
                value={payerName}
                onChange={(event) => setPayerName(event.target.value)}
                className="w-full rounded-xl border border-slate-300 px-3 py-2"
              />
            </label>
            <button
              type="submit"
              disabled={busy || reference.trim() === ''}
              className="min-h-11 rounded-xl bg-slate-900 px-4 py-2 text-sm text-white disabled:opacity-50"
            >
              إرسال للمراجعة
            </button>
          </form>
        </div>
      ) : null}

      {(view === 'failed' || view === 'cancelled' || view === 'rejected') && order.payable?.available ? (
        <button
          type="button"
          className="min-h-11 rounded-xl border border-slate-300 px-4 py-2 text-sm"
          onClick={() => {
            setLocalPayment(null)
            setMethod(resolvedMethod)
            setRetrying(true)
            setError(null)
            void reload()
          }}
        >
          محاولة دفع جديدة
        </button>
      ) : null}

      <Link to={`/dashboard/orders/${order.id}`} className="inline-block text-sm underline">
        العودة إلى الطلب
      </Link>
    </section>
  )
}
