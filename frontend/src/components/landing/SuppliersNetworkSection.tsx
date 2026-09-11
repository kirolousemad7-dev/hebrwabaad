import { Link } from 'react-router-dom'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getPublicSuppliers } from '../../services/suppliers'
import { supplierPath } from '../../utils/suppliers'

export function SuppliersNetworkSection() {
  const { state } = useAsyncData(() => getPublicSuppliers({ featured: true }))
  const suppliers = state.status === 'ready' ? state.data.slice(0, 6) : []

  return (
    <section id="suppliers" className="bg-white py-16 sm:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <header className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div className="max-w-2xl space-y-3">
            <p className="text-sm font-semibold text-brand-primary">شبكة الموردين</p>
            <h2 className="text-3xl font-extrabold text-brand-black sm:text-4xl">موردونا وشركاؤنا</h2>
            <p className="leading-8 text-brand-text-muted">
              نعمل مع شبكة من الموردين والمتخصصين لتوفير حلول الطباعة، التغليف، التجهيزات والفعاليات حسب احتياج المشروع.
            </p>
          </div>
          <Link
            to="/suppliers"
            className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-full bg-brand-primary px-5 text-sm font-semibold text-white hover:bg-brand-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
          >
            استكشف الموردين
          </Link>
        </header>

        {state.status === 'loading' ? (
          <p className="text-sm text-brand-text-muted">جاري تحميل الشركاء...</p>
        ) : null}

        {state.status === 'ready' && suppliers.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-brand-border bg-brand-background px-4 py-8 text-center text-sm text-brand-text-muted">
            سيتم عرض الموردين المنشورين هنا بعد اعتماد المالك.
          </p>
        ) : null}

        {suppliers.length > 0 ? (
          <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {suppliers.map((supplier) => (
              <li key={supplier.slug} className="rounded-3xl border border-brand-border bg-brand-background p-5">
                <img
                  src={supplier.logo}
                  alt=""
                  className="h-14 w-14 rounded-2xl object-cover"
                  loading="lazy"
                />
                <h3 className="mt-3 font-semibold text-brand-black">{supplier.name}</h3>
                <p className="text-sm text-brand-text-muted">{supplier.category ?? supplier.specialties[0]}</p>
                {supplier.short_description ? (
                  <p className="mt-2 line-clamp-3 text-sm leading-7 text-brand-text-muted">
                    {supplier.short_description}
                  </p>
                ) : null}
                <Link
                  to={supplierPath(supplier.slug)}
                  className="mt-4 inline-flex min-h-10 items-center text-sm font-semibold text-brand-primary underline"
                >
                  عرض التفاصيل
                </Link>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </section>
  )
}
