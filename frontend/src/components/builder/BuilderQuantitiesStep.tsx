import {
  BUILDER_MAX_QUANTITY,
  BUILDER_MIN_QUANTITY,
  addonsForService,
  addonPriceLabel,
  clampBuilderQuantity,
  serviceLineHalalas,
  type BuilderAddon,
  type SelectedServiceConfig,
} from '../../utils/builder'
import { formatHalalas, servicePriceLabel } from '../../utils/catalog'

type BuilderQuantitiesStepProps = {
  configs: SelectedServiceConfig[]
  addons: BuilderAddon[]
  onQuantityChange: (serviceId: number, quantity: number) => void
  onToggleServiceAddon: (serviceId: number, addonId: string) => void
  onRemove: (serviceId: number) => void
}

export function BuilderQuantitiesStep({
  configs,
  addons,
  onQuantityChange,
  onToggleServiceAddon,
  onRemove,
}: BuilderQuantitiesStepProps) {
  return (
    <section className="space-y-4">
      <header className="space-y-1">
        <h2 className="text-xl font-extrabold text-brand-black">تفاصيل كل خدمة</h2>
        <p className="text-sm text-brand-text-muted">
          حدّد الكمية والإضافات المتوافقة مع كل خدمة. الحد الأقصى {BUILDER_MAX_QUANTITY}.
        </p>
      </header>

      {configs.length === 0 ? (
        <p className="rounded-2xl border border-brand-border bg-white px-4 py-8 text-center text-sm text-brand-text-muted">
          لا توجد خدمات محددة. ارجع واختر خدمة واحدة على الأقل.
        </p>
      ) : (
        <ul className="space-y-4">
          {configs.map((row) => {
            const quantity = row.quantity
            const inputId = `quantity-${row.service.id}`
            const compatible = addonsForService(addons, row.service.id)

            return (
              <li
                key={row.service.id}
                className="space-y-4 rounded-2xl border border-brand-border bg-white p-4"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0 space-y-1">
                    <p className="font-bold text-brand-black">{row.service.name}</p>
                    <p className="text-sm text-brand-text-muted">
                      {servicePriceLabel(row.service)}
                      {row.service.is_chargeable ? (
                        <>
                          {' '}
                          · الإجمالي{' '}
                          <span className="font-medium text-brand-black">
                            {formatHalalas(serviceLineHalalas(row.service, quantity), row.service.currency)}
                          </span>
                        </>
                      ) : null}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => onRemove(row.service.id)}
                    className="rounded-full border border-brand-border px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
                  >
                    إزالة
                  </button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-sm font-medium text-brand-black">الكمية:</span>
                  <div className="flex items-center rounded-full border border-brand-border">
                    <button
                      type="button"
                      aria-label={`إنقاص كمية ${row.service.name}`}
                      disabled={quantity <= BUILDER_MIN_QUANTITY}
                      onClick={() => onQuantityChange(row.service.id, quantity - 1)}
                      className="px-3 py-2 text-sm disabled:opacity-40"
                    >
                      −
                    </button>
                    <label htmlFor={inputId} className="sr-only">
                      كمية {row.service.name}
                    </label>
                    <input
                      id={inputId}
                      type="number"
                      inputMode="numeric"
                      min={BUILDER_MIN_QUANTITY}
                      max={BUILDER_MAX_QUANTITY}
                      value={quantity}
                      onChange={(event) =>
                        onQuantityChange(
                          row.service.id,
                          clampBuilderQuantity(Number.parseInt(event.target.value, 10)),
                        )
                      }
                      className="w-14 border-x border-brand-border py-2 text-center text-sm"
                    />
                    <button
                      type="button"
                      aria-label={`زيادة كمية ${row.service.name}`}
                      disabled={quantity >= BUILDER_MAX_QUANTITY}
                      onClick={() => onQuantityChange(row.service.id, quantity + 1)}
                      className="px-3 py-2 text-sm disabled:opacity-40"
                    >
                      +
                    </button>
                  </div>
                </div>

                {compatible.length > 0 ? (
                  <fieldset className="space-y-2">
                    <legend className="text-sm font-medium text-brand-black">الإضافات</legend>
                    <ul className="space-y-2">
                      {compatible.map((addon) => {
                        const checked = row.addonIds.includes(addon.id)

                        return (
                          <li key={addon.id}>
                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-brand-border px-3 py-2 text-sm hover:border-brand-primary/40">
                              <input
                                type="checkbox"
                                checked={checked}
                                onChange={() => onToggleServiceAddon(row.service.id, addon.id)}
                                className="mt-1"
                              />
                              <span className="min-w-0 flex-1">
                                <span className="font-medium text-brand-black">{addon.name}</span>
                                {addon.description ? (
                                  <span className="mt-0.5 block text-brand-text-muted">{addon.description}</span>
                                ) : null}
                              </span>
                              <span className="shrink-0 text-brand-text-muted">{addonPriceLabel(addon)}</span>
                            </label>
                          </li>
                        )
                      })}
                    </ul>
                  </fieldset>
                ) : null}
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}
