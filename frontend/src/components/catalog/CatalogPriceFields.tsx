import { formatMoney, resolveListPrice } from '../../utils/catalog'

type CatalogPriceFieldsProps = {
  current: string
  before: string
  discount: string
  requireCurrent: boolean
  onChange: (patch: { current?: string; before?: string; discount?: string }) => void
}

const inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2'

export function CatalogPriceFields({ current, before, discount, requireCurrent, onChange }: CatalogPriceFieldsProps) {
  const resolved = resolveListPrice({ current, before, discount, requireCurrent })
  const showIssue = !resolved.ok && resolved.message !== 'السعر الحالي مطلوب.'

  return (
    <div className="space-y-3 rounded-md border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
      <p className="text-sm font-medium text-slate-800">التسعير</p>
      <div className="grid gap-4 sm:grid-cols-3">
        <label className="block space-y-1 text-sm">
          <span>السعر الحالي (ريال)</span>
          <input
            type="number"
            min={0}
            step="0.01"
            required={requireCurrent}
            value={current}
            onChange={(event) => onChange({ current: event.target.value })}
            className={inputClass}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>السعر قبل الخصم (اختياري)</span>
          <input
            type="number"
            min={0}
            step="0.01"
            value={before}
            onChange={(event) => onChange({ before: event.target.value })}
            className={inputClass}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>الخصم (ريال، اختياري)</span>
          <input
            type="number"
            min={0}
            step="0.01"
            value={discount}
            onChange={(event) => onChange({ discount: event.target.value })}
            className={inputClass}
          />
        </label>
      </div>
      {showIssue ? <p className="text-xs text-red-600">{resolved.message}</p> : null}
      {resolved.ok && resolved.value.discountAmount > 0 ? (
        <p className="text-xs text-slate-600">
          يظهر في الموقع: {formatMoney(resolved.value.current)} بدلاً من {formatMoney(resolved.value.price)}
        </p>
      ) : null}
    </div>
  )
}
