import { SERVICE_CATEGORIES, type ServiceCategory } from '../../types/api'
import { SERVICE_CATEGORY_LABELS } from '../../utils/catalog'
import { SERVICE_CATEGORY_BLURBS } from '../../utils/builder'

type BuilderCategoryStepProps = {
  selected: ServiceCategory | null
  onSelect: (category: ServiceCategory) => void
}

export function BuilderCategoryStep({ selected, onSelect }: BuilderCategoryStepProps) {
  return (
    <fieldset className="space-y-4">
      <legend className="text-lg font-semibold">اختر فئة الخدمات</legend>
      <p className="text-sm text-slate-600">نبدأ بفئة واحدة حتى تبقى الباقة واضحة وسهلة المقارنة.</p>
      <ul className="grid gap-3 sm:grid-cols-2">
        {SERVICE_CATEGORIES.map((category) => {
          const isSelected = selected === category

          return (
            <li key={category}>
              <button
                type="button"
                aria-pressed={isSelected}
                onClick={() => onSelect(category)}
                className={[
                  'flex min-h-24 w-full min-w-0 flex-col items-start rounded-xl border p-4 text-right focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
                  isSelected ? 'border-slate-900 bg-slate-50 ring-2 ring-slate-900' : 'border-slate-200 bg-white',
                ].join(' ')}
              >
                <span className="font-semibold">{SERVICE_CATEGORY_LABELS[category]}</span>
                <span className="mt-1 text-sm text-slate-600">{SERVICE_CATEGORY_BLURBS[category]}</span>
              </button>
            </li>
          )
        })}
      </ul>
    </fieldset>
  )
}
