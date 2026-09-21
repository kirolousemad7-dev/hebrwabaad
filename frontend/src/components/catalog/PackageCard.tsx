import { useId, useState } from 'react'
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion'
import type { Package } from '../../types/api'
import {
  formatDuration,
  formatMoney,
  packageHasDiscount,
  packagePriceLabel,
  SERVICE_CATEGORY_LABELS,
} from '../../utils/catalog'
import { resolveMediaUrl } from '../../utils/mediaUrl'
import { PACKAGE_ORDER_COPY } from '../../utils/orderIntent'
import { fadeUp, motionOrReduced } from '../../utils/marketingMotion'
import type { CatalogTone } from './CatalogHero'
import { PackageDetails } from './PackageDetails'
import { PackageOrderCta } from './PackageOrderCta'

type PackageCardProps = {
  pkg: Package
  tone?: CatalogTone
  /** When provided, parent controls accordion (only one open). */
  open?: boolean
  onToggle?: () => void
}

export function PackageCard({ pkg, open: openProp, onToggle }: PackageCardProps) {
  const [internalOpen, setInternalOpen] = useState(false)
  const controlled = typeof openProp === 'boolean'
  const open = controlled ? openProp : internalOpen
  const detailsId = useId()
  const reduceMotion = useReducedMotion()
  const discounted = packageHasDiscount(pkg)
  const duration = formatDuration(pkg.duration_days)
  const imageSrc = resolveMediaUrl(pkg.image_url)
  const featured = Boolean(pkg.is_featured)

  function toggle() {
    if (onToggle) {
      onToggle()
      return
    }
    setInternalOpen((current) => !current)
  }

  return (
    <motion.article
      layout={!reduceMotion}
      variants={motionOrReduced(reduceMotion, fadeUp)}
      initial="hidden"
      whileInView="show"
      viewport={{ once: true, amount: 0.12 }}
      className={[
        'group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] border bg-white transition duration-300',
        'hover:-translate-y-2 hover:shadow-[0_28px_60px_-36px_rgba(17,19,24,0.45)]',
        'focus-within:-translate-y-2 motion-reduce:hover:translate-y-0 motion-reduce:focus-within:translate-y-0',
        featured ? 'border-brand-ink-900 shadow-card lg:scale-[1.02]' : 'border-brand-ink-100',
      ].join(' ')}
    >
      {featured ? (
        <span className="absolute top-0 inset-inline-start-0 z-10 h-full w-1 bg-brand-cobalt-500" aria-hidden="true" />
      ) : null}

      {imageSrc ? (
        <div className="relative aspect-[16/10] overflow-hidden bg-brand-ink-900">
          <img
            src={imageSrc}
            alt=""
            className="h-full w-full object-cover transition duration-700 group-hover:scale-[1.04] motion-reduce:group-hover:scale-100"
            loading="lazy"
          />
          {featured ? (
            <span className="absolute top-4 inset-inline-start-4 rounded-full bg-white/95 px-3 py-1 text-xs font-medium text-brand-ink-700">
              موصى بها
            </span>
          ) : null}
        </div>
      ) : featured ? (
        <div className="flex items-center gap-2 border-b border-brand-ink-100 px-6 py-3">
          <span className="h-1.5 w-1.5 rounded-full bg-brand-cobalt-500" aria-hidden="true" />
          <span className="text-xs font-medium text-brand-ink-500">موصى بها</span>
        </div>
      ) : null}

      <div className="flex flex-1 flex-col gap-4 p-6 sm:p-7">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <h3 className="text-xl font-semibold text-brand-ink-900 sm:text-2xl">{pkg.name}</h3>
          {discounted ? (
            <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800">
              خصم {formatMoney(pkg.discount_amount, pkg.currency)}
            </span>
          ) : null}
        </div>

        {pkg.description ? (
          <p className="line-clamp-3 text-sm leading-7 text-brand-ink-500">{pkg.description}</p>
        ) : null}

        {pkg.audience ? <p className="text-xs text-brand-ink-300">لمن؟ {pkg.audience}</p> : null}

        <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1 border-y border-brand-ink-100 py-4">
          <span className="whitespace-nowrap text-3xl font-bold tracking-tight text-brand-ink-900">
            {packagePriceLabel(pkg)}
          </span>
          {discounted && pkg.pricing_mode === 'FIXED' ? (
            <span className="text-sm text-brand-ink-300 line-through">{formatMoney(pkg.price, pkg.currency)}</span>
          ) : null}
          {duration ? <span className="text-sm text-brand-ink-500">التسليم خلال {duration}</span> : null}
        </div>

        {pkg.items.length > 0 ? (
          <ul className="space-y-2.5 text-sm text-brand-ink-500">
            {pkg.items.slice(0, 4).map((item) => (
              <li key={item.id} className="flex items-start justify-between gap-3">
                <span className="flex gap-2">
                  <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-ink-300" aria-hidden="true" />
                  {item.service?.name ?? 'خدمة'}
                  {item.quantity > 1 ? ` × ${item.quantity}` : ''}
                </span>
                {item.service ? (
                  <span className="shrink-0 text-xs text-brand-ink-300">
                    {SERVICE_CATEGORY_LABELS[item.service.category]}
                  </span>
                ) : null}
              </li>
            ))}
            {pkg.items.length > 4 ? (
              <li className="ps-4 text-xs text-brand-ink-300">+{pkg.items.length - 4} خدمات إضافية</li>
            ) : null}
          </ul>
        ) : (
          <p className="text-sm text-brand-ink-500">تُحدَّد الخدمات عند الطلب.</p>
        )}

        <AnimatePresence initial={false}>
          {open ? (
            <motion.div
              id={detailsId}
              key="details"
              initial={reduceMotion ? false : { opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              exit={reduceMotion ? undefined : { opacity: 0, height: 0 }}
              transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
              className="overflow-hidden"
            >
              <div className="border-t border-brand-ink-100 pt-4">
                <PackageDetails pkg={pkg} />
              </div>
            </motion.div>
          ) : null}
        </AnimatePresence>

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
            aria-expanded={open}
            aria-controls={detailsId}
            onClick={toggle}
            className="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-brand-ink-100 px-4 text-sm text-brand-ink-700 transition hover:border-brand-ink-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
          >
            {open ? 'إخفاء التفاصيل' : 'عرض التفاصيل'}
          </button>
        </div>
      </div>
    </motion.article>
  )
}
