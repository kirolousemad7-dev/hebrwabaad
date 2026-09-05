import { StatusBadge } from '../ui/StatusBadge'
import { Link } from 'react-router-dom'
import type { CustomerOrder } from '../../types/api'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import { customerOrderPath, formatOrderProgress } from '../../utils/orderTracking'
import { customerPayPath, formatPaymentAmount, orderNeedsPayment } from '../../utils/payments'

type CustomerOrderCardProps = {
  order: CustomerOrder
}

export function CustomerOrderCard({ order }: CustomerOrderCardProps) {
  return (
    <article className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-xs text-slate-500" dir="ltr">
            {order.reference}
          </p>
          <h3 className="font-semibold">{order.title}</h3>
        </div>
        <StatusBadge status={order.status} label={order.status_label} />
      </div>
      <p className="text-sm text-slate-600">التقدم: {formatOrderProgress(order.progress)}</p>
      <p className="text-sm text-slate-500">أُنشئ: {formatDashboardDateTime(order.created_at)}</p>
      {order.project ? <p className="text-sm text-slate-500">المشروع: {order.project.title}</p> : null}
      {orderNeedsPayment(order) ? (
        <p className="text-sm font-medium text-slate-800">
          مستحق: {formatPaymentAmount(order.payable?.amount ?? null, order.payable?.currency ?? 'SAR')}
        </p>
      ) : null}
      <div className="mt-auto flex flex-wrap gap-2">
        <Link
          to={customerOrderPath(order.id)}
          className="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          عرض التفاصيل
        </Link>
        {orderNeedsPayment(order) ? (
          <Link
            to={customerPayPath(order.id)}
            className="inline-flex rounded-lg bg-slate-900 px-3 py-2 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            ادفع الآن
          </Link>
        ) : null}
      </div>
    </article>
  )
}
