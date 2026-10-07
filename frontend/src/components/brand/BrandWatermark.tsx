import { BRAND_MARK_SRC } from '../../utils/brand'

type BrandWatermarkProps = {
  quiet?: boolean
}

export function BrandWatermark({ quiet = false }: BrandWatermarkProps) {
  return (
    <div className={quiet ? 'brand-watermark brand-watermark--quiet' : 'brand-watermark'} aria-hidden="true">
      <img src={BRAND_MARK_SRC} alt="" width={371} height={347} />
    </div>
  )
}
