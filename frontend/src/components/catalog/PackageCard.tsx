import { useState } from 'react'
import { motion, useReducedMotion } from 'framer-motion'
import type { Package } from '../../types/api'
import {
  discountPresentation,
  formatDuration,
  packageHasDiscount,
  packagePriceLabel,
  SERVICE_CATEGORY_LABELS,
} from '../../utils/catalog'
import { resolveMediaUrl } from '../../utils/mediaUrl'
import { PACKAGE_ORDER_COPY } from '../../utils/orderIntent'
import { fadeUp, motionOrReduced } from '../../utils/marketingMotion'
import type { CatalogTone } from './CatalogHero'
import { PackageDetailsModal } from './PackageDetailsModal'
import { PackageOrderCta } from './PackageOrderCta'
import { PackageVisual } from './PackageVisual'
import { MediaLightbox } from '../ui/MediaLightbox'
import { SarAmount } from '../ui/SarAmount'

type PackageCardProps = {
  pkg: Package
  tone?: CatalogTone
}

export function PackageCard({ pkg }: PackageCardProps) {
  const [detailsOpen, setDetailsOpen] = useState(false)
  const reduceMotion = useReducedMotion()
  const discounted = packageHasDiscount(pkg) && pkg.pricing_mode !== 'QUOTE'
  const duration = formatDuration(pkg.duration_days)
  const offer = discounted
    ? discountPresentation(Number.parseFloat(pkg.price), Number.parseFloat(pkg.final_price))
    : null
  const imageSrc = resolveMediaUrl(pkg.image_url)
  const featured = Boolean(pkg.is_featured)
  const [imageOpen, setImageOpen] = useState(false)

  return (
    <motion.article
      layout={!reduceMotion}
      variants={motionOrReduced(reduceMotion, fadeUp)}
      initial="hidden"
      whileInView="show"
      viewport={{ once: true, amount: 0.12 }}
      className={[
        'group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] border bg-white transition duration-300',
        'hover:shadow-[0_28px_60px_-36px_rgba(17,19,24,0.45)]',
        featured ? 'border-brand-ink-900 shadow-card' : 'border-brand-ink-100',
      ].join(' ')}
    >
      {featured ? (
        <span className="absolute top-0 inset-inline-start-0 z-10 h-full w-1 bg-brand-cobalt-500" aria-hidden="true" />
      ) : null}

      <PackageVisual
        src={imageSrc}
        name={pkg.name}
        featured={featured}
        onOpen={imageSrc ? () => setImageOpen(true) : undefined}
      />

      <div className="flex flex-1 flex-col gap-4 p-6 sm:p-7">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <h3 className="text-xl font-semibold text-brand-ink-900 sm:text-2xl">{pkg.name}</h3>
          {offer ? (
            <span className="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
              {offer.percent !== null ? (
                `خصم ${offer.percent}%`
              ) : (
                <>
                  خصم <SarAmount amount={offer.amount} className="text-red-700" />
                </>
              )}
            </span>
          ) : null}
        </div>

        <p className="line-clamp-2 min-h-12 text-sm leading-6 text-brand-ink-500">{pkg.description || ' '}</p>

        <p className="line-clamp-1 min-h-5 text-xs text-brand-ink-300">{pkg.audience ? `لمن؟ ${pkg.audience}` : '\u00a0'}</p>

        <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1 border-y border-brand-ink-100 py-4">
          <span className="whitespace-nowrap text-3xl font-bold tracking-tight text-brand-ink-900">
            {pkg.pricing_mode === 'QUOTE' || Number.parseFloat(pkg.final_price) <= 0 ? (
              packagePriceLabel(pkg)
            ) : pkg.pricing_mode === 'STARTING_FROM' ? (
              <span className="inline-flex items-baseline gap-2">
                <span>يبدأ من</span>
                <SarAmount amount={pkg.final_price} />
              </span>
            ) : (
              <SarAmount amount={pkg.final_price} />
            )}
          </span>
          {offer ? <SarAmount amount={pkg.price} struck className="text-sm" /> : null}
          {duration ? <span className="text-sm text-brand-ink-500">التسليم خلال {duration}</span> : null}
        </div>

        {pkg.items.length > 0 ? (
          <ul className="min-h-[4.5rem] space-y-2.5 text-sm text-brand-ink-500">
            {pkg.items.slice(0, 3).map((item) => (
              <li key={item.id} className="flex items-start justify-between gap-3">
                <span className="flex min-w-0 gap-2">
                  <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-ink-300" aria-hidden="true" />
                  <span className="line-clamp-1">
                    {item.service?.name ?? 'خدمة'}
                    {item.quantity > 1 ? ` × ${item.quantity}` : ''}
                  </span>
                </span>
                {item.service ? (
                  <span className="shrink-0 text-xs text-brand-ink-300">
                    {SERVICE_CATEGORY_LABELS[item.service.category]}
                  </span>
                ) : null}
              </li>
            ))}
            {pkg.items.length > 3 ? (
              <li className="ps-4 text-xs text-brand-ink-300">+{pkg.items.length - 3} خدمات إضافية</li>
            ) : null}
          </ul>
        ) : (
          <p className="text-sm text-brand-ink-500">تُحدَّد الخدمات عند الطلب.</p>
        )}

        <div className="mt-auto flex flex-col gap-2 pt-2 sm:flex-row">
          <PackageOrderCta
            slug={pkg.slug}
            packageId={pkg.id}
            packageName={pkg.name}
            pricingMode={pkg.pricing_mode}
            requiresQuote={!pkg.is_chargeable || pkg.pricing_mode === 'QUOTE'}
            label={
              pkg.is_chargeable && pkg.pricing_mode !== 'QUOTE'
                ? PACKAGE_ORDER_COPY.order
                : PACKAGE_ORDER_COPY.requestQuote
            }
          />
          <button
            type="button"
            onClick={() => setDetailsOpen(true)}
            className="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-brand-ink-100 px-4 text-sm text-brand-ink-700 transition hover:border-brand-ink-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
          >
            عرض التفاصيل
          </button>
        </div>
      </div>
      <MediaLightbox open={imageOpen} imageSrc={imageSrc} title={pkg.name} onClose={() => setImageOpen(false)} />
      <PackageDetailsModal pkg={detailsOpen ? pkg : null} onClose={() => setDetailsOpen(false)} />
    </motion.article>
  )
}
