import { Link } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerQuoteRequests } from '../../services/quoteRequests'
import { formatMoney } from '../../utils/catalog'
import {
  formatQuoteDate,
  quoteRequestSourceLabel,
  quoteRequestStatusLabel,
} from '../../utils/quoteRequests'

export function CustomerQuoteRequestsPage() {
  const { state, reload } = useAsyncData(() => getCustomerQuoteRequests({ per_page: 50 }))

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل طلبات التسعير..." />
  }

  if (state.status === 'error') {
    return (
      <CatalogErrorState message={`تعذر تحميل الطلبات. ${state.message}`} onRetry={() => void reload()} />
    )
  }

  const items = state.data.items ?? []

  return (
    <section className="space-y-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold text-[#111318]">طلبات التسعير</h1>
          <p className="text-sm text-slate-600">تابع حالة طلبات عروض الأسعار والردود من الفريق.</p>
        </div>
        <Link
          to="/request-quote?source_type=SERVICE&title=طلب%20تسعير"
          className="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#315CFF] px-4 text-sm font-medium text-white"
        >
          طلب تسعير جديد
        </Link>
      </header>

      {items.length === 0 ? (
        <CatalogEmptyState
          title="لا توجد طلبات تسعير حتى الآن."
          description="يمكنك طلب عرض سعر من صفحة الخدمات أو الباقات عند ظهور «طلب تسعير»."
          actions={[
            { to: '/services', label: 'الخدمات', variant: 'primary' },
            { to: '/packages', label: 'الباقات', variant: 'secondary' },
          ]}
        />
      ) : (
        <ul className="grid gap-4">
          {items.map((item) => (
            <li key={item.id} className="min-w-0 rounded-xl border border-slate-200 bg-white p-5">
              <div className="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 space-y-2">
                  <p className="font-mono text-xs text-slate-500" dir="ltr">
                    {item.reference}
                  </p>
                  <h2 className="font-semibold text-[#111318]">{item.title}</h2>
                  <p className="text-sm text-slate-600">
                    {quoteRequestStatusLabel(String(item.status))} ·{' '}
                    {quoteRequestSourceLabel(String(item.source_type))}
                  </p>
                  <p className="text-sm text-slate-500">
                    مطلوب: {formatQuoteDate(item.required_date)} · أُنشئ {formatQuoteDate(item.created_at)}
                  </p>
                  {item.budget_min != null || item.budget_max != null ? (
                    <p className="text-sm text-slate-600">
                      الميزانية:{' '}
                      {item.budget_min != null ? formatMoney(item.budget_min) : '—'} —{' '}
                      {item.budget_max != null ? formatMoney(item.budget_max) : '—'}
                    </p>
                  ) : null}
                </div>
                <Link
                  to={`/dashboard/quote-requests/${item.id}`}
                  className="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  عرض التفاصيل
                </Link>
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
