import type { Package } from '../../types/api'
import {
  formatDuration,
  formatMoney,
  packagePriceLabel,
  SERVICE_CATEGORY_LABELS,
  tierPriceLabel,
} from '../../utils/catalog'
import { PACKAGE_ORDER_COPY } from '../../utils/orderIntent'
import { PackageOrderCta } from './PackageOrderCta'

type PackageDetailsProps = {
  pkg: Package
}

export function PackageDetails({ pkg }: PackageDetailsProps) {
  const duration = formatDuration(pkg.duration_days)

  return (
    <div className="space-y-4 border-t border-slate-100 pt-4 text-sm">
      {pkg.description ? (
        <p className="leading-7 text-slate-700">{pkg.description}</p>
      ) : null}

      {pkg.audience ? (
        <div className="rounded-md bg-slate-50 p-3">
          <p className="text-xs text-slate-500">لمن هذه الباقة؟</p>
          <p className="mt-1 leading-7 text-slate-700">{pkg.audience}</p>
        </div>
      ) : null}

      <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div className="rounded-md bg-slate-50 p-3">
          <dt className="text-xs text-slate-500">السعر</dt>
          <dd className="mt-1 font-semibold">{packagePriceLabel(pkg)}</dd>
        </div>
        <div className="rounded-md bg-slate-50 p-3">
          <dt className="text-xs text-slate-500">الخصم</dt>
          <dd className="mt-1 font-semibold">
            {pkg.pricing_mode === 'FIXED' && Number.parseFloat(pkg.discount_amount) > 0
              ? formatMoney(pkg.discount_amount, pkg.currency)
              : 'بدون خصم'}
          </dd>
        </div>
        <div className="rounded-md bg-slate-50 p-3">
          <dt className="text-xs text-slate-500">مدة التسليم</dt>
          <dd className="mt-1 font-semibold">{duration ?? 'تُحدد حسب النطاق'}</dd>
        </div>
        <div className="rounded-md bg-slate-50 p-3">
          <dt className="text-xs text-slate-500">جولات التعديل</dt>
          <dd className="mt-1 font-semibold">
            {pkg.revision_rounds === null ? 'تُحدد حسب النطاق' : `${pkg.revision_rounds} جولة`}
          </dd>
        </div>
      </dl>

      {pkg.deliverables.length > 0 ? (
        <div className="space-y-2">
          <p className="text-xs font-semibold text-slate-500">مكونات الحل</p>
          <ul className="flex flex-wrap gap-2">
            {pkg.deliverables.map((deliverable) => (
              <li key={deliverable} className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                {deliverable}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {pkg.tiers.length > 0 ? (
        <div className="space-y-2">
          <p className="text-xs font-semibold text-slate-500">مستويات الباقة</p>
          <ul className="grid gap-3 sm:grid-cols-3">
            {pkg.tiers.map((tier) => (
              <li key={tier.id} className="flex flex-col gap-2 rounded-md border border-slate-200 p-3">
                <div className="flex items-baseline justify-between gap-2">
                  <span className="font-medium">{tier.name}</span>
                  <span className="text-xs text-slate-600">{tierPriceLabel(tier)}</span>
                </div>
                <p className="text-xs leading-6 text-slate-500">
                  {tier.description ?? PACKAGE_ORDER_COPY.tierPending}
                </p>
                <dl className="text-xs text-slate-500">
                  <div className="flex justify-between gap-2">
                    <dt>المدة</dt>
                    <dd>{formatDuration(tier.duration_days) ?? '—'}</dd>
                  </div>
                  <div className="flex justify-between gap-2">
                    <dt>جولات التعديل</dt>
                    <dd>{tier.revision_rounds === null ? '—' : `${tier.revision_rounds} جولة`}</dd>
                  </div>
                </dl>
                {tier.deliverables.length > 0 ? (
                  <ul className="space-y-1 text-xs text-slate-600">
                    {tier.deliverables.map((deliverable) => (
                      <li key={deliverable}>• {deliverable}</li>
                    ))}
                  </ul>
                ) : null}
                <div className="mt-auto flex">
                  <PackageOrderCta
                    slug={pkg.slug}
                    tierSlug={tier.slug}
                    variant="secondary"
                    label={tier.is_priced ? `اطلب ${tier.name}` : `اطلب تسعير ${tier.name}`}
                  />
                </div>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {pkg.items.length > 0 ? (
        <div className="space-y-2">
          <p className="text-xs font-semibold text-slate-500">تفاصيل الخدمات المشمولة</p>
          <ul className="space-y-2">
            {pkg.items.map((item) => (
              <li key={item.id} className="rounded-md border border-slate-100 p-3">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <span className="font-medium">{item.service?.name ?? 'خدمة'}</span>
                  <span className="text-xs text-slate-500">الكمية: {item.quantity}</span>
                </div>
                {item.service ? (
                  <p className="mt-1 text-xs text-slate-500">
                    {SERVICE_CATEGORY_LABELS[item.service.category]}
                    {item.service.summary ? ` · ${item.service.summary}` : ''}
                  </p>
                ) : null}
                {item.notes ? <p className="mt-2 text-slate-600">{item.notes}</p> : null}
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  )
}
