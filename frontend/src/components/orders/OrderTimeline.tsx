import type { OrderTimelineStep } from '../../types/api'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'

type OrderTimelineProps = {
  steps: OrderTimelineStep[]
}

export function OrderTimeline({ steps }: OrderTimelineProps) {
  return (
    <ol className="flex min-w-0 flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start lg:justify-between">
      {steps.map((step, index) => {
        const marker =
          step.state === 'completed' ? '✓' : step.state === 'current' ? '●' : '○'
        const tone =
          step.state === 'current'
            ? 'border-amber-300 bg-amber-50 text-slate-900'
            : step.state === 'completed'
              ? 'border-slate-200 bg-white text-slate-800'
              : 'border-dashed border-slate-200 bg-slate-50 text-slate-400'

        return (
          <li key={step.status} className="min-w-0 flex-1">
            <div className={`rounded-2xl border px-4 py-3 ${tone}`}>
              <p className="text-sm font-medium">
                <span aria-hidden="true" className="ms-1">
                  {marker}
                </span>
                {step.label}
              </p>
              {step.occurred_at ? (
                <p className="mt-1 text-xs text-slate-500">{formatDashboardDateTime(step.occurred_at)}</p>
              ) : null}
            </div>
            {index < steps.length - 1 ? (
              <p className="px-4 py-1 text-center text-xs text-slate-400 lg:hidden" aria-hidden="true">
                ↓
              </p>
            ) : null}
          </li>
        )
      })}
    </ol>
  )
}
