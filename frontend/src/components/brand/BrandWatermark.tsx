import { BRAND_MARK_SRC } from '../../utils/brand'

export function BrandWatermark() {
  return (
    <div className="brand-watermark" aria-hidden="true">
      <img src={BRAND_MARK_SRC} alt="" width={371} height={347} />
    </div>
  )
}
