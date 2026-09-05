import { Link } from 'react-router-dom'

export type CatalogTone = 'marketing' | 'events' | 'printing' | 'suppliers'

type CatalogHeroProps = {
  tone: CatalogTone
  eyebrow: string
  title: string
  description: string
  primaryCta: string
  secondaryCta?: string
  secondaryTo?: string
  packagesAnchor: string
  packageCount: number | null
  emptyCountLabel: string
  countLabel: (count: number) => string
}

const toneClasses: Record<CatalogTone, { section: string; eyebrow: string; primary: string }> = {
  marketing: {
    section: 'bg-slate-900',
    eyebrow: 'text-amber-300',
    primary: 'bg-amber-400 text-slate-900',
  },
  events: {
    section: 'bg-slate-900',
    eyebrow: 'text-amber-300',
    primary: 'bg-amber-400 text-slate-900',
  },
  printing: {
    section: 'bg-slate-900',
    eyebrow: 'text-amber-300',
    primary: 'bg-amber-400 text-slate-900',
  },
  suppliers: {
    section: 'bg-slate-900',
    eyebrow: 'text-amber-300',
    primary: 'bg-amber-400 text-slate-900',
  },
}

export function CatalogHero({
  tone,
  eyebrow,
  title,
  description,
  primaryCta,
  secondaryCta = 'تصفح خدماتنا',
  secondaryTo = '/services',
  packagesAnchor,
  packageCount,
  emptyCountLabel,
  countLabel,
}: CatalogHeroProps) {
  const classes = toneClasses[tone]

  return (
    <section className={`-mx-4 -mt-8 px-4 py-12 text-white sm:-mx-6 sm:-mt-10 sm:px-6 sm:py-16 ${classes.section}`}>
      <div className="mx-auto max-w-5xl space-y-6">
        <p className={`text-sm font-medium tracking-wide ${classes.eyebrow}`}>{eyebrow}</p>
        <h1 className="max-w-3xl text-3xl font-semibold leading-snug sm:text-4xl">{title}</h1>
        <p className="max-w-2xl text-sm leading-7 text-white/75 sm:text-base">{description}</p>
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
          <a
            href={`#${packagesAnchor}`}
            className={`inline-flex min-h-11 items-center justify-center rounded-lg px-5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white ${classes.primary}`}
          >
            {primaryCta}
          </a>
          <Link
            to={secondaryTo}
            className="inline-flex min-h-11 items-center justify-center rounded-lg border border-white/40 px-5 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
          >
            {secondaryCta}
          </Link>
        </div>
        {packageCount !== null ? (
          <p className="text-xs text-white/55">
            {packageCount === 0 ? emptyCountLabel : countLabel(packageCount)}
          </p>
        ) : null}
      </div>
    </section>
  )
}
