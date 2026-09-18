import { useMemo, useState } from 'react'
import { CatalogHero } from '../components/catalog/CatalogHero'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { SupplierCard } from '../components/suppliers/SupplierCard'
import { SupplierFilters } from '../components/suppliers/SupplierFilters'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicSuppliers } from '../services/suppliers'
import { filterSuppliers, uniqueSupplierCategories, uniqueSupplierLocations, uniqueSupplierValues } from '../utils/suppliers'

export function SuppliersPage() {
  const { state, reload } = useAsyncData(getPublicSuppliers)
  const [specialty, setSpecialty] = useState<string | null>(null)
  const [service, setService] = useState<string | null>(null)
  const [location, setLocation] = useState<string | null>(null)
  const [category, setCategory] = useState<string | null>(null)
  const [featuredOnly, setFeaturedOnly] = useState(false)
  const [query, setQuery] = useState('')

  const suppliers = useMemo(() => (state.status === 'ready' ? state.data : []), [state])
  const specialties = useMemo(() => uniqueSupplierValues(suppliers, 'specialties'), [suppliers])
  const services = useMemo(() => uniqueSupplierValues(suppliers, 'services'), [suppliers])
  const locations = useMemo(() => uniqueSupplierLocations(suppliers), [suppliers])
  const categories = useMemo(() => uniqueSupplierCategories(suppliers), [suppliers])
  const visible = useMemo(
    () => filterSuppliers(suppliers, { specialty, service, q: query, location, category, featured: featuredOnly }),
    [suppliers, specialty, service, query, location, category, featuredOnly],
  )
  const featured = visible.filter((supplier) => supplier.featured)
  const filtered = specialty !== null || service !== null || location !== null || category !== null || featuredOnly || query.trim() !== ''

  return (
    <div className="space-y-10">
      <CatalogHero
        tone="suppliers"
        eyebrow="شركاء الإنتاج"
        title="انضم إلى شبكة موردي حبر وأبعاد"
        description="إذا كنت تقدم خدمات الطباعة أو التغليف أو الدعاية أو التصوير أو تجهيز المعارض أو تنظيم الفعاليات، يمكنك الانضمام إلى شبكة الموردين وعرض منتجاتك واستقبال طلبات وفرص جديدة."
        primaryCta="استعرض الموردين"
        secondaryCta="الطباعة والتغليف"
        secondaryTo="/printing-packaging"
        packagesAnchor="supplier-list"
        packageCount={state.status === 'ready' ? suppliers.length : null}
        emptyCountLabel="لا يوجد موردون متاحون حاليًا."
        countLabel={(count) => `${count} موردون جاهزون للاستكشاف.`}
      />

      {state.status === 'loading' ? <CatalogSkeleton label="جاري تحميل الموردين..." /> : null}

      {state.status === 'error' ? (
        <CatalogErrorState message={`تعذر تحميل الموردين. ${state.message}`} onRetry={() => void reload()} />
      ) : null}

      {state.status === 'ready' && suppliers.length === 0 ? (
        <CatalogEmptyState
          title="لا يوجد موردون متاحون حاليًا."
          description="يمكنك العودة إلى كتالوج الطباعة أو تصفح الخدمات."
          actions={[
            { to: '/printing-packaging', label: 'الطباعة والتغليف', variant: 'primary' },
            { to: '/services', label: 'الخدمات', variant: 'secondary' },
          ]}
        />
      ) : null}

      {state.status === 'ready' && suppliers.length > 0 ? (
        <>
          <SupplierFilters
            specialties={specialties}
            services={services}
            locations={locations}
            categories={categories}
            selectedSpecialty={specialty}
            selectedService={service}
            selectedLocation={location}
            selectedCategory={category}
            featuredOnly={featuredOnly}
            query={query}
            onSpecialty={setSpecialty}
            onService={setService}
            onLocation={setLocation}
            onCategory={setCategory}
            onFeaturedOnly={setFeaturedOnly}
            onQuery={setQuery}
          />

          <section id="supplier-list" className="space-y-4 scroll-mt-24">
            {!filtered && featured.length > 0 ? (
              <p className="text-sm text-slate-600">الموردون المميزون يظهرون أولاً.</p>
            ) : null}

            {visible.length === 0 ? (
              <div className="space-y-3 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <h3 className="text-lg font-semibold">لا توجد نتائج مطابقة لبحثك.</h3>
                <p className="text-sm text-slate-600">جرّب تخصصاً أو خدمة أخرى، أو امسح التصفية لعرض كل الموردين.</p>
                <button
                  type="button"
                  onClick={() => {
                    setSpecialty(null)
                    setService(null)
                    setLocation(null)
                    setCategory(null)
                    setFeaturedOnly(false)
                    setQuery('')
                  }}
                  className="rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  عرض كل الموردين
                </button>
              </div>
            ) : (
              <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {visible.map((supplier) => (
                  <li key={supplier.id} className="min-w-0">
                    <SupplierCard supplier={supplier} />
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
