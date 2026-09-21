import type { MarketingVisual } from '../../utils/marketingVisuals'

type BrandVisualProps = {
  visual: MarketingVisual
  className?: string
  priority?: boolean
}

/**
 * Renders a photo when `visual.image` is set; otherwise a brand geometric panel.
 * Swap images in `utils/marketingVisuals.ts` without touching components.
 */
export function BrandVisual({ visual, className = '', priority = false }: BrandVisualProps) {
  if (visual.image) {
    return (
      <img
        src={visual.image}
        alt={visual.alt}
        className={`h-full w-full object-cover ${className}`}
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
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,rgba(49,92,255,0.45),transparent_55%)]" />
      <div className="pointer-events-none absolute -bottom-8 -start-8 h-40 w-40 rounded-full bg-brand-cobalt-500/40 blur-2xl" />
      <div className="pointer-events-none absolute -top-6 -end-6 h-28 w-28 rounded-[2rem] bg-brand-cobalt-300/25" />
      {visual.accentSvg ? (
        <img
          src={visual.accentSvg}
          alt=""
          aria-hidden="true"
          className="relative z-10 h-24 w-24 object-contain opacity-90 sm:h-28 sm:w-28"
          loading={priority ? 'eager' : 'lazy'}
          decoding="async"
        />
      ) : null}
    </div>
  )
}
