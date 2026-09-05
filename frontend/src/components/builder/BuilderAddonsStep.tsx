import { BUILDER_ADDONS } from '../../utils/builder'
import { formatHalalas } from '../../utils/catalog'

type BuilderAddonsStepProps = {
  selectedIds: string[]
  onToggle: (addonId: string) => void
}

export function BuilderAddonsStep({ selectedIds, onToggle }: BuilderAddonsStepProps) {
  return (
    <section className="space-y-4">
      <header className="space-y-1">
        <h2 className="text-lg font-semibold">إضافات اختيارية</h2>
        <p className="text-sm text-slate-600">
          أسعار الإضافات تقديرية من واجهة المنصة وليست أسعاراً تعاقدية من كتالوج الخدمات.
        </p>
      </header>
      <ul className="grid gap-3 sm:grid-cols-2">
        {BUILDER_ADDONS.map((addon) => {
          const isSelected = selectedIds.includes(addon.id)

          return (
            <li key={addon.id} className="min-w-0">
              <button
                type="button"
                aria-pressed={isSelected}
                onClick={() => onToggle(addon.id)}
                className={[
                  'flex h-full w-full min-w-0 flex-col gap-2 rounded-xl border p-4 text-right focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
                  isSelected ? 'border-slate-900 bg-slate-50 ring-2 ring-slate-900' : 'border-slate-200 bg-white',
                ].join(' ')}
              >
                <span className="flex items-start justify-between gap-2">
                  <span className="font-semibold">{addon.name}</span>
                  <span className="shrink-0 text-xs text-slate-500">{isSelected ? 'محددة' : 'إضافة'}</span>
                </span>
                <span className="text-sm text-slate-600">{addon.description}</span>
                <span className="mt-auto text-sm font-semibold">{formatHalalas(addon.priceHalalas)}</span>
              </button>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
