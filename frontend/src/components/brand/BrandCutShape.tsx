type BrandCutShapeProps = {
  variant?: 'tr' | 'ink'
  className?: string
}

/** Decorative diagonal cut inspired by the brand symbol. Visual only. */
export function BrandCutShape({ variant = 'tr', className = '' }: BrandCutShapeProps) {
  return (
    <div
      aria-hidden="true"
      className={`brand-cut-shape ${variant === 'ink' ? 'brand-cut-shape--ink brand-cut-shape--tr' : 'brand-cut-shape--tr'} ${className}`}
    />
  )
}
