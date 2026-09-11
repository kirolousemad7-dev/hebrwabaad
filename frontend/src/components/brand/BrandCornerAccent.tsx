type BrandCornerAccentProps = {
  position?: 'tr' | 'bl'
  className?: string
}

/** Web-native geometric accent inspired by the official identity fold shapes. */
export function BrandCornerAccent({ position = 'tr', className = '' }: BrandCornerAccentProps) {
  return (
    <div
      aria-hidden="true"
      className={`brand-corner brand-corner--${position} ${className}`}
    />
  )
}
