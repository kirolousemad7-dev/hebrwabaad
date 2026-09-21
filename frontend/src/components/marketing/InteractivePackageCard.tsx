import { Link } from 'react-router-dom'
import { motion, useReducedMotion } from 'framer-motion'
import type { MarketingVisual } from '../../utils/marketingVisuals'
import { BrandVisual } from './BrandVisual'

export type InteractivePackageCardProps = {
  name: string
  body: string
  accent: string
  features: string[]
  href: string
  visual: MarketingVisual
  featured?: boolean
  index?: number
}

export function InteractivePackageCard({
  name,
  body,
  accent,
  features,
  href,
  visual,
  featured = false,
  index = 0,
}: InteractivePackageCardProps) {
  const reduceMotion = useReducedMotion()

  return (
    <motion.article
      initial={reduceMotion ? false : { opacity: 0, y: 16 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, amount: 0.25 }}
      transition={{ delay: reduceMotion ? 0 : index * 0.07, duration: 0.45 }}
      className={[
        'group relative flex h-full flex-col overflow-hidden rounded-3xl border shadow-sm transition duration-300',
        'hover:-translate-y-1.5 hover:shadow-card focus-within:-translate-y-1.5',
        featured
          ? 'border-brand-cobalt-500 bg-brand-ink-900 text-white ring-2 ring-brand-cobalt-500/30'
          : 'border-brand-ink-100 bg-white text-brand-ink-900 hover:border-brand-cobalt-300',
      ].join(' ')}
    >
      <div className="relative aspect-[16/9] overflow-hidden">
        <div className="h-full w-full transition duration-500 group-hover:scale-[1.03] motion-reduce:group-hover:scale-100">
          <BrandVisual visual={visual} />
        </div>
        <span
          className={[
            'absolute top-3 inset-inline-start-3 rounded-full px-3 py-1 text-xs font-medium',
            featured ? 'bg-brand-cobalt-500 text-white' : 'bg-white/95 text-brand-cobalt-700',
          ].join(' ')}
        >
          {accent}
        </span>
      </div>

      <div className="flex flex-1 flex-col gap-4 p-6">
        <h3 className="text-xl font-semibold">{name}</h3>
        <p className={`text-sm leading-7 ${featured ? 'text-white/75' : 'text-brand-ink-500'}`}>{body}</p>
        <ul className={`space-y-2 text-sm ${featured ? 'text-white/80' : 'text-brand-ink-500'}`}>
          {features.map((feature) => (
            <li key={feature} className="flex gap-2">
              <span
                className={`mt-2 h-1.5 w-1.5 shrink-0 rounded-full ${featured ? 'bg-brand-cobalt-300' : 'bg-brand-cobalt-500'}`}
                aria-hidden="true"
              />
              <span>{feature}</span>
            </li>
          ))}
        </ul>
        <Link
          to={href}
          className={[
            'mt-auto inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
            featured
              ? 'bg-brand-cobalt-500 text-white hover:bg-brand-cobalt-300 hover:text-brand-ink-900'
              : 'bg-brand-ink-900 text-white hover:bg-brand-cobalt-700',
          ].join(' ')}
        >
          عرض الباقات
        </Link>
      </div>
    </motion.article>
  )
}
