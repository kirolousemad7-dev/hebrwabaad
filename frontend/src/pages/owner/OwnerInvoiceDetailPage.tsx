import { FormEvent, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import {
  cancelOwnerInvoice,
  downloadOwnerInvoicePdf,
  getOwnerInvoice,
  issueOwnerInvoice,
  recordOwnerInvoicePayment,
  sendOwnerInvoice,
  voidOwnerInvoice,
  type Invoice,
} from '../../services/invoices'
import { describeApiError } from '../../utils/errors'

export function OwnerInvoiceDetailPage() {
  const { invoiceId } = useParams()
  const navigate = useNavigate()
  const [invoice, setInvoice] = useState<Invoice | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [paymentAmount, setPaymentAmount] = useState('')
  const [paymentRef, setPaymentRef] = useState('')

  async function load() {
    if (!invoiceId) return
    setLoading(true)
    setError(null)
    try {
      const response = await getOwnerInvoice(invoiceId)
      setInvoice(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الفاتورة.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [invoiceId])

  async function run(action: () => Promise<unknown>) {
    setBusy(true)
    setError(null)
    try {
      await action()
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنفيذ العملية.'))
    } finally {
      setBusy(false)
    }
  }

  async function onPayment(event: FormEvent) {
    event.preventDefault()
    if (!invoiceId) return
    await run(() =>
      recordOwnerInvoicePayment(invoiceId, {
        amount: paymentAmount,
        payment_method: 'BANK_TRANSFER',
        reference_number: paymentRef || undefined,
      }),
    )
    setPaymentAmount('')
    setPaymentRef('')
  }

  if (loading) return <DashboardPanelSkeleton label="جاري التحميل" />
  if (error && !invoice) return <DashboardErrorState message={error} onRetry={() => void load()} />
  if (!invoice) return null

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-sm text-slate-500">
            <Link to="/owner/invoices" className="underline">
              الفواتير
            </Link>
          </p>
          <h1 className="text-2xl font-semibold">{invoice.number}</h1>
          <div className="mt-2 flex items-center gap-2">
            <StatusBadge status={invoice.status} label={invoice.status_label ?? invoice.status} />
            <span className="text-sm text-slate-600">
              {invoice.total} {invoice.currency} — مستحق {invoice.amount_due}
            </span>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          {invoice.status === 'DRAFT' ? (
            <>
              <Link to={`/owner/invoices/${invoice.id}/edit`} className="rounded-xl border px-3 py-2 text-sm">
                تعديل
              </Link>
              <button type="button" disabled={busy} className="rounded-xl bg-slate-900 px-3 py-2 text-sm text-white" onClick={() => void run(() => issueOwnerInvoice(invoice.id))}>
                إصدار
              </button>
            </>
          ) : null}
          {['ISSUED', 'SENT', 'OVERDUE', 'PARTIALLY_PAID'].includes(invoice.status) ? (
            <button type="button" disabled={busy} className="rounded-xl border px-3 py-2 text-sm" onClick={() => void run(() => sendOwnerInvoice(invoice.id))}>
              إرسال للعميل
            </button>
          ) : null}
          <button type="button" className="rounded-xl border px-3 py-2 text-sm" onClick={() => void downloadOwnerInvoicePdf(invoice.id)}>
            PDF
          </button>
          {invoice.status !== 'PAID' && invoice.status !== 'VOID' ? (
            <button type="button" disabled={busy} className="rounded-xl border px-3 py-2 text-sm text-amber-800" onClick={() => void run(() => cancelOwnerInvoice(invoice.id))}>
              إلغاء
            </button>
          ) : null}
          <button type="button" disabled={busy} className="rounded-xl border px-3 py-2 text-sm text-rose-700" onClick={() => void run(() => voidOwnerInvoice(invoice.id).then(() => navigate('/owner/invoices')))}>
            إبطال
          </button>
        </div>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
          <h2 className="mb-2 font-semibold">العميل</h2>
          <p>{invoice.customer?.name}</p>
          <p className="text-slate-600">{invoice.customer?.email}</p>
          <p className="mt-2">الاستحقاق: {invoice.due_date ?? '—'}</p>
          <p>ملاحظات: {invoice.notes || '—'}</p>
          <p className="text-amber-800">داخلي: {invoice.internal_notes || '—'}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
          <h2 className="mb-2 font-semibold">المبالغ</h2>
          <p>الإجمالي الفرعي: {invoice.subtotal}</p>
          <p>الخصم: {invoice.discount_amount}</p>
          <p>الضريبة: {invoice.tax_amount}</p>
          <p>المدفوع: {invoice.amount_paid}</p>
          <p className="font-semibold">المستحق: {invoice.amount_due}</p>
        </div>
      </div>

      <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table className="min-w-full text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-right">الوصف</th>
              <th className="px-4 py-3 text-right">الكمية</th>
              <th className="px-4 py-3 text-right">السعر</th>
              <th className="px-4 py-3 text-right">الإجمالي</th>
            </tr>
          </thead>
          <tbody>
            {invoice.items.map((item, index) => (
              <tr key={item.id ?? index} className="border-t">
                <td className="px-4 py-3">{item.description}</td>
                <td className="px-4 py-3">{item.quantity}</td>
                <td className="px-4 py-3">{item.unit_price}</td>
                <td className="px-4 py-3">{item.line_total}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {['ISSUED', 'SENT', 'OVERDUE', 'PARTIALLY_PAID'].includes(invoice.status) ? (
        <form onSubmit={onPayment} className="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
          <h2 className="font-semibold">تسجيل دفعة</h2>
          <div className="flex flex-wrap gap-2">
            <input className="rounded-xl border px-3 py-2 text-sm" placeholder="المبلغ" value={paymentAmount} onChange={(e) => setPaymentAmount(e.target.value)} required />
            <input className="rounded-xl border px-3 py-2 text-sm" placeholder="مرجع التحويل" value={paymentRef} onChange={(e) => setPaymentRef(e.target.value)} />
            <button type="submit" disabled={busy} className="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white">
              حفظ الدفعة
            </button>
          </div>
        </form>
      ) : null}

      <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm space-y-2">
        <h2 className="font-semibold">سجل المدفوعات</h2>
        {(invoice.payments ?? []).length === 0 ? <p className="text-slate-500">لا توجد مدفوعات</p> : null}
        {(invoice.payments ?? []).map((payment) => (
          <p key={payment.id}>
            {payment.amount} {payment.currency} — {payment.payment_method} — {payment.paid_at ?? ''}
          </p>
        ))}
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm space-y-2">
        <h2 className="font-semibold">سجل الأحداث</h2>
        {(invoice.events ?? []).map((event) => (
          <p key={event.id}>
            {event.event} — {event.created_at}
          </p>
        ))}
      </div>
    </section>
  )
}
