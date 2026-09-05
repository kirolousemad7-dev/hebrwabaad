import { BUILDER_STEPS, type BuilderStepId } from '../../utils/builder'

type BuilderProgressProps = {
  current: BuilderStepId
}

export function BuilderProgress({ current }: BuilderProgressProps) {
  return (
    <ol className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 text-sm" aria-label="خطوات تصميم الباقة">
      {BUILDER_STEPS.map((step) => {
        const isCurrent = step.id === current
        const isDone = step.id < current

        return (
          <li key={step.id} className="shrink-0">
            <span
              aria-current={isCurrent ? 'step' : undefined}
              className={[
                'inline-flex min-h-11 items-center gap-2 rounded-full px-3 py-1.5',
                isCurrent
                  ? 'bg-slate-900 font-semibold text-white shadow-sm'
                  : isDone
                    ? 'bg-amber-100 font-medium text-amber-900'
                    : 'bg-slate-100 text-slate-500',
              ].join(' ')}
            >
              <span
                aria-hidden="true"
                className={[
                  'inline-flex h-6 w-6 items-center justify-center rounded-full text-xs',
                  isCurrent ? 'bg-amber-400 text-slate-900' : 'bg-white/80 text-slate-700',
                ].join(' ')}
              >
                {step.id}
              </span>
              {step.label}
            </span>
          </li>
        )
      })}
    </ol>
  )
}
