import type { PricingMode } from '../../types/api'
import { discountPresentation, formatDuration, servicePriceLabel } from '../../utils/catalog'
import { SarAmount } from '../ui/SarAmount'

type CatalogPriceLineProps = {
  pricingMode: PricingMode
  current: string
  before?: string | null
  currency?: string
  durationDays?: number | null
  chargeable?: boolean
}

export function CatalogPriceLine({
  pricingMode,
  current,
  before,
  currency = 'SAR',
  durationDays,
  chargeable = true,
}: CatalogPriceLineProps) {
  const currentValue = Number.parseFloat(current)
  const beforeValue = before == null || before === '' ? Number.NaN : Number.parseFloat(before)
  const quote = pricingMode === 'QUOTE' || !Number.isFinite(currentValue) || currentValue <= 0 || !chargeable
  const offer = quote ? null : discountPresentation(beforeValue, currentValue)
  const duration = formatDuration(durationDays ?? null)

  return (
    <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
      <span className="font-semibold text-brand-ink-900">
        {quote ? (
          servicePriceLabel({ pricing_mode: pricingMode, base_price: current, currency })
        ) : pricingMode === 'STARTING_FROM' ? (
          <span className="inline-flex flex-wrap items-baseline gap-2">
            <span className="text-sm font-medium">يبدأ من</span>
            <SarAmount amount={current} />
          </span>
        ) : (
          <SarAmount amount={current} />
        )}
      </span>
      {offer ? <SarAmount amount={beforeValue} struck className="text-sm" /> : null}
      {offer ? (
        <span className="text-sm font-medium text-red-600">
          {offer.percent !== null ? (
            `خصم ${offer.percent}%`
          ) : (
            <>
              خصم <SarAmount amount={offer.amount} className="text-red-600" />
            </>
          )}
        </span>
      ) : null}
      {duration ? <span className="text-sm text-brand-ink-500">{duration}</span> : null}
    </div>
  )
}
