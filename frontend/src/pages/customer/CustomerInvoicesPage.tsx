import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { getCustomerInvoices, type Invoice } from '../../services/invoices'
import { describeApiError } from '../../utils/errors'

export function CustomerInvoicesPage() {
  const [items, setItems] = useState<Invoice[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    void (async () => {
      setLoading(true)
      try {
        const response = await getCustomerInvoices()
        setItems(response.data.items)
      } catch (caught) {
        setError(describeApiError(caught, 'تعذر تحميل الفواتير.'))
      } finally {
        setLoading(false)
      }
    })()
  }, [])

  if (loading) return <DashboardPanelSkeleton label="جاري التحميل" />
  if (error) return <DashboardErrorState message={error} onRetry={() => window.location.reload()} />
  if (items.length === 0) return <DashboardEmptyState title="لا توجد فواتير" description="لم يتم إصدار فواتير بعد." />

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-semibold">فواتيري</h1>
      <div className="overflow-x-auto rounded-2xl border bg-white">
        <table className="min-w-full text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-right">الرقم</th>
              <th className="px-4 py-3 text-right">الحالة</th>
              <th className="px-4 py-3 text-right">الإجمالي</th>
              <th className="px-4 py-3 text-right">المستحق</th>
            </tr>
          </thead>
          <tbody>
            {items.map((invoice) => (
              <tr key={invoice.id} className="border-t">
                <td className="px-4 py-3">
                  <Link className="underline" to={`/dashboard/invoices/${invoice.id}`}>
                    {invoice.number}
                  </Link>
                </td>
                <td className="px-4 py-3">
                  <StatusBadge status={invoice.status} label={invoice.status_label ?? invoice.status} />
                </td>
                <td className="px-4 py-3">
                  {invoice.total} {invoice.currency}
                </td>
                <td className="px-4 py-3">
                  {invoice.amount_due} {invoice.currency}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
