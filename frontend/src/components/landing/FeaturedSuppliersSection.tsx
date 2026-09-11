import { Link } from 'react-router-dom'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getPublicSuppliers } from '../../services/suppliers'
import { supplierPath } from '../../utils/suppliers'

export function FeaturedSuppliersSection() {
  const { state } = useAsyncData(() => getPublicSuppliers({ featured: true }))
  const suppliers = state.status === 'ready' ? state.data.slice(0, 6) : []

  if (state.status === 'ready' && suppliers.length === 0) {
    return null
  }

  return (
    <section className="bg-white py-16 sm:py-20" id="partners">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <header className="mb-8 max-w-2xl space-y-3">
          <p className="text-sm font-medium text-amber-700">شركاؤنا من الموردين</p>
          <h2 className="text-3xl font-semibold">موردون معتمدون للطباعة والتغليف</h2>
          <p className="leading-8 text-slate-600">نظهر هنا الموردين النشطين المنشورين والمميزين فقط بعد اعتماد المالك.</p>
        </header>
        {state.status === 'loading' ? <p className="text-sm text-slate-500">جاري تحميل الشركاء...</p> : null}
        {suppliers.length > 0 ? (
          <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {suppliers.map((supplier) => (
              <li key={supplier.slug} className="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <img src={supplier.logo} alt="" className="h-14 w-14 rounded-2xl object-cover" loading="lazy" />
                <h3 className="mt-3 font-semibold">{supplier.name}</h3>
                <p className="text-sm text-slate-500">{supplier.category ?? supplier.specialties[0]}</p>
                <p className="mt-2 text-sm leading-7 text-slate-600">{supplier.short_description}</p>
                <Link to={supplierPath(supplier.slug)} className="mt-4 inline-flex min-h-10 items-center text-sm font-medium text-slate-900 underline">
                  عرض الملف
                </Link>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </section>
  )
}
