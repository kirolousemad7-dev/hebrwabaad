type BrandImageMaskProps = {
  children: React.ReactNode
  cut?: boolean
  className?: string
}

/** Logo-inspired image mask. Does not change image sources or content. */
export function BrandImageMask({ children, cut = false, className = '' }: BrandImageMaskProps) {
  return (
    <div className={`brand-image-mask ${cut ? 'brand-image-mask--cut' : ''} ${className}`}>{children}</div>
  )
}
