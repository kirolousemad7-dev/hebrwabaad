import type { ReactNode } from 'react'
import { BrandCornerAccent } from '../brand/BrandCornerAccent'
import { AnimatedSection } from './AnimatedSection'

type PageHeroProps = {
  eyebrow?: string
  title: string
  description?: string | null
  children?: ReactNode
  tone?: 'paper' | 'ink'
}

export function PageHero({ eyebrow, title, description, children, tone = 'paper' }: PageHeroProps) {
  const ink = tone === 'ink'

  return (
    <header
      className={[
        'relative overflow-hidden rounded-3xl border px-6 py-10 sm:px-10 sm:py-12',
        ink
          ? 'border-brand-ink-700 bg-brand-ink-900 text-white'
          : 'border-brand-ink-100 bg-white text-brand-ink-900',
      ].join(' ')}
    >
      <BrandCornerAccent position="tr" className={ink ? 'opacity-70' : 'opacity-80'} />
      <div
        className={[
          'pointer-events-none absolute inset-0',
          ink
            ? 'bg-[radial-gradient(circle_at_80%_20%,rgba(49,92,255,0.35),transparent_45%)]'
            : 'bg-[radial-gradient(circle_at_85%_15%,rgba(49,92,255,0.08),transparent_40%)]',
        ].join(' ')}
      />
      <AnimatedSection className="relative z-10 max-w-3xl space-y-4">
        {eyebrow ? (
          <p className={`brand-label ${ink ? 'text-brand-cobalt-300' : 'text-brand-cobalt-700'}`}>{eyebrow}</p>
        ) : null}
        <h1 className={`brand-heading-xl ${ink ? 'text-white' : ''}`}>{title}</h1>
        {description ? (
          <p className={`max-w-2xl text-base leading-8 sm:text-lg ${ink ? 'text-white/75' : 'text-brand-ink-500'}`}>
            {description}
          </p>
        ) : null}
        {children}
      </AnimatedSection>
    </header>
  )
}
