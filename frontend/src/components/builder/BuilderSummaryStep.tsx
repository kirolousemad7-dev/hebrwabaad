import { Link } from 'react-router-dom'
import type { Service, ServiceCategory } from '../../types/api'
import {
  BUILDER_ADDONS,
  BUILDER_MIN_QUANTITY,
  serviceLineHalalas,
} from '../../utils/builder'
import { formatDuration, formatHalalas, formatMoney, SERVICE_CATEGORY_LABELS } from '../../utils/catalog'
import { customPackagePath } from '../../utils/orderIntent'

type BuilderSummaryStepProps = {
  category: ServiceCategory
  services: Service[]
  quantities: Record<number, number>
  addonIds: string[]
  servicesSubtotal: number
  addonsSubtotal: number
  estimatedTotal: number
  durationDays: number | null
  onEditStep: (step: 1 | 2 | 3 | 4) => void
}

export function BuilderSummaryStep({
  category,
  services,
  quantities,
  addonIds,
  servicesSubtotal,
  addonsSubtotal,
  estimatedTotal,
  durationDays,
  onEditStep,
}: BuilderSummaryStepProps) {
  const selectedAddons = BUILDER_ADDONS.filter((addon) => addonIds.includes(addon.id))

  return (
    <section className="space-y-6">
      <header className="space-y-1">
        <h2 className="text-lg font-semibold">ملخص الباقة التقديرية</h2>
        <p className="text-sm text-slate-600">
          هذا تقدير للمقارنة فقط. لا يُنشئ طلباً ولا يُثبّت سعراً تعاقدياً حتى مرحلة الطلب لاحقاً.
        </p>
      </header>

      <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
        <div className="flex items-center justify-between gap-3">
          <h3 className="font-semibold">الفئة</h3>
          <button type="button" className="text-sm underline" onClick={() => onEditStep(1)}>
            تعديل
          </button>
        </div>
        <p>{SERVICE_CATEGORY_LABELS[category]}</p>
      </div>

      <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
        <div className="flex items-center justify-between gap-3">
          <h3 className="font-semibold">الخدمات</h3>
          <button type="button" className="text-sm underline" onClick={() => onEditStep(3)}>
            تعديل
          </button>
        </div>
        <ul className="space-y-2 text-sm">
          {services.map((service) => {
            const quantity = quantities[service.id] ?? BUILDER_MIN_QUANTITY

            return (
              <li key={service.id} className="flex flex-wrap justify-between gap-2">
                <span>
                  {service.name} × {quantity}
                </span>
                <span>
                  {formatMoney(service.base_price, service.currency)} ·{' '}
                  {formatHalalas(serviceLineHalalas(service, quantity), service.currency)}
                </span>
              </li>
            )
          })}
        </ul>
      </div>

      <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
        <div className="flex items-center justify-between gap-3">
          <h3 className="font-semibold">الإضافات</h3>
          <button type="button" className="text-sm underline" onClick={() => onEditStep(4)}>
            تعديل
          </button>
        </div>
        {selectedAddons.length === 0 ? (
          <p className="text-sm text-slate-600">لا توجد إضافات محددة.</p>
        ) : (
          <ul className="space-y-2 text-sm">
            {selectedAddons.map((addon) => (
              <li key={addon.id} className="flex flex-wrap justify-between gap-2">
                <span>{addon.name}</span>
                <span>{formatHalalas(addon.priceHalalas)}</span>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="space-y-2 rounded-xl border border-slate-900 bg-slate-900 p-4 text-white">
        <p className="flex justify-between gap-3 text-sm">
          <span>مجموع الخدمات</span>
          <span>{formatHalalas(servicesSubtotal)}</span>
        </p>
        <p className="flex justify-between gap-3 text-sm">
          <span>مجموع الإضافات التقديرية</span>
          <span>{formatHalalas(addonsSubtotal)}</span>
        </p>
        <p className="flex justify-between gap-3 text-lg font-semibold">
          <span>السعر التقديري</span>
          <span>{formatHalalas(estimatedTotal)}</span>
        </p>
        <p className="text-sm text-white/75">
          {durationDays === null
            ? 'لم يتم تحديد مدة التنفيذ بعد'
            : `مدة التنفيذ التقديرية: ${formatDuration(durationDays)}`}
        </p>
      </div>

      <Link
        to={customPackagePath()}
        className="mb-2 inline-flex scroll-mb-44 items-center justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 lg:scroll-mb-0"
      >
        اطلب هذه الباقة
      </Link>
    </section>
  )
}
