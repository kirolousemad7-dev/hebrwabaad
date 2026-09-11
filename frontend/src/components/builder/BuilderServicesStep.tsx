import { useMemo, useState } from 'react'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../catalog/CatalogStatus'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getPublicServices } from '../../services/catalog'
import type { Service, ServiceCategory } from '../../types/api'
import { BUILDER_CATEGORY_ORDER } from '../../utils/builder'
import { formatDuration, SERVICE_CATEGORY_LABELS, servicePriceLabel } from '../../utils/catalog'

type BuilderServicesStepProps = {
  selected: Service[]
  onToggle: (service: Service) => void
  onRemove: (serviceId: number) => void
}

export function BuilderServicesStep({ selected, onToggle, onRemove }: BuilderServicesStepProps) {
  const { state, reload } = useAsyncData(() => getPublicServices())
  const [query, setQuery] = useState('')
  const [categoryFilter, setCategoryFilter] = useState<ServiceCategory | 'ALL'>('ALL')
  const selectedIds = new Set(selected.map((service) => service.id))

  const filtered = useMemo(() => {
    if (state.status !== 'ready') {
      return []
    }

    const needle = query.trim().toLowerCase()

    return state.data.filter((service) => {
      if (categoryFilter !== 'ALL' && service.category !== categoryFilter) {
        return false
      }

      if (needle === '') {
        return true
      }

      return (
        service.name.toLowerCase().includes(needle) ||
        (service.summary ?? '').toLowerCase().includes(needle) ||
        service.slug.toLowerCase().includes(needle)
      )
    })
  }, [categoryFilter, query, state])

  const grouped = BUILDER_CATEGORY_ORDER.map((category) => ({
    category,
    services: filtered.filter((service) => service.category === category),
  })).filter((group) => group.services.length > 0)

  return (
    <section className="space-y-6">
      <header className="space-y-1">
        <h2 className="text-xl font-extrabold text-brand-black">اختار الخدمات اللي تحتاجها</h2>
        <p className="text-sm text-brand-text-muted">
          يمكنك اختيار خدمات من فئات متعددة في نفس الباقة — الاستراتيجية والتصميم والتصوير وغيرها معاً.
        </p>
      </header>

      {selected.length > 0 ? (
        <div className="rounded-2xl border border-brand-primary/30 bg-brand-primary-soft/60 p-4">
          <p className="mb-2 text-sm font-semibold text-brand-black">تم اختيار {selected.length} خدمات</p>
          <ul className="flex flex-wrap gap-2">
            {selected.map((service) => (
              <li key={service.id}>
                <button
                  type="button"
                  onClick={() => onRemove(service.id)}
                  className="inline-flex min-h-9 items-center gap-2 rounded-full border border-brand-primary bg-white px-3 text-sm text-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
                >
                  {service.name}
                  <span aria-hidden="true">×</span>
                </button>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label className="block min-w-0 flex-1 text-sm">
          <span className="sr-only">بحث في الخدمات</span>
          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="ابحث باسم الخدمة..."
            className="w-full rounded-full border border-brand-border bg-white px-4 py-2.5"
          />
        </label>
        <label className="block text-sm sm:w-56">
          <span className="sr-only">تصفية حسب الفئة</span>
          <select
            value={categoryFilter}
            onChange={(event) => setCategoryFilter(event.target.value as ServiceCategory | 'ALL')}
            className="w-full rounded-full border border-brand-border bg-white px-4 py-2.5"
          >
            <option value="ALL">كل الفئات</option>
            {BUILDER_CATEGORY_ORDER.map((category) => (
              <option key={category} value={category}>
                {SERVICE_CATEGORY_LABELS[category]}
              </option>
            ))}
          </select>
        </label>
      </div>

      {state.status === 'loading' ? <CatalogSkeleton variant="services" label="جاري تحميل الخدمات..." /> : null}

      {state.status === 'error' ? (
        <CatalogErrorState message={`تعذر تحميل الخدمات. ${state.message}`} onRetry={() => void reload()} />
      ) : null}

      {state.status === 'ready' && state.data.length === 0 ? (
        <CatalogEmptyState title="لا توجد خدمات منشورة حالياً" description="حاول لاحقاً أو تواصل معنا." actions={[]} />
      ) : null}

      {state.status === 'ready' && state.data.length > 0 && grouped.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-brand-border bg-white px-4 py-8 text-center text-sm text-brand-text-muted">
          لا توجد نتائج مطابقة للبحث أو التصفية.
        </p>
      ) : null}

      {grouped.map((group) => (
        <div key={group.category} className="space-y-3">
          <h3 className="text-base font-bold text-brand-black">{pdfCategoryLabel(group.category)}</h3>
          <ul className="grid gap-3 sm:grid-cols-2">
            {group.services.map((service) => {
              const isSelected = selectedIds.has(service.id)

              return (
                <li key={service.id} className="min-w-0">
                  <button
                    type="button"
                    role="checkbox"
                    aria-checked={isSelected}
                    onClick={() => onToggle(service)}
                    className={[
                      'flex h-full w-full min-w-0 flex-col gap-2 rounded-2xl border p-4 text-right transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary',
                      isSelected
                        ? 'border-brand-primary bg-brand-primary-soft ring-2 ring-brand-primary'
                        : 'border-brand-border bg-white hover:border-brand-primary/40',
                    ].join(' ')}
                  >
                    <span className="flex items-start justify-between gap-2">
                      <span className="font-semibold text-brand-black">{service.name}</span>
                      <span
                        className={[
                          'inline-flex h-5 w-5 shrink-0 items-center justify-center rounded border text-xs',
                          isSelected
                            ? 'border-brand-primary bg-brand-primary text-white'
                            : 'border-brand-border bg-white text-transparent',
                        ].join(' ')}
                        aria-hidden="true"
                      >
                        ✓
                      </span>
                    </span>
                    <span className="text-xs text-brand-text-muted">{SERVICE_CATEGORY_LABELS[service.category]}</span>
                    {service.summary ? <span className="text-sm text-brand-text-muted">{service.summary}</span> : null}
                    <span className="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                      <span className="font-semibold text-brand-black">{servicePriceLabel(service)}</span>
                      {service.duration_days !== null ? (
                        <span className="text-brand-text-muted">{formatDuration(service.duration_days)}</span>
                      ) : null}
                    </span>
                  </button>
                </li>
              )
            })}
          </ul>
        </div>
      ))}
    </section>
  )
}

function pdfCategoryLabel(category: ServiceCategory): string {
  switch (category) {
    case 'STRATEGY':
    case 'CAMPAIGNS':
      return 'الاستراتيجية والتسويق'
    case 'PRODUCTION':
      return 'الهوية والتصميم / التصوير والفيديو'
    case 'CONTENT':
      return 'المحتوى'
    case 'STORES':
      return 'المتاجر والتجربة الرقمية'
    case 'PRINTING':
      return 'الطباعة'
    default:
      return SERVICE_CATEGORY_LABELS[category]
  }
}
