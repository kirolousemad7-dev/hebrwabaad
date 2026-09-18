import { useEffect } from 'react'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicServices } from '../services/catalog'
import { formatDuration, servicePriceLabel, SERVICE_CATEGORY_LABELS } from '../utils/catalog'
import { CATALOG_SECTIONS, type CatalogSectionId } from '../utils/catalogRoutes'
import { buildRequestQuotePath } from '../utils/quoteRequests'

export function CatalogSectionPage({ sectionId }: { sectionId: CatalogSectionId }) {
  const section = CATALOG_SECTIONS[sectionId]
  const { state, reload } = useAsyncData(() => getPublicServices(undefined, section.subcategory), [section.subcategory])

  useEffect(() => {
    document.title = `${section.seoTitle} | حبر وأبعاد`
    document.querySelector('meta[name="description"]')?.setAttribute('content', section.description)
  }, [section.description, section.seoTitle])

  return (
    <section className="space-y-6">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'الخدمات', to: '/services' },
          { name: section.title },
        ]}
      />
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold">{section.title}</h1>
        <p className="max-w-3xl text-slate-600">{section.description}</p>
        <div className="flex flex-wrap gap-3">
          <PublicCta to="/consultant" variant="secondary">
            اكتشف احتياجك
          </PublicCta>
          <PublicCta to="/packages" variant="secondary">
            الباقات
          </PublicCta>
        </div>
      </header>

      {state.status === 'loading' ? <CatalogSkeleton variant="services" label="جاري تحميل الخدمات..." /> : null}
      {state.status === 'error' ? (
        <CatalogErrorState message={`تعذر تحميل الخدمات. ${state.message}`} onRetry={() => void reload()} />
      ) : null}
      {state.status === 'ready' && state.data.length === 0 ? (
        <CatalogEmptyState
          title="الخدمات قيد التجهيز"
          description="لا توجد خدمات منشورة في هذا القسم حالياً."
          actions={[{ to: '/services', label: 'كل الخدمات', variant: 'primary' }]}
        />
      ) : null}
      {state.status === 'ready' && state.data.length > 0 ? (
        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {state.data.map((service) => (
            <li key={service.id} className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <h2 className="font-semibold">{service.name}</h2>
                <span className="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
                  {SERVICE_CATEGORY_LABELS[service.category]}
                </span>
              </div>
              {service.summary ? <p className="flex-1 text-sm leading-7 text-slate-600">{service.summary}</p> : null}
              <div className="mt-auto space-y-3 border-t border-slate-100 pt-3">
                <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                  <span className="whitespace-nowrap text-xl font-semibold">{servicePriceLabel(service)}</span>
                  {service.duration_days !== null ? (
                    <span className="text-sm text-slate-500">{formatDuration(service.duration_days)}</span>
                  ) : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  <PublicCta to={`/services/${service.slug}`} variant="secondary">
                    تفاصيل الخدمة
                  </PublicCta>
                  <PublicCta
                    to={
                      service.pricing_mode === 'QUOTE' || !service.is_chargeable
                        ? buildRequestQuotePath({
                            source_type: 'SERVICE',
                            source_id: service.id,
                            title: service.name,
                          })
                        : '/consultant'
                    }
                    variant="secondary"
                  >
                    {service.pricing_mode === 'QUOTE' || !service.is_chargeable ? 'طلب تسعير' : 'اطلب توصية مناسبة'}
                  </PublicCta>
                </div>
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  )
}
