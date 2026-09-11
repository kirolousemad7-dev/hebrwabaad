import {
  addonPriceLabel,
  serviceLineHalalas,
  type BuilderAddon,
  type BuilderStepId,
  type SelectedServiceConfig,
} from '../../utils/builder'
import { formatDuration, formatHalalas, servicePriceLabel } from '../../utils/catalog'

type BuilderSummaryStepProps = {
  configs: SelectedServiceConfig[]
  addons: BuilderAddon[]
  packageAddonIds: string[]
  servicesSubtotal: number
  addonsSubtotal: number
  estimatedTotal: number
  incomplete: boolean
  durationDays: number | null
  submitting: boolean
  submitError: string | null
  onEditStep: (step: BuilderStepId) => void
  onSubmit: () => void
}

export function BuilderSummaryStep({
  configs,
  addons,
  packageAddonIds,
  servicesSubtotal,
  addonsSubtotal,
  estimatedTotal,
  incomplete,
  durationDays,
  submitting,
  submitError,
  onEditStep,
  onSubmit,
}: BuilderSummaryStepProps) {
  const packageAddons = addons.filter((addon) => packageAddonIds.includes(addon.id))

  return (
    <section className="space-y-6">
      <header className="space-y-1">
        <h2 className="text-xl font-extrabold text-brand-black">ملخص باقتك</h2>
        <p className="text-sm text-brand-text-muted">
          راجع الخدمات والكميات والإضافات قبل الإرسال. إن وُجد عنصر يحتاج تسعير فلن يُعرض إجمالي نهائي مضلّل.
        </p>
      </header>

      <div className="space-y-3 rounded-2xl border border-brand-border bg-white p-4">
        <div className="flex items-center justify-between gap-3">
          <h3 className="font-semibold text-brand-black">الخدمات</h3>
          <button type="button" className="text-sm text-brand-primary underline" onClick={() => onEditStep(2)}>
            تعديل
          </button>
        </div>
        <ul className="space-y-3 text-sm">
          {configs.map((row) => (
            <li key={row.service.id} className="space-y-1 border-b border-brand-border pb-3 last:border-0 last:pb-0">
              <div className="flex flex-wrap justify-between gap-2 font-medium text-brand-black">
                <span>
                  {row.service.name} × {row.quantity}
                </span>
                <span>
                  {row.service.is_chargeable
                    ? formatHalalas(serviceLineHalalas(row.service, row.quantity), row.service.currency)
                    : 'طلب تسعير'}
                </span>
              </div>
              <p className="text-brand-text-muted">{servicePriceLabel(row.service)}</p>
              {row.addonIds.length > 0 ? (
                <ul className="text-brand-text-muted">
                  {row.addonIds.map((id) => {
                    const addon = addons.find((item) => item.id === id)
                    return (
                      <li key={id}>
                        + {addon?.name ?? id} — {addon ? addonPriceLabel(addon) : 'طلب تسعير'}
                      </li>
                    )
                  })}
                </ul>
              ) : null}
            </li>
          ))}
        </ul>
      </div>

      <div className="space-y-3 rounded-2xl border border-brand-border bg-white p-4">
        <div className="flex items-center justify-between gap-3">
          <h3 className="font-semibold text-brand-black">إضافات الباقة</h3>
          <button type="button" className="text-sm text-brand-primary underline" onClick={() => onEditStep(3)}>
            تعديل
          </button>
        </div>
        {packageAddons.length === 0 ? (
          <p className="text-sm text-brand-text-muted">لا توجد إضافات على مستوى الباقة.</p>
        ) : (
          <ul className="space-y-2 text-sm">
            {packageAddons.map((addon) => (
              <li key={addon.id} className="flex flex-wrap justify-between gap-2">
                <span>{addon.name}</span>
                <span>{addonPriceLabel(addon)}</span>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="space-y-2 rounded-2xl border border-brand-black bg-brand-black p-4 text-white">
        {incomplete ? (
          <>
            <p className="flex justify-between gap-3 text-sm">
              <span>القيمة المعروفة (المسعّرة فقط)</span>
              <span>{formatHalalas(estimatedTotal)}</span>
            </p>
            <p className="text-sm text-white/80">+ خدمة/إضافة تحتاج تسعير</p>
            <p className="text-lg font-semibold">النهائي: بعد المراجعة</p>
          </>
        ) : (
          <>
            <p className="flex justify-between gap-3 text-sm">
              <span>مجموع الخدمات</span>
              <span>{formatHalalas(servicesSubtotal)}</span>
            </p>
            <p className="flex justify-between gap-3 text-sm">
              <span>مجموع الإضافات</span>
              <span>{formatHalalas(addonsSubtotal)}</span>
            </p>
            <p className="flex justify-between gap-3 text-lg font-semibold">
              <span>الإجمالي</span>
              <span>{formatHalalas(estimatedTotal)}</span>
            </p>
          </>
        )}
        <p className="text-sm text-white/75">
          {durationDays === null
            ? 'مدة الباقة النهائية يتم تأكيدها بعد المراجعة'
            : `مدة التنفيذ التقديرية (أطول مسار موازٍ): ${formatDuration(durationDays)}`}
        </p>
      </div>

      {submitError ? (
        <p className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{submitError}</p>
      ) : null}

      <button
        type="button"
        disabled={submitting || configs.length === 0}
        onClick={onSubmit}
        className="brand-btn-primary w-full disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500 sm:w-auto"
      >
        {submitting ? 'جاري الإرسال...' : incomplete ? 'إرسال لطلب تسعير' : 'اطلب هذه الباقة'}
      </button>
    </section>
  )
}
