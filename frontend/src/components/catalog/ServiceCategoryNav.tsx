import type { ServiceCategory } from '../../types/api'
import { SERVICE_CATEGORY_LABELS } from '../../utils/catalog'

type ServiceCategoryNavProps = {
  title: string
  description: string
  allLabel: string
  categories: ServiceCategory[]
  selected: ServiceCategory | null
  onSelect: (category: ServiceCategory | null) => void
  blurbs: Partial<Record<ServiceCategory, string>>
}

export function ServiceCategoryNav({
  title,
  description,
  allLabel,
  categories,
  selected,
  onSelect,
  blurbs,
}: ServiceCategoryNavProps) {
  if (categories.length === 0) {
    return null
  }

  return (
    <section className="space-y-4" aria-labelledby="catalog-types-heading">
      <div className="space-y-1">
        <h2 id="catalog-types-heading" className="text-xl font-semibold">
          {title}
        </h2>
        <p className="text-sm text-slate-600">{description}</p>
      </div>

      <div className="flex gap-3 overflow-x-auto pb-1" role="toolbar" aria-label={title}>
        <button
          type="button"
          aria-pressed={selected === null}
          onClick={() => onSelect(null)}
          className={chipClass(selected === null)}
        >
          {allLabel}
        </button>
        {categories.map((category) => (
          <button
            key={category}
            type="button"
            aria-pressed={selected === category}
            onClick={() => onSelect(category)}
            className={chipClass(selected === category)}
          >
            {SERVICE_CATEGORY_LABELS[category]}
          </button>
        ))}
      </div>

      {selected ? (
        <p className="rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700">
          {SERVICE_CATEGORY_LABELS[selected]}: {blurbs[selected]}
        </p>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {categories.map((category) => (
            <li key={category} className="rounded-lg border border-slate-200 bg-white p-4">
              <p className="font-medium">{SERVICE_CATEGORY_LABELS[category]}</p>
              <p className="mt-1 text-sm text-slate-600">{blurbs[category]}</p>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

function chipClass(active: boolean): string {
  return [
    'shrink-0 rounded-full px-4 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
    active ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700',
  ].join(' ')
}
