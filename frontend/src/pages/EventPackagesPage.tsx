import { useMemo, useState } from 'react'
import { CatalogHero } from '../components/catalog/CatalogHero'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { PackageCard } from '../components/catalog/PackageCard'
import { ServiceCategoryNav } from '../components/catalog/ServiceCategoryNav'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicPackages } from '../services/catalog'
import type { ServiceCategory } from '../types/api'
import { filterPackagesByServiceCategory, uniquePackageServiceCategories } from '../utils/catalog'

const EVENT_TYPE_BLURBS: Partial<Record<ServiceCategory, string>> = {
  PRODUCTION: 'تصميم وإنتاج بصري وتغطية مرئية ليوم الفعالية.',
  PRINTING: 'مواد مطبوعة للضيافة واللافتات والهوية الميدانية.',
  OTHER: 'هوية الفعالية والتنسيق التنظيمي يوم التنفيذ.',
  STRATEGY: 'تخطيط أهداف الفعالية ورسائلها.',
  CONTENT: 'توثيق ومحتوى مكتوب حول المناسبة.',
  CAMPAIGNS: 'ترويج للفعالية قبل يوم التنفيذ.',
  STORES: 'نقاط بيع أو تسجيل مرتبطة بالمناسبة.',
}

function loadEventPackages() {
  return getPublicPackages('EVENTS')
}

export function EventPackagesPage() {
  const { state, reload } = useAsyncData(loadEventPackages)
  const [selectedType, setSelectedType] = useState<ServiceCategory | null>(null)

  const packages = useMemo(
    () => (state.status === 'ready' ? state.data : []),
    [state],
  )
  const eventTypes = useMemo(() => uniquePackageServiceCategories(packages), [packages])
  const visiblePackages = useMemo(
    () => filterPackagesByServiceCategory(packages, selectedType),
    [packages, selectedType],
  )

  return (
    <div className="space-y-10">
      <CatalogHero
        tone="events"
        eyebrow="حلول الفعاليات"
        title="نحوّل فعاليتك إلى تجربة لا تُنسى"
        description="من التخطيط والتنظيم إلى الهوية البصرية والإنتاج والتنفيذ الميداني. نجهّز مناسبتك كتجربة متكاملة، لا كمجموعة مهام منفصلة."
        primaryCta="استكشف الباقات"
        packagesAnchor="event-packages-list"
        packageCount={state.status === 'ready' ? packages.length : null}
        emptyCountLabel="لا توجد باقات فعاليات منشورة حالياً."
        countLabel={(count) => `${count} باقة فعاليات جاهزة للتنفيذ.`}
      />

      {state.status === 'loading' ? <CatalogSkeleton label="جاري تحميل باقات الفعاليات..." /> : null}

      {state.status === 'error' ? (
        <CatalogErrorState
          message={`تعذر تحميل باقات الفعاليات. ${state.message}`}
          onRetry={() => void reload()}
        />
      ) : null}

      {state.status === 'ready' && packages.length === 0 ? (
        <CatalogEmptyState
          title="باقات الفعاليات قيد التجهيز"
          description="يمكنك استكشاف خدماتنا المتاحة أو الاطلاع على كل الباقات لمعرفة المزيد."
        />
      ) : null}

      {state.status === 'ready' && packages.length > 0 ? (
        <>
          <section className="grid gap-3 sm:grid-cols-3" aria-label="ماذا نغطي في فعاليتك">
            <div className="rounded-xl border border-amber-100 bg-amber-50/70 p-4">
              <p className="font-medium">تخطيط وتنظيم</p>
              <p className="mt-1 text-sm text-slate-600">نرتّب الجدول، المساحات، وتسلسل يوم المناسبة.</p>
            </div>
            <div className="rounded-xl border border-amber-100 bg-amber-50/70 p-4">
              <p className="font-medium">إنتاج وإبداع</p>
              <p className="mt-1 text-sm text-slate-600">هوية بصرية، تصوير، ومواد تُثبت حضور علامتك.</p>
            </div>
            <div className="rounded-xl border border-amber-100 bg-amber-50/70 p-4">
              <p className="font-medium">تنفيذ ميداني</p>
              <p className="mt-1 text-sm text-slate-600">طباعة، تركيب، وتغطية حتى إغلاق الفعالية.</p>
            </div>
          </section>

          <ServiceCategoryNav
            title="أنواع التغطية"
            description="لا توجد أنواع فعاليات منفصلة في الكتالوج. نعرض التصنيفات المستمدة من الخدمات المشمولة في باقات الفعاليات الحالية."
            allLabel="جميع الفعاليات"
            categories={eventTypes}
            selected={selectedType}
            onSelect={setSelectedType}
            blurbs={EVENT_TYPE_BLURBS}
          />

          <section id="event-packages-list" className="space-y-4 scroll-mt-6">
            <div className="flex flex-wrap items-end justify-between gap-2">
              <h2 className="text-xl font-semibold">باقات الفعاليات</h2>
              <p className="text-sm text-slate-500">
                {visiblePackages.length} من {packages.length}
              </p>
            </div>

            {visiblePackages.length === 0 ? (
              <p className="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-600">
                لا توجد باقة تغطي هذا النوع حالياً. عد إلى جميع الفعاليات لمعاينة المتاح.
              </p>
            ) : (
              <ul className={visiblePackages.length === 1 ? 'grid gap-5' : 'grid gap-5 lg:grid-cols-2'}>
                {visiblePackages.map((pkg) => (
                  <li key={pkg.id} className="min-w-0">
                    <PackageCard pkg={pkg} tone="events" />
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
