import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../catalog/CatalogStatus'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getPublicServices } from '../../services/catalog'
import type { Service, ServiceCategory } from '../../types/api'
import { formatDuration, formatMoney, SERVICE_CATEGORY_LABELS } from '../../utils/catalog'

type BuilderServicesStepProps = {
  category: ServiceCategory
  selected: Service[]
  onToggle: (service: Service) => void
  onChangeCategory: () => void
}

export function BuilderServicesStep({
  category,
  selected,
  onToggle,
  onChangeCategory,
}: BuilderServicesStepProps) {
  const { state, reload } = useAsyncData(() => getPublicServices(category))
  const selectedIds = new Set(selected.map((service) => service.id))

  return (
    <section className="space-y-4">
      <header className="space-y-1">
        <h2 className="text-lg font-semibold">اختر الخدمات</h2>
        <p className="text-sm text-slate-600">
          خدمات فئة {SERVICE_CATEGORY_LABELS[category]}. يمكنك اختيار أكثر من خدمة.
        </p>
      </header>

      {state.status === 'loading' ? <CatalogSkeleton variant="services" label="جاري تحميل الخدمات..." /> : null}

      {state.status === 'error' ? (
        <CatalogErrorState message={`تعذر تحميل الخدمات. ${state.message}`} onRetry={() => void reload()} />
      ) : null}

      {state.status === 'ready' && state.data.length === 0 ? (
        <div className="space-y-4">
          <CatalogEmptyState
            title="لا توجد خدمات في هذه الفئة حالياً"
            description="يمكنك العودة واختيار فئة أخرى."
            actions={[]}
          />
          <button
            type="button"
            onClick={onChangeCategory}
            className="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            تغيير الفئة
          </button>
        </div>
      ) : null}

      {state.status === 'ready' && state.data.length > 0 ? (
        <ul className="grid gap-3 sm:grid-cols-2">
          {state.data.map((service) => {
            const isSelected = selectedIds.has(service.id)

            return (
              <li key={service.id} className="min-w-0">
                <button
                  type="button"
                  aria-pressed={isSelected}
                  onClick={() => onToggle(service)}
                  className={[
                    'flex h-full w-full min-w-0 flex-col gap-2 rounded-xl border p-4 text-right focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
                    isSelected ? 'border-slate-900 bg-slate-50 ring-2 ring-slate-900' : 'border-slate-200 bg-white',
                  ].join(' ')}
                >
                  <span className="flex items-start justify-between gap-2">
                    <span className="font-semibold">{service.name}</span>
                    <span className="shrink-0 text-xs text-slate-500">{isSelected ? 'محددة' : 'إضافة'}</span>
                  </span>
                  {service.summary ? <span className="text-sm text-slate-600">{service.summary}</span> : null}
                  {service.description ? (
                    <span className="line-clamp-3 text-sm text-slate-500">{service.description}</span>
                  ) : null}
                  <span className="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span className="font-semibold">{formatMoney(service.base_price, service.currency)}</span>
                    {service.duration_days !== null ? (
                      <span className="text-slate-500">{formatDuration(service.duration_days)}</span>
                    ) : null}
                    {service.is_featured ? (
                      <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">مميّزة</span>
                    ) : null}
                  </span>
                </button>
              </li>
            )
          })}
        </ul>
      ) : null}
    </section>
  )
}
