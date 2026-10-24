import { useEffect } from 'react'
import { createPortal } from 'react-dom'
import type { Package } from '../../types/api'
import {
  PACKAGE_CATEGORY_LABELS,
  discountPresentation,
  formatDuration,
  packageHasDiscount,
  packagePriceLabel,
  tierPriceLabel,
} from '../../utils/catalog'
import { PACKAGE_ORDER_COPY } from '../../utils/orderIntent'
import { resolveMediaUrl } from '../../utils/mediaUrl'
import { BRAND_MARK_SRC } from '../../utils/brand'
import { PackageOrderCta } from './PackageOrderCta'
import { PackageVisual } from './PackageVisual'
import { SarAmount } from '../ui/SarAmount'

type PackageDetailsModalProps = {
  pkg: Package | null
  onClose: () => void
}

export function PackageDetailsModal({ pkg, onClose }: PackageDetailsModalProps) {
  useEffect(() => {
    if (!pkg) {
      return
    }

    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose()
      }
    }

    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', onKey)

    return () => {
      document.body.style.overflow = previous
      window.removeEventListener('keydown', onKey)
    }
  }, [onClose, pkg])

  if (!pkg) {
    return null
  }

  const discounted = packageHasDiscount(pkg) && pkg.pricing_mode !== 'QUOTE'
  const offer = discounted
    ? discountPresentation(Number.parseFloat(pkg.price), Number.parseFloat(pkg.final_price))
    : null
  const duration = formatDuration(pkg.duration_days)
  const quote = pkg.pricing_mode === 'QUOTE' || Number.parseFloat(pkg.final_price) <= 0
  const included = pkg.items
  const excluded = pkg.excluded_service_names ?? []
  const imageSrc = resolveMediaUrl(pkg.image_url)

  return createPortal(
    <div
      className="fixed inset-0 z-[90] flex items-end justify-center bg-brand-ink-900/55 p-4 sm:items-center"
      role="presentation"
      onClick={onClose}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="package-details-title"
        className="relative flex max-h-[min(92vh,52rem)] w-full max-w-[860px] min-w-0 flex-col overflow-hidden rounded-t-3xl bg-brand-paper shadow-2xl sm:rounded-3xl"
        onClick={(event) => event.stopPropagation()}
      >
        <button
          type="button"
          onClick={onClose}
          className="absolute top-3 inset-inline-end-3 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-ink-100 bg-white text-lg text-brand-ink-700 shadow-sm hover:bg-brand-paper"
          aria-label="إغلاق"
        >
          ×
        </button>

        <div className="min-w-0 overflow-y-auto">
          <PackageVisual src={imageSrc} name={pkg.name} variant="banner" />
          <header className="flex items-start gap-3 border-b border-brand-ink-100 bg-white px-5 py-4 pe-16 sm:px-6">
            {imageSrc ? null : (
              <img src={BRAND_MARK_SRC} alt="" className="mt-0.5 h-9 w-auto shrink-0 object-contain" />
            )}
            <div className="min-w-0 space-y-1">
              <p className="text-xs text-brand-ink-500">{PACKAGE_CATEGORY_LABELS[pkg.category]}</p>
              <h2 id="package-details-title" className="text-xl font-bold text-brand-ink-900 sm:text-2xl">
                {pkg.name}
              </h2>
            </div>
          </header>
          <div className="space-y-5 px-5 py-5 sm:px-6">
          {pkg.description ? <p className="text-sm leading-7 text-brand-ink-600">{pkg.description}</p> : null}
          {pkg.audience ? (
            <p className="text-sm leading-7 text-brand-ink-500">
              <span className="font-medium text-brand-ink-700">لمن؟ </span>
              {pkg.audience}
            </p>
          ) : null}

          <div className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-2xl border border-brand-ink-100 bg-white p-3">
              <p className="text-xs text-brand-ink-500">السعر</p>
              <p className="mt-1 text-lg font-bold text-brand-ink-900">
                {quote ? (
                  packagePriceLabel(pkg)
                ) : pkg.pricing_mode === 'STARTING_FROM' ? (
                  <span className="inline-flex flex-wrap items-baseline gap-2">
                    <span className="text-sm font-medium">يبدأ من</span>
                    <SarAmount amount={pkg.final_price} />
                  </span>
                ) : (
                  <SarAmount amount={pkg.final_price} />
                )}
              </p>
              {offer && !quote ? (
                <p className="mt-1">
                  <SarAmount amount={pkg.price} struck className="text-sm" />
                </p>
              ) : null}
              {offer && !quote ? (
                <p className="mt-1 text-sm font-medium text-red-600">
                  {offer.percent !== null ? (
                    `خصم ${offer.percent}%`
                  ) : (
                    <>
                      خصم <SarAmount amount={offer.amount} className="text-red-600" />
                    </>
                  )}
                </p>
              ) : null}
            </div>
            <div className="rounded-2xl border border-brand-ink-100 bg-white p-3">
              <p className="text-xs text-brand-ink-500">مدة التنفيذ</p>
              <p className="mt-1 font-semibold text-brand-ink-900">{duration ?? 'تُحدد حسب النطاق'}</p>
            </div>
            <div className="rounded-2xl border border-brand-ink-100 bg-white p-3">
              <p className="text-xs text-brand-ink-500">جولات التعديل</p>
              <p className="mt-1 font-semibold text-brand-ink-900">
                {pkg.revision_rounds === null ? 'تُحدد حسب النطاق' : `${pkg.revision_rounds} جولة`}
              </p>
            </div>
          </div>

          {pkg.deliverables.length > 0 ? (
            <div>
              <h3 className="text-sm font-semibold text-brand-ink-900">ملاحظات وتسليمات</h3>
              <ul className="mt-2 flex flex-wrap gap-2">
                {pkg.deliverables.map((item) => (
                  <li key={item} className="rounded-full bg-white px-3 py-1 text-xs text-brand-ink-700">
                    {item}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          <div className="grid gap-4 lg:grid-cols-2">
            <div className="min-w-0 rounded-2xl border border-brand-ink-100 bg-white p-4">
              <h3 className="text-sm font-semibold text-brand-ink-900">الخدمات المضمنة</h3>
              {included.length > 0 ? (
                <ul className="mt-3 space-y-2 text-sm">
                  {included.map((item) => (
                    <li key={item.id} className="flex items-start justify-between gap-3">
                      <span className="flex min-w-0 gap-2 text-brand-ink-800">
                        <span className="text-emerald-700" aria-hidden="true">
                          ✓
                        </span>
                        <span>
                          {item.service?.name ?? 'خدمة'}
                          {item.quantity > 1 ? ` × ${item.quantity}` : ''}
                          {item.notes ? <span className="mt-1 block text-xs leading-5 text-brand-ink-500">{item.notes}</span> : null}
                        </span>
                      </span>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="mt-3 text-sm text-brand-ink-500">تُحدَّد الخدمات عند الطلب.</p>
              )}
            </div>
            <div className="min-w-0 rounded-2xl border border-brand-ink-100 bg-white p-4">
              <h3 className="text-sm font-semibold text-brand-ink-900">غير المضمنة</h3>
              {excluded.length > 0 ? (
                <ul className="mt-3 space-y-2 text-sm">
                  {excluded.map((name) => (
                    <li key={name} className="flex gap-2 text-brand-ink-500">
                      <span className="text-red-600" aria-hidden="true">
                        ✕
                      </span>
                      <span>{name}</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="mt-3 text-sm text-brand-ink-500">لا توجد خدمات مستبعدة في بيانات هذه الباقة.</p>
              )}
            </div>
          </div>

          {pkg.tiers.length > 0 ? (
            <div>
              <h3 className="text-sm font-semibold text-brand-ink-900">مستويات الباقة</h3>
              <ul className="mt-3 grid gap-3 sm:grid-cols-2">
                {pkg.tiers.map((tier) => (
                  <li key={tier.id} className="rounded-2xl border border-brand-ink-100 bg-white p-3 text-sm">
                    <div className="flex items-baseline justify-between gap-2">
                      <span className="font-medium text-brand-ink-900">{tier.name}</span>
                      <span className="text-xs text-brand-ink-500">{tier.is_priced ? tierPriceLabel(tier) : 'تسعير'}</span>
                    </div>
                    {tier.description ? <p className="mt-1 text-xs leading-6 text-brand-ink-500">{tier.description}</p> : null}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
          </div>
        </div>

        <footer className="border-t border-brand-ink-100 bg-white px-5 py-4 sm:px-6">
          <PackageOrderCta
            slug={pkg.slug}
            packageId={pkg.id}
            packageName={pkg.name}
            pricingMode={pkg.pricing_mode}
            requiresQuote={!pkg.is_chargeable || pkg.pricing_mode === 'QUOTE'}
            label={pkg.is_chargeable && pkg.pricing_mode !== 'QUOTE' ? PACKAGE_ORDER_COPY.order : PACKAGE_ORDER_COPY.requestQuote}
          />
        </footer>
      </div>
    </div>,
    document.body,
  )
}
