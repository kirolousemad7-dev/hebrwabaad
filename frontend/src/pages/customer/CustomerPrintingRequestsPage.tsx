import { Link } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { PrintingPricingStatus } from '../../components/printing/PrintingPricingStatus'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerPrintingRequests } from '../../services/printingRequests'
import { formatMoney } from '../../utils/catalog'
import { formatPrintingDate, PRINTING_PRICING_LABELS, PRINTING_STATUS_LABELS } from '../../utils/printingRequest'

export function CustomerPrintingRequestsPage() {
  const { state, reload } = useAsyncData(getCustomerPrintingRequests)

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل طلبات الطباعة..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={`تعذر تحميل الطلبات. ${state.message}`} onRetry={() => void reload()} />
  }

  const requests = state.data

  return (
    <section className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold">طلبات الطباعة</h1>
        <p className="text-sm text-slate-600">تابع حالة التسعير وعروض السعر لطلباتك. الدفع غير مفعّل بعد.</p>
      </header>

      {requests.length === 0 ? (
        <CatalogEmptyState
          title="لا توجد طلبات طباعة بعد."
          description="يمكنك تخصيص منتج من كتالوج الطباعة وإرسال طلب جديد."
          actions={[{ to: '/printing-packaging', label: 'الطباعة والتغليف', variant: 'primary' }]}
        />
      ) : (
        <ul className="grid gap-4">
          {requests.map((request) => (
            <li key={request.id} className="min-w-0 rounded-xl border border-slate-200 bg-white p-5">
              <div className="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 space-y-2">
                  <p className="text-xs text-slate-500">طلب #{request.id}</p>
                  <h2 className="font-semibold">{request.product_name}</h2>
                  <p className="text-sm text-slate-600">
                    الحالة: {PRINTING_STATUS_LABELS[request.status] ?? request.status}
                    {request.pricing_type ? ` — ${PRINTING_PRICING_LABELS[request.pricing_type]}` : ''}
                  </p>
                  <p className="text-sm text-slate-500">
                    التسليم المطلوب: {formatPrintingDate(request.required_date)} · أُنشئ {formatPrintingDate(request.created_at)}
                  </p>
                  {request.pricing_type === 'QUOTE_READY' && request.quoted_price ? (
                    <p className="text-sm font-medium">عرض السعر: {formatMoney(request.quoted_price)}</p>
                  ) : request.pricing_type === 'ESTIMATED' && request.estimated_price ? (
                    <p className="text-sm font-medium">السعر التقديري: {formatMoney(request.estimated_price)}</p>
                  ) : null}
                </div>
                <Link
                  to={`/customer/printing-requests/${request.id}`}
                  className="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  عرض التفاصيل
                </Link>
              </div>
              <div className="mt-4">
                <PrintingPricingStatus request={request} />
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
