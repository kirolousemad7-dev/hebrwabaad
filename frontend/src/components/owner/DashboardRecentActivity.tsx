import { Link } from 'react-router-dom'
import type { OwnerDashboardActivityItem } from '../../types/api'
import {
  ACTIVITY_STATUS_LABELS,
  ACTIVITY_TYPE_LABELS,
  formatDashboardDateTime,
} from '../../utils/ownerDashboard'
import { DashboardEmptyState } from './DashboardSection'

type DashboardRecentActivityProps = {
  items: OwnerDashboardActivityItem[]
}

export function DashboardRecentActivity({ items }: DashboardRecentActivityProps) {
  if (items.length === 0) {
    return (
      <DashboardEmptyState
        title="لا يوجد نشاط حديث"
        description="ستظهر هنا طلبات الطباعة، العملاء الجدد، وعروض الأسعار عند حدوثها."
      />
    )
  }

  return (
    <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
      {items.map((item) => {
        const typeLabel = ACTIVITY_TYPE_LABELS[item.type] ?? item.type
        const statusLabel = ACTIVITY_STATUS_LABELS[item.status] ?? item.status
        const body = (
          <>
            <div className="flex min-w-0 flex-wrap items-start justify-between gap-2">
              <p className="font-medium">{typeLabel}</p>
              <p className="text-xs text-slate-500">{formatDashboardDateTime(item.occurred_at)}</p>
            </div>
            <p className="mt-1 text-sm text-slate-700">{item.title}</p>
            <p className="mt-1 text-xs text-slate-500">
              {item.actor
                ? `بواسطة ${item.actor.name}`
                : item.type === 'supplier_added'
                  ? 'من دليل الموردين'
                  : 'حدث تلقائي'}
              {item.entity.label ? ` · ${item.entity.label}` : ''}
              {` · ${statusLabel}`}
            </p>
          </>
        )

        return (
          <li key={item.id}>
            {item.entity.href ? (
              <Link
                to={item.entity.href}
                className="block px-4 py-3 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
              >
                {body}
              </Link>
            ) : (
              <div className="px-4 py-3">{body}</div>
            )}
          </li>
        )
      })}
    </ul>
  )
}
