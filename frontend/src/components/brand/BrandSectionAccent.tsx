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
        <p className="brand-label font-latin text-brand-cobalt-700 uppercase tracking-[0.14em]">{english}</p>
      ) : null}
      <h2 className="brand-heading-lg">{title}</h2>
      {description ? <p className="leading-8 text-brand-ink-500">{description}</p> : null}
    </header>
  )
}
