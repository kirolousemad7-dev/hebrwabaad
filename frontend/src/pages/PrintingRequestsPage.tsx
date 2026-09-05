import { FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { useAsyncData } from '../hooks/useAsyncData'
import { getAdminPrintingRequests, type AdminPrintingRequestFilters } from '../services/printingRequests'
import { formatMoney } from '../utils/catalog'
import { formatPrintingDate, PRINTING_PRICING_LABELS, PRINTING_STATUS_LABELS } from '../utils/printingRequest'

const fieldClass =
  'w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type PrintingRequestsListProps = {
  filters: AdminPrintingRequestFilters
}

function PrintingRequestsList({ filters }: PrintingRequestsListProps) {
  const { state, reload } = useAsyncData(() => getAdminPrintingRequests(filters))

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل طلبات الطباعة..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={`تعذر تحميل الطلبات. ${state.message}`} onRetry={() => void reload()} />
  }

  const requests = state.data

  if (requests.length === 0) {
    return (
      <CatalogEmptyState
        title="لا توجد طلبات مطابقة."
        description="غيّر التصفية أو انتظر طلبات جديدة من العملاء."
        actions={[]}
      />
    )
  }

  return (
    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-50 text-start text-slate-600">
          <tr>
            <th className="px-4 py-3 font-medium">الطلب</th>
            <th className="px-4 py-3 font-medium">العميل</th>
            <th className="px-4 py-3 font-medium">المنتج</th>
            <th className="px-4 py-3 font-medium">الكمية</th>
            <th className="px-4 py-3 font-medium">التسليم</th>
            <th className="px-4 py-3 font-medium">الحالة</th>
            <th className="px-4 py-3 font-medium">التسعير</th>
            <th className="px-4 py-3 font-medium">السعر</th>
            <th className="px-4 py-3 font-medium">أُنشئ</th>
            <th className="px-4 py-3 font-medium">إجراء</th>
          </tr>
        </thead>
        <tbody>
          {requests.map((request) => {
            const price =
              request.pricing_type === 'QUOTE_READY' && request.quoted_price
                ? formatMoney(request.quoted_price)
                : request.pricing_type === 'ESTIMATED' && request.estimated_price
                  ? formatMoney(request.estimated_price)
                  : '—'

            return (
              <tr key={request.id} className="border-t border-slate-100">
                <td className="whitespace-nowrap px-4 py-3">#{request.id}</td>
                <td className="px-4 py-3">
                  <p>{request.customer?.name ?? '—'}</p>
                  <p className="text-xs text-slate-500">{request.customer?.email}</p>
                </td>
                <td className="px-4 py-3">{request.product_name}</td>
                <td className="px-4 py-3">{request.quantity.toLocaleString('ar-SA')}</td>
                <td className="whitespace-nowrap px-4 py-3">{formatPrintingDate(request.required_date)}</td>
                <td className="px-4 py-3">{PRINTING_STATUS_LABELS[request.status] ?? request.status}</td>
                <td className="px-4 py-3">
                  {request.pricing_type ? PRINTING_PRICING_LABELS[request.pricing_type] : 'قيد التحديد'}
                </td>
                <td className="whitespace-nowrap px-4 py-3">{price}</td>
                <td className="whitespace-nowrap px-4 py-3">{formatPrintingDate(request.created_at)}</td>
                <td className="px-4 py-3">
                  <Link
                    to={`/printing-requests/${request.id}`}
                    className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                  >
                    مراجعة
                  </Link>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

export function PrintingRequestsPage() {
  const [status, setStatus] = useState('')
  const [pricingType, setPricingType] = useState('')
  const [query, setQuery] = useState('')
  const [appliedQuery, setAppliedQuery] = useState('')

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setAppliedQuery(query.trim())
  }

  const filterKey = `${status}|${pricingType}|${appliedQuery}`

  return (
    <section className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold">طلبات الطباعة</h1>
        <p className="text-sm text-slate-600">راجع الطلبات وعيّن سعراً تقديرياً أو أصدر عرض سعر. الدفع غير مضمّن هنا.</p>
      </header>

      <form
        onSubmit={handleSearch}
        className="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4"
      >
        <label className="space-y-1 text-sm">
          <span>بحث</span>
          <input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="عميل أو منتج"
            className={fieldClass}
          />
        </label>
        <label className="space-y-1 text-sm">
          <span>الحالة</span>
          <select value={status} onChange={(event) => setStatus(event.target.value)} className={fieldClass}>
            <option value="">الكل</option>
            <option value="PENDING">قيد المراجعة</option>
          </select>
        </label>
        <label className="space-y-1 text-sm">
          <span>التسعير</span>
          <select value={pricingType} onChange={(event) => setPricingType(event.target.value)} className={fieldClass}>
            <option value="">الكل</option>
            <option value="ESTIMATED">سعر تقديري</option>
            <option value="QUOTE_REQUIRED">طلب عرض سعر</option>
            <option value="QUOTE_READY">عرض سعر جاهز</option>
          </select>
        </label>
        <div className="flex items-end">
          <button
            type="submit"
            className="w-full rounded-md bg-slate-900 px-4 py-2.5 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            تطبيق البحث
          </button>
        </div>
      </form>

      <PrintingRequestsList
        key={filterKey}
        filters={{
          status: status || undefined,
          pricing_type: pricingType || undefined,
          q: appliedQuery || undefined,
        }}
      />
    </section>
  )
}
