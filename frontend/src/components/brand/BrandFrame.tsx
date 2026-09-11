type BrandFrameProps = {
  children: React.ReactNode
  className?: string
}

/** Soft paper card frame with brand border language. Visual wrapper only. */
export function BrandFrame({ children, className = '' }: BrandFrameProps) {
  return <div className={`brand-card ${className}`}>{children}</div>
}
