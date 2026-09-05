import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PackageCard } from '../components/catalog/PackageCard'
import { PublicCta } from '../components/public/PublicCta'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicPackages } from '../services/catalog'

export function PackagesPage() {
  const { state, reload } = useAsyncData(getPublicPackages)

  return (
    <section className="space-y-6">
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold">الباقات</h1>
        <p className="text-slate-600">باقات جاهزة تجمع أكثر من خدمة بسعر واحد.</p>
        <div className="flex flex-wrap gap-3">
          <PublicCta to="/marketing-packages" variant="secondary">
            الباقات التسويقية
          </PublicCta>
          <PublicCta to="/event-packages" variant="secondary">
            الباقات للفعاليات
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
    </section>
  )
}
