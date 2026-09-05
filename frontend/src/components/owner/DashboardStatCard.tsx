import type { OwnerDashboardMetric } from '../../types/api'
import { UNAVAILABLE_METRIC_COPY } from '../../utils/ownerDashboard'

type StatIconName =
  | 'revenue'
  | 'orders'
  | 'customers'
  | 'projects'
  | 'employees'
  | 'suppliers'
  | 'leads'
  | 'pending'

type DashboardStatCardProps = {
  title: string
  icon: StatIconName
  metric: OwnerDashboardMetric
  emptyLabel: string
  secondaryLabel?: string | null
}

function StatIcon({ name }: { name: StatIconName }) {
  const className = 'h-5 w-5'

  if (name === 'revenue') {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path
          d="M4 19h16M7 16V9m5 7V6m5 10v-4"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinecap="round"
        />
      </svg>
    )
  }

  if (name === 'orders') {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path
          d="M7 4h10l1 4H6zm-1 4 1 12h10l1-12M9 12h6"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinejoin="round"
        />
      </svg>
    )
  }

  if (name === 'customers') {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path
          d="M9 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm9-1a2.5 2.5 0 1 0-2.5-2.5A2.5 2.5 0 0 0 18 10zM4 19a5 5 0 0 1 10 0m4-1a4 4 0 0 1 4 0"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinecap="round"
        />
      </svg>
    )
  }

  if (name === 'projects') {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path
          d="M4 7h16v12H4zm4-3h8l1 3H7z"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinejoin="round"
        />
      </svg>
    )
  }

  if (name === 'employees') {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path
          d="M12 12a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm-7 8a5 5 0 0 1 14 0M18 8h3m-1.5-1.5v3"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinecap="round"
        />
      </svg>
    )
  }

  if (name === 'suppliers') {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path
          d="M4 17h16v3H4zm2-6 2-6h8l2 6M7 17v-6m10 6v-6M9 11h6"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinejoin="round"
        />
      </svg>
    )
  }

  if (name === 'leads') {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path
          d="M12 4a5 5 0 0 1 5 5c0 4-5 9-5 9s-5-5-5-9a5 5 0 0 1 5-5zm0 7.5a2.5 2.5 0 1 0-2.5-2.5A2.5 2.5 0 0 0 12 11.5z"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
        />
      </svg>
    )
  }

  return (
    <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
      <path
        d="M6 4h9l3 3v13H6zM15 4v3h3M8 11h8M8 15h5"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinejoin="round"
      />
    </svg>
  )
}

export function DashboardStatCard({
  title,
  icon,
  metric,
  emptyLabel,
  secondaryLabel,
}: DashboardStatCardProps) {
  const unavailableCopy = metric.reason ? UNAVAILABLE_METRIC_COPY[metric.reason] : undefined

  return (
    <article className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <h3 className="font-medium">{title}</h3>
        <span className="rounded-lg bg-slate-100 p-2 text-slate-700">
          <StatIcon name={icon} />
          <span className="sr-only">{title}</span>
        </span>
      </div>

      {metric.available ? (
        <>
          <p className="text-3xl font-semibold leading-none tabular-nums">
            {(metric.value ?? 0).toLocaleString('ar-SA')}
          </p>
          <p className="text-sm text-slate-600">
            {metric.value === 0 ? emptyLabel : (secondaryLabel ?? ' ')}
          </p>
        </>
      ) : (
        <>
          <p className="text-sm font-medium text-amber-800">غير مفعّل بعد</p>
          <p className="text-sm leading-6 text-slate-600">
            {unavailableCopy ?? 'لا تتوفر بيانات موثوقة لهذا المؤشر حالياً.'}
          </p>
        </>
      )}
    </article>
  )
}
