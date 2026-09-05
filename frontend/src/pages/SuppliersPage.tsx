import { useMemo, useState } from 'react'
import { CatalogHero } from '../components/catalog/CatalogHero'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { SupplierCard } from '../components/suppliers/SupplierCard'
import { SupplierFilters } from '../components/suppliers/SupplierFilters'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicSuppliers } from '../services/suppliers'
import { filterSuppliers, uniqueSupplierValues } from '../utils/suppliers'

export function SuppliersPage() {
  const { state, reload } = useAsyncData(getPublicSuppliers)
  const [specialty, setSpecialty] = useState<string | null>(null)
  const [service, setService] = useState<string | null>(null)
  const [query, setQuery] = useState('')

  const suppliers = useMemo(() => (state.status === 'ready' ? state.data : []), [state])
  const specialties = useMemo(() => uniqueSupplierValues(suppliers, 'specialties'), [suppliers])
  const services = useMemo(() => uniqueSupplierValues(suppliers, 'services'), [suppliers])
  const visible = useMemo(
    () => filterSuppliers(suppliers, { specialty, service, q: query }),
    [suppliers, specialty, service, query],
  )
  const featured = visible.filter((supplier) => supplier.featured)
  const filtered = specialty !== null || service !== null || query.trim() !== ''

  return (
    <div className="space-y-10">
      <CatalogHero
        tone="suppliers"
        eyebrow="شركاء الإنتاج"
        title="شركاؤنا من الموردين"
        description="استكشف موردين موثوقين للطباعة والتغليف والمواد الدعائية. اطّلع على تخصصاتهم وخدماتهم وملفات أعمالهم قبل مرحلة التسعير."
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
            selectedSpecialty={specialty}
            selectedService={service}
            query={query}
            onSpecialty={setSpecialty}
            onService={setService}
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
