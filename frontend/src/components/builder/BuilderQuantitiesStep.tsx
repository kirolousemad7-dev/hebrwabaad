import type { Service } from '../../types/api'
import {
  BUILDER_MAX_QUANTITY,
  BUILDER_MIN_QUANTITY,
  clampBuilderQuantity,
  serviceLineHalalas,
} from '../../utils/builder'
import { formatHalalas, formatMoney } from '../../utils/catalog'

type BuilderQuantitiesStepProps = {
  services: Service[]
  quantities: Record<number, number>
  onQuantityChange: (serviceId: number, quantity: number) => void
  onRemove: (serviceId: number) => void
}

export function BuilderQuantitiesStep({
  services,
  quantities,
  onQuantityChange,
  onRemove,
}: BuilderQuantitiesStepProps) {
  return (
    <section className="space-y-4">
      <header className="space-y-1">
        <h2 className="text-lg font-semibold">حدد الكميات</h2>
        <p className="text-sm text-slate-600">الحد الأدنى 1 والحد الأقصى {BUILDER_MAX_QUANTITY} لكل خدمة.</p>
      </header>

      {services.length === 0 ? (
        <p className="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-600">
          لا توجد خدمات محددة. ارجع واختر خدمة واحدة على الأقل.
        </p>
      ) : (
        <ul className="space-y-3">
          {services.map((service) => {
            const quantity = quantities[service.id] ?? BUILDER_MIN_QUANTITY
            const inputId = `quantity-${service.id}`

            return (
              <li
                key={service.id}
                className="flex min-w-0 flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0 space-y-1">
                  <p className="font-semibold">{service.name}</p>
                  <p className="text-sm text-slate-600">
                    سعر الوحدة {formatMoney(service.base_price, service.currency)} · الإجمالي{' '}
                    <span className="font-medium">{formatHalalas(serviceLineHalalas(service, quantity), service.currency)}</span>
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <div className="flex items-center rounded-md border border-slate-300">
                    <button
                      type="button"
                      aria-label={`إنقاص كمية ${service.name}`}
                      disabled={quantity <= BUILDER_MIN_QUANTITY}
                      onClick={() => onQuantityChange(service.id, quantity - 1)}
                      className="px-3 py-2 text-sm disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                    >
                      −
                    </button>
                    <label htmlFor={inputId} className="sr-only">
                      كمية {service.name}
                    </label>
                    <input
                      id={inputId}
                      type="number"
                      inputMode="numeric"
                      min={BUILDER_MIN_QUANTITY}
                      max={BUILDER_MAX_QUANTITY}
                      value={quantity}
                      onChange={(event) =>
                        onQuantityChange(service.id, clampBuilderQuantity(Number.parseInt(event.target.value, 10)))
                      }
                      className="w-14 border-x border-slate-300 py-2 text-center text-sm"
                    />
                    <button
                      type="button"
                      aria-label={`زيادة كمية ${service.name}`}
                      disabled={quantity >= BUILDER_MAX_QUANTITY}
                      onClick={() => onQuantityChange(service.id, quantity + 1)}
                      className="px-3 py-2 text-sm disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                    >
                      +
                    </button>
                  </div>
                  <button
                    type="button"
                    onClick={() => onRemove(service.id)}
                    className="rounded-md border border-slate-300 px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                  >
                    إزالة
                  </button>
                </div>
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}
