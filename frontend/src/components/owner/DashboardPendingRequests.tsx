import { Link } from 'react-router-dom'
import type { OwnerDashboardPendingRequest } from '../../types/api'
import { PRINTING_PRICING_LABELS, PRINTING_STATUS_LABELS } from '../../utils/printingRequest'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import { DashboardEmptyState } from './DashboardSection'

type DashboardPendingRequestsProps = {
  items: OwnerDashboardPendingRequest[]
}

export function DashboardPendingRequests({ items }: DashboardPendingRequestsProps) {
  if (items.length === 0) {
    return (
      <DashboardEmptyState
        title="لا توجد طلبات معلّقة"
        description="كل طلبات الطباعة الحالية لها تسعير أو لا توجد طلبات بعد."
      />
    )
  }

  return (
    <ul className="space-y-3">
      {items.map((item) => {
        const pricingLabel = item.pricing_type
          ? PRINTING_PRICING_LABELS[item.pricing_type]
          : 'بانتظار التسعير'

        return (
          <li
            key={item.id}
            className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
          >
            <div className="min-w-0 space-y-1">
              <p className="font-medium">
                #{item.id} · {item.product_name}
              </p>
              <p className="text-sm text-slate-600">
                {item.customer?.name ?? 'عميل'}
                {item.customer?.email ? ` · ${item.customer.email}` : ''}
              </p>
              <p className="text-xs text-slate-500">
                {PRINTING_STATUS_LABELS[item.status] ?? item.status} · {pricingLabel} ·{' '}
                {formatDashboardDateTime(item.created_at)}
              </p>
            </div>
            <Link
              to={item.href}
              className="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
            >
              مراجعة
            </Link>
          </li>
        )
      })}
    </ul>
  )
}
