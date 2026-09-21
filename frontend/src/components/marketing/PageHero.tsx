import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { AnimatedSection } from './AnimatedSection'

type PageHeroProps = {
  eyebrow?: string
  title: string
  description?: string | null
  children?: ReactNode
  tone?: 'paper' | 'ink'
  breadcrumbs?: Array<{ label: string; to?: string }>
}

export function PageHero({
  eyebrow,
  title,
  description,
  children,
  tone = 'paper',
  breadcrumbs,
}: PageHeroProps) {
  const ink = tone === 'ink'

  return (
    <header
      className={[
        'relative overflow-hidden rounded-[1.75rem] border px-6 py-12 sm:px-10 sm:py-14',
        ink
          ? 'border-brand-ink-700 bg-brand-ink-900 text-white'
          : 'border-brand-ink-100 bg-brand-paper text-brand-ink-900',
      ].join(' ')}
    >
      <div
        className={[
          'pointer-events-none absolute inset-0',
          ink
            ? 'bg-[radial-gradient(circle_at_85%_15%,rgba(49,92,255,0.18),transparent_42%)]'
            : 'bg-[radial-gradient(circle_at_90%_10%,rgba(17,19,24,0.03),transparent_36%)]',
        ].join(' ')}
      />
      {ink ? (
        <div aria-hidden="true" className="absolute top-0 inset-inline-end-0 h-full w-1 bg-brand-cobalt-500" />
      ) : null}
      <AnimatedSection className="relative z-10 max-w-3xl space-y-5">
        {breadcrumbs && breadcrumbs.length > 0 ? (
          <nav aria-label="مسار التنقل" className="flex flex-wrap items-center gap-2 text-xs">
            {breadcrumbs.map((crumb, index) => (
              <span key={`${crumb.label}-${index}`} className="flex items-center gap-2">
                {index > 0 ? (
                  <span className={ink ? 'text-white/35' : 'text-brand-ink-300'} aria-hidden="true">
                    /
                  </span>
                ) : null}
                {crumb.to ? (
                  <Link
                    to={crumb.to}
                    className={ink ? 'text-white/65 hover:text-white' : 'text-brand-ink-500 hover:text-brand-ink-900'}
                  >
                    {crumb.label}
                  </Link>
                ) : (
                  <span className={ink ? 'text-white' : 'text-brand-ink-900'}>{crumb.label}</span>
                )}
              </span>
            ))}
          </nav>
        ) : null}
        {eyebrow ? (
          <p className={`brand-label ${ink ? 'text-brand-cobalt-300' : 'text-brand-ink-500'}`}>{eyebrow}</p>
        ) : null}
        <h1
          className={[
            'text-balance font-bold leading-[1.2] tracking-tight',
            'text-[clamp(2rem,1.35rem+2.4vw,3.5rem)]',
            ink ? 'text-white' : 'text-brand-ink-900',
          ].join(' ')}
        >
          {title}
        </h1>
        {description ? (
          <p className={`max-w-2xl text-base leading-9 sm:text-lg ${ink ? 'text-white/70' : 'text-brand-ink-500'}`}>
            {description}
          </p>
        ) : null}
        {children}
      </AnimatedSection>
    </header>
  )
}
