import { useId, useState } from 'react'
import { Link } from 'react-router-dom'
import { motion, useReducedMotion } from 'framer-motion'
import type { MarketingVisual } from '../../utils/marketingVisuals'
import { BrandVisual } from './BrandVisual'

export type InteractiveServiceCardProps = {
  title: string
  body: string
  href: string
  visual: MarketingVisual
  features?: string[]
  index?: number
}

export function InteractiveServiceCard({
  title,
  body,
  href,
  visual,
  features = [],
  index = 0,
}: InteractiveServiceCardProps) {
  const reduceMotion = useReducedMotion()
  const [expanded, setExpanded] = useState(false)
  const panelId = useId()

  return (
    <motion.article
      initial={reduceMotion ? false : { opacity: 0, y: 18 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, amount: 0.25 }}
      transition={{ delay: reduceMotion ? 0 : index * 0.06, duration: 0.45 }}
      className="group relative flex h-full flex-col overflow-hidden rounded-3xl border border-brand-ink-100 bg-white shadow-sm transition duration-300 hover:-translate-y-1.5 hover:border-brand-cobalt-300 hover:shadow-card focus-within:border-brand-cobalt-300"
    >
      <div className="relative aspect-[16/10] overflow-hidden">
        <div className="h-full w-full transition duration-500 group-hover:scale-[1.04] motion-reduce:transition-none motion-reduce:group-hover:scale-100">
          <BrandVisual visual={visual} />
        </div>
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-brand-ink-900/55 via-transparent to-transparent opacity-80" />
        <span className="absolute bottom-3 inset-inline-start-3 brand-section-marker w-10" aria-hidden="true" />
      </div>

      <div className="flex flex-1 flex-col gap-3 p-5 sm:p-6">
        <h3 className="text-lg font-semibold text-brand-ink-900 transition group-hover:text-brand-cobalt-700">
          {title}
        </h3>
        <p className="line-clamp-3 flex-1 text-sm leading-7 text-brand-ink-500">{body}</p>

        {features.length > 0 ? (
          <div>
            <button
              type="button"
              className="text-xs font-medium text-brand-cobalt-700 underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500 lg:hidden"
              aria-expanded={expanded}
              aria-controls={panelId}
              onClick={() => setExpanded((value) => !value)}
            >
              {expanded ? 'إخفاء التفاصيل' : 'عرض التفاصيل'}
            </button>
            <ul
              id={panelId}
              className={[
                'mt-2 space-y-1.5 text-xs leading-6 text-brand-ink-500',
                expanded ? 'block' : 'hidden',
                'lg:block lg:max-h-0 lg:overflow-hidden lg:opacity-0 lg:transition-all lg:duration-300',
                'lg:group-hover:max-h-40 lg:group-hover:opacity-100',
                'lg:group-focus-within:max-h-40 lg:group-focus-within:opacity-100',
              ].join(' ')}
            >
              {features.map((feature) => (
                <li key={feature} className="flex gap-2">
                  <span className="mt-2 h-1 w-1 shrink-0 rounded-full bg-brand-cobalt-500" aria-hidden="true" />
                  <span>{feature}</span>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <Link
          to={href}
          className="mt-auto inline-flex min-h-10 items-center justify-center rounded-xl bg-brand-ink-900 px-4 text-sm font-medium text-white transition hover:bg-brand-cobalt-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
        >
          استكشف الخدمة
        </Link>
      </div>
    </motion.article>
  )
}
