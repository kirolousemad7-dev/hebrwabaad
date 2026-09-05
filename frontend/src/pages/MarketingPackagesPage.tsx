import { useMemo, useState } from 'react'
import { CatalogHero } from '../components/catalog/CatalogHero'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PackageCard } from '../components/catalog/PackageCard'
import { ServiceCategoryNav } from '../components/catalog/ServiceCategoryNav'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicPackages } from '../services/catalog'
import type { ServiceCategory } from '../types/api'
import { filterPackagesByServiceCategory, uniquePackageServiceCategories } from '../utils/catalog'

const MARKETING_BLURBS: Partial<Record<ServiceCategory, string>> = {
  STRATEGY: 'تموضع وأهداف وقياس واضح.',
  CONTENT: 'نصوص وجداول نشر جاهزة للتنفيذ.',
  PRODUCTION: 'تصميم وإنتاج بصري للحملات.',
  STORES: 'تهيئة المتجر لدعم الحملات.',
  CAMPAIGNS: 'إعلانات مدفوعة وتحسين أداء.',
  PRINTING: 'مواد مطبوعة مساندة للحملة.',
  OTHER: 'خدمات مساندة حسب احتياج العلامة.',
}

function loadMarketingPackages() {
  return getPublicPackages('MARKETING')
}

export function MarketingPackagesPage() {
  const { state, reload } = useAsyncData(loadMarketingPackages)
  const [selectedCategory, setSelectedCategory] = useState<ServiceCategory | null>(null)

  const packages = useMemo(
    () => (state.status === 'ready' ? state.data : []),
    [state],
  )
  const categories = useMemo(() => uniquePackageServiceCategories(packages), [packages])
  const visiblePackages = useMemo(
    () => filterPackagesByServiceCategory(packages, selectedCategory),
    [packages, selectedCategory],
  )

  return (
    <div className="space-y-10">
      <CatalogHero
        tone="marketing"
        eyebrow="باقات التسويق الرقمي"
        title="باقات تسويقية متكاملة لنمو علامتك التجارية"
        description="نبني حضورك الرقمي من الاستراتيجية إلى المحتوى والحملات. اختر باقة جاهزة تناسب مرحلتك، وسننفّذها بفريق حبر وأبعاد دون تشتيت بين موردين متعددين."
        primaryCta="استعرض الباقات"
        secondaryCta="تصفح الخدمات المنفردة"
        packagesAnchor="marketing-packages-list"
        packageCount={state.status === 'ready' ? packages.length : null}
        emptyCountLabel="لا توجد باقات تسويقية منشورة حالياً."
        countLabel={(count) => `${count} باقة تسويقية جاهزة للاختيار.`}
      />

      {state.status === 'loading' ? <CatalogSkeleton label="جاري تحميل الباقات التسويقية..." /> : null}

      {state.status === 'error' ? (
        <CatalogErrorState
          message={`تعذر تحميل الباقات التسويقية. ${state.message}`}
          onRetry={() => void reload()}
        />
      ) : null}

      {state.status === 'ready' && packages.length === 0 ? (
        <CatalogEmptyState
          title="لا توجد باقات تسويقية منشورة حالياً"
          description="يمكنك تصفح الخدمات المنفردة أو العودة إلى كل الباقات."
        />
      ) : null}

      {state.status === 'ready' && packages.length > 0 ? (
        <>
          <ServiceCategoryNav
            title="مجالات العمل التسويقي"
            description="صنّفنا الباقات حسب الخدمات المشمولة فيها. اختر مجالاً لمعاينة الباقات التي تغطيه."
            allLabel="كل الباقات"
            categories={categories}
            selected={selectedCategory}
            onSelect={setSelectedCategory}
            blurbs={MARKETING_BLURBS}
          />

          <section id="marketing-packages-list" className="space-y-4 scroll-mt-6">
            <div className="flex flex-wrap items-end justify-between gap-2">
              <h2 className="text-xl font-semibold">الباقات التسويقية</h2>
              <p className="text-sm text-slate-500">
                {visiblePackages.length} من {packages.length}
              </p>
            </div>

            {visiblePackages.length === 0 ? (
              <p className="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-600">
                لا توجد باقة تغطي هذا المجال حالياً. جرّب تصنيفاً آخر أو اعرض كل الباقات.
              </p>
            ) : (
              <ul className={visiblePackages.length === 1 ? 'grid gap-5' : 'grid gap-5 lg:grid-cols-2'}>
                {visiblePackages.map((pkg) => (
                  <li key={pkg.id} className="min-w-0">
                    <PackageCard pkg={pkg} tone="marketing" />
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      ) : null}
    </div>
  )
}
