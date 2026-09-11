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
                'inline-flex min-h-11 items-center gap-2 rounded-xl px-3 py-1.5',
                isCurrent
                  ? 'bg-brand-cobalt-500 font-semibold text-white shadow-sm'
                  : isDone
                    ? 'bg-brand-cobalt-100 font-medium text-brand-cobalt-700'
                    : 'bg-brand-ink-100 text-brand-ink-500',
              ].join(' ')}
            >
              <span
                aria-hidden="true"
                className={[
                  'inline-flex h-6 w-6 items-center justify-center rounded-lg text-xs font-latin',
                  isCurrent ? 'bg-white text-brand-cobalt-500' : 'bg-white/90 text-brand-ink-700',
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
