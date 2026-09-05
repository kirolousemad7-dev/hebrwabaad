import { Link, useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../components/catalog/CatalogStatus'
import { SupplierPortfolioGrid } from '../components/suppliers/SupplierPortfolioGrid'
import { useAsyncData } from '../hooks/useAsyncData'
import { getPublicSupplier } from '../services/suppliers'

type SupplierDetailBodyProps = {
  slug: string
}

function SupplierDetailBody({ slug }: SupplierDetailBodyProps) {
  const { state, reload } = useAsyncData(() => getPublicSupplier(slug))

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل المورد..." />
  }

  if (state.status === 'error') {
    if (state.message.toLowerCase().includes('not found') || state.message.includes('404')) {
      return (
        <CatalogEmptyState
          title="لم نجد هذا المورد."
          description="قد يكون غير منشور أو أن الرابط غير صحيح."
          actions={[{ to: '/suppliers', label: 'كل الموردين', variant: 'primary' }]}
        />
      )
    }

    return <CatalogErrorState message={`تعذر تحميل المورد. ${state.message}`} onRetry={() => void reload()} />
  }

  const supplier = state.data
  const portfolio = supplier.portfolio ?? supplier.portfolio_preview ?? []

  return (
    <article className="space-y-8">
      <header className="flex min-w-0 flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-start">
        <img
          src={supplier.logo}
          alt={`شعار ${supplier.name}`}
          width={80}
          height={80}
          className="h-20 w-20 shrink-0 rounded-2xl object-cover"
        />
        <div className="min-w-0 space-y-2">
          <p className="text-sm font-medium text-slate-500">المورد</p>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-2xl font-semibold">{supplier.name}</h1>
            {supplier.featured ? (
              <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">مميّز</span>
            ) : null}
          </div>
          <p className="text-sm text-slate-500">{supplier.location}</p>
          <p className="max-w-2xl text-sm leading-7 text-slate-600">{supplier.description ?? supplier.short_description}</p>
        </div>
      </header>

      <section className="space-y-2">
        <h2 className="text-xl font-semibold">التخصصات</h2>
        <ul className="flex flex-wrap gap-2">
          {supplier.specialties.map((specialty) => (
            <li key={specialty} className="rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-800">
              {specialty}
            </li>
          ))}
        </ul>
      </section>

      <section className="space-y-2">
        <h2 className="text-xl font-semibold">الخدمات</h2>
        <ul className="flex flex-wrap gap-2">
          {supplier.services.map((service) => (
            <li key={service} className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700">
              {service}
            </li>
          ))}
        </ul>
      </section>

      <SupplierPortfolioGrid items={portfolio} />

      <p className="text-sm text-slate-600">
        <Link
          to="/suppliers"
          className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          العودة إلى الموردين
        </Link>
      </p>
    </article>
  )
}

export function SupplierDetailPage() {
  const { slug = '' } = useParams()

  if (slug === '') {
    return (
      <CatalogEmptyState
        title="لم نجد هذا المورد."
        description="يمكنك العودة إلى قائمة الموردين."
        actions={[{ to: '/suppliers', label: 'كل الموردين', variant: 'primary' }]}
      />
    )
  }

  return <SupplierDetailBody key={slug} slug={slug} />
}
