type BrandPathShapeProps = {
  className?: string
}

/** Soft pathway frame derived from logo curvature. Decorative only. */
export function BrandPathShape({ className = '' }: BrandPathShapeProps) {
  return <div aria-hidden="true" className={`brand-path-shape ${className}`} />
}
