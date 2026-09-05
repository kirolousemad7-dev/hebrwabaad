import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PublicCta } from '../components/public/PublicCta'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicServices } from '../services/catalog'
import { formatDuration, servicePriceLabel, SERVICE_CATEGORY_LABELS } from '../utils/catalog'

export function ServicesPage() {
  const { state, reload } = useAsyncData(getPublicServices)

  return (
    <section className="space-y-6">
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold">الخدمات</h1>
        <p className="text-slate-600">خدماتنا المتاحة حالياً مع الأسعار ومدة التنفيذ التقديرية.</p>
        <div className="flex flex-wrap gap-3">
          <PublicCta to="/packages" variant="secondary">
            تصفح الباقات
          </PublicCta>
          <PublicCta to="/marketing-packages" variant="secondary">
            الباقات التسويقية
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
                  {service.is_featured ? (
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">مميّزة</span>
                  ) : null}
                </div>
                <PublicCta to="/consultant" variant="secondary">
                  اطلب توصية مناسبة
                </PublicCta>
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  )
}
