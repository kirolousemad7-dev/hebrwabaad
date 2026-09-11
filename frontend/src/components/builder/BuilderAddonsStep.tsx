import { addonPriceLabel, type BuilderAddon } from '../../utils/builder'

type BuilderAddonsStepProps = {
  addons: BuilderAddon[]
  selectedIds: string[]
  onToggle: (addonId: string) => void
  loading?: boolean
}

export function BuilderAddonsStep({ addons, selectedIds, onToggle, loading }: BuilderAddonsStepProps) {
  return (
    <section className="space-y-4">
      <header className="space-y-1">
        <h2 className="text-xl font-extrabold text-brand-black">إضافات على مستوى الباقة</h2>
        <p className="text-sm text-brand-text-muted">
          إضافات عامة مثل الخدمة العاجلة أو الطباعة — فقط عند توفرها. الأسعار تظهر بعد اعتماد المالك.
        </p>
      </header>
      {loading ? <p className="text-sm text-brand-text-muted">جاري تحميل الإضافات...</p> : null}
      {!loading && addons.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-brand-border bg-white p-4 text-sm text-brand-text-muted">
          لا توجد إضافات عامة حالياً. يمكنك المتابعة للمراجعة.
        </p>
      ) : null}
      <ul className="grid gap-3 sm:grid-cols-2">
        {addons.map((addon) => {
          const isSelected = selectedIds.includes(addon.id)
          const disabled = addon.is_available === false

          return (
            <li key={addon.id} className="min-w-0">
              <button
                type="button"
                aria-pressed={isSelected}
                disabled={disabled}
                onClick={() => onToggle(addon.id)}
                className={[
                  'flex h-full w-full min-w-0 flex-col gap-2 rounded-2xl border p-4 text-right focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary disabled:cursor-not-allowed disabled:opacity-50',
                  isSelected
                    ? 'border-brand-primary bg-brand-primary-soft ring-2 ring-brand-primary'
                    : 'border-brand-border bg-white',
                ].join(' ')}
              >
                <span className="flex items-start justify-between gap-2">
                  <span className="font-semibold text-brand-black">{addon.name}</span>
                  <span className="shrink-0 text-xs text-brand-text-muted">
                    {disabled ? 'غير متاحة' : isSelected ? 'محددة' : 'إضافة'}
                  </span>
                </span>
                <span className="text-sm text-brand-text-muted">{addon.description}</span>
                <span className="mt-auto text-sm font-semibold text-brand-black">{addonPriceLabel(addon)}</span>
              </button>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
