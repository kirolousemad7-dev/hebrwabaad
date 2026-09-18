import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { getOwnerInvoices, type Invoice, type InvoiceListFilters } from '../../services/invoices'
import { describeApiError } from '../../utils/errors'

const STATUS_FILTERS = [
  { value: '', label: 'كل الحالات' },
  { value: 'DRAFT', label: 'مسودة' },
  { value: 'ISSUED', label: 'صادرة' },
  { value: 'SENT', label: 'مُرسلة' },
  { value: 'PARTIALLY_PAID', label: 'مدفوعة جزئياً' },
  { value: 'PAID', label: 'مدفوعة' },
  { value: 'OVERDUE', label: 'متأخرة' },
  { value: 'CANCELLED', label: 'ملغاة' },
  { value: 'VOID', label: 'ملغاة نهائياً' },
]

export function OwnerInvoicesPage() {
  const [filters, setFilters] = useState<InvoiceListFilters>({ q: '', status: '', from: '', to: '', page: 1 })
  const [items, setItems] = useState<Invoice[]>([])
  const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getOwnerInvoices(filters)
      setItems(response.data.items)
      setMeta({
        current_page: response.data.meta.current_page,
        last_page: response.data.meta.last_page,
        total: response.data.meta.total,
      })
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الفواتير.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    const handle = window.setTimeout(() => {
      void load()
    }, filters.q ? 250 : 0)
    return () => window.clearTimeout(handle)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.q, filters.status, filters.from, filters.to, filters.page])

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">الفواتير</h1>
          <p className="text-sm text-slate-600">إصدار وإدارة فواتير العملاء التجارية.</p>
        </div>
        <Link to="/owner/invoices/new" className="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white">
          فاتورة جديدة
        </Link>
      </header>

      <div className="flex flex-wrap gap-2">
        <input
          className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
          placeholder="بحث برقم الفاتورة أو العميل"
          value={filters.q ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, q: e.target.value, page: 1 }))}
        />
        <select
          className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
          value={filters.status ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, status: e.target.value, page: 1 }))}
        >
          {STATUS_FILTERS.map((opt) => (
            <option key={opt.value || 'all'} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
        <input
          type="date"
          className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
          value={filters.from ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, from: e.target.value, page: 1 }))}
        />
        <input
          type="date"
          className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
          value={filters.to ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, to: e.target.value, page: 1 }))}
        />
      </div>

      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {loading ? <DashboardPanelSkeleton label="جاري التحميل" /> : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا توجد فواتير" description="لم يتم إصدار فواتير بعد." />
      ) : null}

      {!loading && items.length > 0 ? (
        <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-slate-600">
              <tr>
                <th className="px-4 py-3 text-right font-medium">الرقم</th>
                <th className="px-4 py-3 text-right font-medium">العميل</th>
                <th className="px-4 py-3 text-right font-medium">الحالة</th>
                <th className="px-4 py-3 text-right font-medium">الإجمالي</th>
                <th className="px-4 py-3 text-right font-medium">المستحق</th>
                <th className="px-4 py-3 text-right font-medium">الاستحقاق</th>
              </tr>
            </thead>
            <tbody>
              {items.map((invoice) => (
                <tr key={invoice.id} className="border-t border-slate-100">
                  <td className="px-4 py-3">
                    <Link className="font-medium text-slate-900 underline" to={`/owner/invoices/${invoice.id}`}>
                      {invoice.number}
                    </Link>
                  </td>
                  <td className="px-4 py-3">{invoice.customer?.name ?? '—'}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={invoice.status} label={invoice.status_label ?? invoice.status} />
                  </td>
                  <td className="px-4 py-3">
                    {invoice.total} {invoice.currency}
                  </td>
                  <td className="px-4 py-3">
                    {invoice.amount_due} {invoice.currency}
                  </td>
                  <td className="px-4 py-3">{invoice.due_date ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {meta && meta.last_page > 1 ? (
        <FeedbackBanner kind="info">
          صفحة {meta.current_page} من {meta.last_page} — الإجمالي {meta.total}
          <button
            type="button"
            className="ms-3 underline"
            disabled={meta.current_page >= meta.last_page}
            onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
          >
            التالي
          </button>
        </FeedbackBanner>
      ) : null}
    </section>
  )
}
