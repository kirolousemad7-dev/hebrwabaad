import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { downloadCustomerInvoicePdf, getCustomerInvoice, type Invoice } from '../../services/invoices'
import { describeApiError } from '../../utils/errors'

export function CustomerInvoiceDetailPage() {
  const { invoiceId } = useParams()
  const [invoice, setInvoice] = useState<Invoice | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!invoiceId) return
    void (async () => {
      setLoading(true)
      try {
        const response = await getCustomerInvoice(invoiceId)
        setInvoice(response.data)
      } catch (caught) {
        setError(describeApiError(caught, 'تعذر تحميل الفاتورة.'))
      } finally {
        setLoading(false)
      }
    })()
  }, [invoiceId])

  if (loading) return <DashboardPanelSkeleton label="جاري التحميل" />
  if (error || !invoice) return <DashboardErrorState message={error ?? 'غير موجودة'} onRetry={() => window.location.reload()} />

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-sm text-slate-500">
            <Link to="/dashboard/invoices" className="underline">
              فواتيري
            </Link>
          </p>
          <h1 className="text-2xl font-semibold">{invoice.number}</h1>
          <StatusBadge status={invoice.status} label={invoice.status_label ?? invoice.status} />
        </div>
        <button type="button" className="rounded-xl border px-3 py-2 text-sm" onClick={() => void downloadCustomerInvoicePdf(invoice.id)}>
          تحميل PDF
        </button>
      </header>

      <div className="rounded-2xl border bg-white p-4 text-sm">
        <p>
          الإجمالي: {invoice.total} {invoice.currency}
        </p>
        <p>
          المستحق: {invoice.amount_due} {invoice.currency}
        </p>
        <p>الاستحقاق: {invoice.due_date ?? '—'}</p>
        <p>ملاحظات: {invoice.notes || '—'}</p>
      </div>

      <div className="overflow-x-auto rounded-2xl border bg-white">
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
    </section>
  )
}
