import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PackageCard } from '../components/catalog/PackageCard'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicPackages } from '../services/catalog'
import { PACKAGE_FAQS } from '../utils/catalogRoutes'

export function PackagesPage() {
  const { state, reload } = useAsyncData(getPublicPackages)

  return (
    <section className="space-y-6">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'الباقات' },
        ]}
      />
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold">خدمات مترابطة لنمو نشاطك</h1>
        <p className="text-slate-600">خدمات مترابطة ضمن نطاق واحد وفريق واحد وجدول زمني موحد، لتقليل التشتت وتسريع الوصول إلى النتيجة.</p>
        <div className="flex flex-wrap gap-3">
          <PublicCta to="/marketing-packages" variant="secondary">
            الباقات التسويقية
          </PublicCta>
          <PublicCta to="/event-packages" variant="secondary">
            الباقات للفعاليات
          </PublicCta>
          <PublicCta to="/services" variant="secondary">
            الخدمات
          </PublicCta>
          <PublicCta to="/build-package">صمّم باقتك</PublicCta>
        </div>
      </header>

      {state.status === 'loading' ? <CatalogSkeleton variant="list" label="جاري تحميل الباقات..." /> : null}

      {state.status === 'error' ? (
        <CatalogErrorState message={`تعذر تحميل الباقات. ${state.message}`} onRetry={() => void reload()} />
      ) : null}

      {state.status === 'ready' && state.data.length === 0 ? (
        <CatalogEmptyState
          title="الباقات قيد التجهيز"
          description="لا توجد باقات منشورة حالياً. يمكنك استكشاف الخدمات المتاحة أو العودة للرئيسية."
          actions={[
            { to: '/services', label: 'الخدمات', variant: 'primary' },
            { to: '/', label: 'الرئيسية', variant: 'secondary' },
          ]}
        />
      ) : null}

      {state.status === 'ready' && state.data.length > 0 ? (
        <ul className="grid gap-5 lg:grid-cols-2">
          {state.data.map((pkg) => (
            <li key={pkg.id} className="min-w-0">
              <PackageCard pkg={pkg} />
            </li>
          ))}
        </ul>
      ) : null}

      <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 className="text-lg font-semibold">أسئلة شائعة عن الباقات والخدمات</h2>
        <ul className="space-y-4">
          {PACKAGE_FAQS.map((item) => (
            <li key={item.question} className="space-y-1">
              <h3 className="font-medium">{item.question}</h3>
              <p className="text-sm leading-7 text-slate-600">{item.answer}</p>
            </li>
          ))}
        </ul>
      </section>
    </section>
  )
}
