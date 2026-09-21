import { useState } from 'react'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PackageCard } from '../components/catalog/PackageCard'
import { PageHero } from '../components/marketing/PageHero'
import { PublicCta } from '../components/public/PublicCta'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicPackages } from '../services/catalog'
import { PACKAGE_FAQS } from '../utils/catalogRoutes'

export function PackagesPage() {
  const { state, reload } = useAsyncData(getPublicPackages)
  const [openId, setOpenId] = useState<number | null>(null)

  return (
    <section className="space-y-12">
      <PageHero
        eyebrow="الباقات"
        title="خدمات مترابطة لنمو نشاطك"
        description="خدمات مترابطة ضمن نطاق واحد وفريق واحد وجدول زمني موحد، لتقليل التشتت وتسريع الوصول إلى النتيجة."
        breadcrumbs={[
          { label: 'الرئيسية', to: '/' },
          { label: 'الباقات' },
        ]}
      >
        <div className="flex flex-wrap gap-3 pt-2">
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
      </PageHero>

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
        <ul className="grid items-stretch gap-6 lg:grid-cols-2 xl:grid-cols-3">
          {state.data.map((pkg) => (
            <li key={pkg.id} className="min-w-0">
              <PackageCard
                pkg={pkg}
                open={openId === pkg.id}
                onToggle={() => setOpenId((current) => (current === pkg.id ? null : pkg.id))}
              />
            </li>
          ))}
        </ul>
      ) : null}

      <section className="space-y-5 border-t border-brand-ink-100 pt-10">
        <h2 className="text-xl font-semibold text-brand-ink-900">أسئلة شائعة عن الباقات والخدمات</h2>
        <ul className="grid gap-6 sm:grid-cols-2">
          {PACKAGE_FAQS.map((item) => (
            <li key={item.question} className="space-y-2">
              <h3 className="font-medium text-brand-ink-900">{item.question}</h3>
              <p className="text-sm leading-7 text-brand-ink-500">{item.answer}</p>
            </li>
          ))}
        </ul>
      </section>
    </section>
  )
}
