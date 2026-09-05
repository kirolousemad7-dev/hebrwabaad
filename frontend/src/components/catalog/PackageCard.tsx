import { useId, useState } from 'react'
import type { Package } from '../../types/api'
import {
  formatDuration,
  formatMoney,
  packageHasDiscount,
  packagePriceLabel,
  SERVICE_CATEGORY_LABELS,
} from '../../utils/catalog'
import { PACKAGE_ORDER_COPY } from '../../utils/orderIntent'
import type { CatalogTone } from './CatalogHero'
import { PackageDetails } from './PackageDetails'
import { PackageOrderCta } from './PackageOrderCta'

type PackageCardProps = {
  pkg: Package
  tone?: CatalogTone
}

const featuredRing: Record<CatalogTone, string> = {
  marketing: 'border-amber-300 ring-2 ring-amber-200',
  events: 'border-amber-300 ring-2 ring-amber-200',
  printing: 'border-amber-300 ring-2 ring-amber-200',
  suppliers: 'border-amber-300 ring-2 ring-amber-200',
}

export function PackageCard({ pkg, tone = 'marketing' }: PackageCardProps) {
  const [open, setOpen] = useState(Boolean(pkg.is_featured))
  const detailsId = useId()
  const discounted = packageHasDiscount(pkg)
  const duration = formatDuration(pkg.duration_days)

  return (
    <article
      className={[
        'flex h-full flex-col gap-4 rounded-2xl border bg-white p-5 shadow-sm sm:p-6',
        pkg.is_featured ? featuredRing[tone] : 'border-slate-200',
      ].join(' ')}
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <h3 className="text-lg font-semibold">{pkg.name}</h3>
        <div className="flex flex-wrap gap-2">
          {pkg.is_featured ? (
            <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-900">
              الأكثر طلباً
            </span>
          ) : null}
          {discounted ? (
            <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800">
              خصم {formatMoney(pkg.discount_amount, pkg.currency)}
            </span>
          ) : null}
        </div>
      </div>

      {pkg.description ? (
        <p className="line-clamp-3 text-sm leading-7 text-slate-600">{pkg.description}</p>
      ) : null}

      {pkg.audience ? <p className="text-xs text-slate-500">لمن؟ {pkg.audience}</p> : null}

      <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <span className="whitespace-nowrap text-2xl font-semibold">{packagePriceLabel(pkg)}</span>
        {discounted && pkg.pricing_mode === 'FIXED' ? (
          <span className="text-sm text-slate-400 line-through">{formatMoney(pkg.price, pkg.currency)}</span>
        ) : null}
        {duration ? <span className="text-sm text-slate-500">التسليم خلال {duration}</span> : null}
      </div>

      {pkg.items.length > 0 ? (
        <ul className="space-y-2 text-sm text-slate-700">
          {pkg.items.slice(0, open ? pkg.items.length : 4).map((item) => (
            <li key={item.id} className="flex items-start justify-between gap-3">
              <span>
                {item.service?.name ?? 'خدمة'}
                {item.quantity > 1 ? ` × ${item.quantity}` : ''}
              </span>
              {item.service ? (
                <span className="shrink-0 text-xs text-slate-400">
                  {SERVICE_CATEGORY_LABELS[item.service.category]}
                </span>
              ) : null}
            </li>
          ))}
          {!open && pkg.items.length > 4 ? (
            <li className="text-xs text-slate-500">+{pkg.items.length - 4} خدمات إضافية</li>
          ) : null}
        </ul>
      ) : (
        <p className="text-sm text-slate-500">تُحدَّد الخدمات عند الطلب.</p>
      )}

      {open ? (
        <div id={detailsId}>
          <PackageDetails pkg={pkg} />
        </div>
      ) : null}

      <div className="mt-auto flex flex-col gap-2 pt-2 sm:flex-row">
        <PackageOrderCta
          slug={pkg.slug}
          label={pkg.is_chargeable ? PACKAGE_ORDER_COPY.order : PACKAGE_ORDER_COPY.requestQuote}
        />
        <button
          type="button"
          aria-expanded={open}
          aria-controls={detailsId}
          onClick={() => setOpen((current) => !current)}
          className="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {open ? 'إخفاء التفاصيل' : 'عرض التفاصيل'}
        </button>
      </div>
    </article>
  )
}
