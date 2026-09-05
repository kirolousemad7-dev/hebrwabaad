import type { CustomerDashboardMetric } from '../../types/api'

type CustomerMetricCardProps = {
  title: string
  metric: CustomerDashboardMetric
  emptyLabel: string
  secondaryLabel?: string | null
}

export function CustomerMetricCard({ title, metric, emptyLabel, secondaryLabel }: CustomerMetricCardProps) {
  return (
    <article className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h3 className="font-medium">{title}</h3>
      {metric.available ? (
        <>
          <p className="text-3xl font-semibold leading-none tabular-nums">{(metric.value ?? 0).toLocaleString('ar-SA')}</p>
          <p className="text-sm text-slate-600">{metric.value === 0 ? emptyLabel : (secondaryLabel ?? ' ')}</p>
        </>
      ) : (
        <p className="text-sm font-medium text-amber-800">غير متاح بعد</p>
      )}
    </article>
  )
}
