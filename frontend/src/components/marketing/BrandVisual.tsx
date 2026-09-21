import type { MarketingVisual } from '../../utils/marketingVisuals'

type BrandVisualProps = {
  visual: MarketingVisual
  className?: string
  priority?: boolean
  /** Soft zoom on parent group hover */
  zoomable?: boolean
}

/**
 * Renders a photo when `visual.image` is set; otherwise a brand geometric panel.
 * Swap images in `utils/marketingVisuals.ts` without touching components.
 */
export function BrandVisual({ visual, className = '', priority = false, zoomable = false }: BrandVisualProps) {
  if (visual.image) {
    return (
      <img
        src={visual.image}
        alt={visual.alt}
        className={[
          'h-full w-full object-cover',
          zoomable
            ? 'transition duration-700 ease-out group-hover:scale-[1.04] motion-reduce:transition-none motion-reduce:group-hover:scale-100'
            : '',
          className,
        ].join(' ')}
        loading={priority ? 'eager' : 'lazy'}
        decoding="async"
      />
    )
  }

  return (
    <div
      className={`relative flex h-full w-full items-center justify-center overflow-hidden bg-brand-ink-900 ${className}`}
      role="img"
      aria-label={visual.alt}
    >
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_28%_22%,rgba(49,92,255,0.38),transparent_52%)]" />
      <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(160deg,transparent_40%,rgba(247,245,239,0.06)_100%)]" />
      <div className="pointer-events-none absolute -bottom-10 -start-10 h-44 w-44 rounded-full bg-brand-cobalt-500/25 blur-3xl" />
      {visual.accentSvg ? (
        <img
          src={visual.accentSvg}
          alt=""
          aria-hidden="true"
          className={[
            'relative z-10 h-24 w-24 object-contain opacity-90 sm:h-28 sm:w-28',
            zoomable ? 'transition duration-700 group-hover:scale-105 motion-reduce:group-hover:scale-100' : '',
          ].join(' ')}
          loading={priority ? 'eager' : 'lazy'}
          decoding="async"
        />
      ) : null}
    </div>
  )
}
