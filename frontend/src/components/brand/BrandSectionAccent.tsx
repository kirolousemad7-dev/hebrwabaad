type BrandSectionAccentProps = {
  english?: string
  title: string
  description?: string
  className?: string
}

/**
 * Consistent section header language from Brand Guidelines:
 * cobalt marker → bold Arabic title → optional short support.
 * Purely presentational — callers keep their own layout/IDs.
 */
export function BrandSectionAccent({ english, title, description, className = '' }: BrandSectionAccentProps) {
  return (
    <header className={`max-w-2xl space-y-3 ${className}`}>
      <span className="brand-section-marker" aria-hidden="true" />
      {english ? (
        <p className="brand-label font-latin text-brand-ink-500 uppercase tracking-[0.14em]">{english}</p>
      ) : null}
      <h2 className="text-[clamp(1.75rem,1.3rem+1.4vw,2.75rem)] font-bold leading-tight text-brand-ink-900">
        {title}
      </h2>
      {description ? <p className="max-w-xl leading-8 text-brand-ink-500">{description}</p> : null}
    </header>
  )
}
