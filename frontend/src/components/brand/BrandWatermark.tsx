import { BRAND_LOGO_SRC } from '../../utils/brand'

export function BrandWatermark() {
  return (
    <div className="brand-watermark" aria-hidden="true">
      <img src={BRAND_LOGO_SRC} alt="" width={447} height={440} />
    </div>
  )
}
