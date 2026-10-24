import { useEffect, useMemo } from 'react'
import { CatalogPriceLine } from '../components/catalog/CatalogPriceLine'
import { CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PackageCard } from '../components/catalog/PackageCard'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicPackages, getPublicServices } from '../services/catalog'
import type { Service } from '../types/api'
import { SERVICE_CATEGORY_LABELS } from '../utils/catalog'
import { CATALOG_SECTIONS, type CatalogSectionId } from '../utils/catalogRoutes'
import { resolveMediaUrl } from '../utils/mediaUrl'
import { buildRequestQuotePath } from '../utils/quoteRequests'

export function CatalogSectionPage({ sectionId }: { sectionId: CatalogSectionId }) {
  const section = CATALOG_SECTIONS[sectionId]

  useEffect(() => {
    document.title = `${section.seoTitle} | حبر وأبعاد`
    document.querySelector('meta[name="description"]')?.setAttribute('content', section.description)
  }, [section.description, section.seoTitle])

  const sectionState = useAsyncData(() => getPublicServices(undefined, section.subcategory), [section.subcategory])
  const catalogState = useAsyncData(getPublicServices, [section.subcategory])
  const packagesState = useAsyncData(getPublicPackages, [section.subcategory])

  const sectionServices = useMemo(
    () => (sectionState.state.status === 'ready' ? sectionState.state.data : []),
    [sectionState.state],
  )
  const publishedServices = catalogState.state.status === 'ready' ? catalogState.state.data : []
  const usingFallback = sectionState.state.status === 'ready' && sectionServices.length === 0 && publishedServices.length > 0
  const services = sectionServices.length > 0 ? sectionServices : publishedServices
  const completelyEmpty =
    sectionState.state.status === 'ready' &&
    catalogState.state.status === 'ready' &&
    services.length === 0

  const packages = useMemo(
    () => (packagesState.state.status === 'ready' ? packagesState.state.data : []),
    [packagesState.state],
  )
  const relatedPackages = useMemo(() => {
    const ids = new Set(sectionServices.map((service) => service.id))
    if (ids.size === 0) {
      return []
    }

    return packages.filter((pkg) => pkg.items.some((item) => ids.has(item.service_id)))
  }, [packages, sectionServices])
  const visiblePackages = relatedPackages.length > 0 ? relatedPackages : packages

  return (
    <section className="mx-auto w-full max-w-[1180px] space-y-10">
      <header className="space-y-3">
        <PublicBreadcrumbs
          items={[
            { name: 'الرئيسية', to: '/' },
            { name: 'الخدمات', to: '/services' },
            { name: section.title },
          ]}
        />
        <h1 className="text-2xl font-semibold text-brand-ink-900">{section.title}</h1>
        <p className="max-w-3xl text-sm leading-7 text-brand-ink-500">{section.description}</p>
        <div className="flex flex-wrap gap-3">
          <PublicCta to="/consultant" variant="secondary">
            اكتشف احتياجك
          </PublicCta>
          <PublicCta to="/packages" variant="secondary">
            الباقات
          </PublicCta>
        </div>
      </header>

      <div className="space-y-4">
        <div className="space-y-1">
          <h2 className="text-lg font-semibold text-brand-ink-900">الخدمات</h2>
          {usingFallback ? (
            <p className="text-sm text-brand-ink-500">لا توجد خدمات مخصصة لهذا القسم حالياً. هذه خدمات منشورة على المنصة.</p>
          ) : null}
        </div>

        {sectionState.state.status === 'loading' || (sectionServices.length === 0 && catalogState.state.status === 'loading') ? (
          <CatalogSkeleton variant="services" label="جاري تحميل الخدمات..." />
        ) : null}
        {sectionState.state.status === 'error' ? (
          <CatalogErrorState
            message={`تعذر تحميل الخدمات. ${sectionState.state.message}`}
            onRetry={() => void sectionState.reload()}
          />
        ) : null}
        {completelyEmpty || (sectionState.state.status === 'ready' && sectionServices.length === 0 && catalogState.state.status === 'error') ? (
          <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-ink-100 bg-white px-4 py-4">
            <div>
              <p className="text-sm font-medium text-brand-ink-900">لا توجد خدمات منشورة حالياً</p>
              <p className="text-sm text-brand-ink-500">يمكنك استعراض كل الخدمات من الكتالوج.</p>
            </div>
            <PublicCta to="/services">كل الخدمات</PublicCta>
          </div>
        ) : null}
        {services.length > 0 && sectionState.state.status === 'ready' ? (
          <ul className="grid grid-cols-1 items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {services.map((service) => (
              <ServiceOfferCard key={service.id} service={service} />
            ))}
          </ul>
        ) : null}
      </div>

      <div className="space-y-4">
        <div className="space-y-1">
          <h2 className="text-lg font-semibold text-brand-ink-900">الباقات</h2>
          {relatedPackages.length === 0 && visiblePackages.length > 0 ? (
            <p className="text-sm text-brand-ink-500">باقات منشورة على المنصة.</p>
          ) : null}
        </div>
        {packagesState.state.status === 'loading' ? <CatalogSkeleton variant="list" label="جاري تحميل الباقات..." /> : null}
        {packagesState.state.status === 'ready' && visiblePackages.length === 0 ? (
          <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-ink-100 bg-white px-4 py-4">
            <p className="text-sm text-brand-ink-500">لا توجد باقات منشورة حالياً.</p>
            <PublicCta to="/packages" variant="secondary">
              كل الباقات
            </PublicCta>
          </div>
        ) : null}
        {visiblePackages.length > 0 ? (
          <ul className="grid grid-cols-1 items-stretch gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {visiblePackages.map((pkg) => (
              <li key={pkg.id} className="min-w-0">
                <PackageCard pkg={pkg} />
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </section>
  )
}

function ServiceOfferCard({ service }: { service: Service }) {
  const image = resolveMediaUrl(service.hero_image)
  const summary = service.summary || service.short_description || ''

  return (
    <li className="flex h-full min-w-0 flex-col rounded-2xl border border-brand-ink-100 bg-white p-4">
      {image ? (
        <img src={image} alt="" className="mb-3 h-28 w-full rounded-xl object-cover" loading="lazy" />
      ) : (
        <span className="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-ink-900 text-xs font-medium text-white">
          {SERVICE_CATEGORY_LABELS[service.category].slice(0, 1)}
        </span>
      )}
      <h3 className="text-base font-semibold text-brand-ink-900">{service.name}</h3>
      <p className="mt-2 line-clamp-3 min-h-16 flex-1 text-sm leading-6 text-brand-ink-500">{summary || ' '}</p>
      <div className="mt-3 text-sm">
        <CatalogPriceLine
          pricingMode={service.pricing_mode}
          current={service.base_price}
          before={service.price_before_discount}
          currency={service.currency}
          durationDays={service.duration_days}
          chargeable={service.is_chargeable}
        />
      </div>
      <div className="mt-4 flex flex-wrap gap-2">
        <PublicCta to={`/services/${service.slug}`} variant="secondary">
          عرض التفاصيل
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
          variant="primary"
        >
          {service.pricing_mode === 'QUOTE' || !service.is_chargeable ? 'طلب تسعير' : 'اطلب الخدمة'}
        </PublicCta>
      </div>
    </li>
  )
}
