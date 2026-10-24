import { useState } from 'react'
import { CatalogPriceLine } from '../components/catalog/CatalogPriceLine'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicServices } from '../services/catalog'
import type { Service } from '../types/api'
import { SERVICE_CATEGORY_LABELS } from '../utils/catalog'
import { resolveMediaUrl } from '../utils/mediaUrl'
import { buildRequestQuotePath } from '../utils/quoteRequests'

function ServiceCover({ service }: { service: Service }) {
  const image = resolveMediaUrl(service.hero_image)
  const [failed, setFailed] = useState(false)
  const mark = SERVICE_CATEGORY_LABELS[service.category].slice(0, 1)

  if (!image || failed) {
    return (
      <span className="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-ink-900 text-xs font-medium text-white">
        {mark}
      </span>
    )
  }

  return (
    <img
      src={image}
      alt=""
      className="mb-3 h-28 w-full rounded-xl object-cover"
      loading="lazy"
      onError={() => setFailed(true)}
    />
  )
}

export function ServicesPage() {
  const { state, reload } = useAsyncData(getPublicServices)

  return (
    <section className="space-y-6">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'الخدمات' },
        ]}
      />
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold">خدمات متكاملة لبناء وتطوير أعمالك</h1>
        <p className="text-slate-600">
          منصة متكاملة تبدأ بتشخيص نشاطك، ثم تخطيط النمو واختيار الخدمات والموردين، وصولًا إلى التنفيذ والقياس والمتابعة.
        </p>
        <div className="flex flex-wrap gap-3">
          <PublicCta to="/packages" variant="secondary">
            تصفح الباقات
          </PublicCta>
          <PublicCta to="/marketing-packages" variant="secondary">
            الباقات التسويقية
          </PublicCta>
          <PublicCta to="/printing-packaging" variant="secondary">
            الطباعة والتغليف
          </PublicCta>
          <PublicCta to="/build-package">صمّم باقتك</PublicCta>
        </div>
      </header>

      {state.status === 'loading' ? <CatalogSkeleton variant="services" label="جاري تحميل الخدمات..." /> : null}

      {state.status === 'error' ? (
        <CatalogErrorState message={`تعذر تحميل الخدمات. ${state.message}`} onRetry={() => void reload()} />
      ) : null}

      {state.status === 'ready' && state.data.length === 0 ? (
        <CatalogEmptyState
          title="الخدمات قيد التجهيز"
          description="لا توجد خدمات منشورة حالياً. يمكنك تصفح الباقات الجاهزة أو العودة للرئيسية."
          actions={[
            { to: '/packages', label: 'كل الباقات', variant: 'primary' },
            { to: '/', label: 'الرئيسية', variant: 'secondary' },
          ]}
        />
      ) : null}

      {state.status === 'ready' && state.data.length > 0 ? (
        <ul className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {state.data.map((service) => (
            <li key={service.id} className="flex h-full min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <ServiceCover service={service} />
              <div className="flex items-start justify-between gap-3">
                <h2 className="font-semibold">{service.name}</h2>
                <span className="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
                  {SERVICE_CATEGORY_LABELS[service.category]}
                </span>
              </div>

              <p className="line-clamp-3 min-h-16 flex-1 text-sm leading-7 text-slate-600">{service.summary || ' '}</p>

              <div className="mt-auto space-y-3 border-t border-slate-100 pt-3">
                <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                  <CatalogPriceLine
                    pricingMode={service.pricing_mode}
                    current={service.base_price}
                    before={service.price_before_discount}
                    currency={service.currency}
                    durationDays={service.duration_days}
                    chargeable={service.is_chargeable}
                  />
                  {service.is_featured ? (
                    <span className="rounded-full bg-brand-primary-soft px-2 py-0.5 text-xs text-brand-primary">مميّزة</span>
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
