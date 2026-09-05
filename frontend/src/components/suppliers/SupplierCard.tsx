import { Link } from 'react-router-dom'
import { PublicCta } from '../public/PublicCta'
import type { Supplier } from '../../types/api'
import { supplierPath } from '../../utils/suppliers'

type SupplierCardProps = {
  supplier: Supplier
}

export function SupplierCard({ supplier }: SupplierCardProps) {
  const preview = supplier.portfolio_preview ?? supplier.portfolio?.slice(0, 2) ?? []
  const count = supplier.portfolio_count ?? supplier.portfolio?.length ?? preview.length

  return (
    <article className="flex h-full min-w-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="flex items-start gap-3 p-5">
        <img
          src={supplier.logo}
          alt={`شعار ${supplier.name}`}
          width={56}
          height={56}
          className="h-14 w-14 shrink-0 rounded-xl object-cover"
        />
        <div className="min-w-0 space-y-1">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="font-semibold">{supplier.name}</h3>
            {supplier.featured ? (
              <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">مميّز</span>
            ) : null}
          </div>
          <p className="text-sm text-slate-500">{supplier.location}</p>
        </div>
      </div>
      <div className="flex min-w-0 flex-1 flex-col gap-3 px-5 pb-5">
        <p className="text-sm leading-6 text-slate-600">{supplier.short_description}</p>
        <p className="text-xs font-medium text-slate-500">التخصصات</p>
        <ul className="flex flex-wrap gap-1.5">
          {supplier.specialties.map((specialty) => (
            <li key={specialty} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
              {specialty}
            </li>
          ))}
        </ul>
        <p className="text-xs font-medium text-slate-500">الخدمات</p>
        <p className="text-sm text-slate-600">{supplier.services.join('، ')}</p>
        {preview.length > 0 ? (
          <div className="space-y-2">
            <p className="text-xs font-medium text-slate-500">الأعمال ({count.toLocaleString('ar-SA')})</p>
            <div className="grid grid-cols-2 gap-2">
              {preview.map((item) => (
                <img
                  key={item.id}
                  src={item.image}
                  alt={item.title}
                  width={280}
                  height={175}
                  className="aspect-[16/10] w-full rounded-lg object-cover"
                />
              ))}
            </div>
          </div>
        ) : (
          <p className="text-sm text-slate-500">لا توجد أعمال معروضة بعد.</p>
        )}
        <div className="mt-auto flex flex-wrap gap-2 pt-1">
          <PublicCta to={supplierPath(supplier.slug)}>عرض المورد</PublicCta>
          <Link
            to={supplierPath(supplier.slug)}
            className="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            عرض الأعمال
          </Link>
        </div>
      </div>
    </article>
  )
}
