import { formatShortAxisDate } from '../../utils/ownerDashboard'
import { PRINTING_PRICING_LABELS } from '../../utils/printingRequest'
import { DashboardEmptyState } from './DashboardSection'

type DashboardBusinessOverviewProps = {
  requestActivity: Array<{ date: string; count: number }>
  pricingBreakdown: {
    unpriced: number
    estimated: number
    quote_required: number
    quote_ready: number
  }
}

const BREAKDOWN_ITEMS = [
  { key: 'unpriced', label: 'بانتظار التسعير' },
  { key: 'estimated', label: PRINTING_PRICING_LABELS.ESTIMATED },
  { key: 'quote_required', label: PRINTING_PRICING_LABELS.QUOTE_REQUIRED },
  { key: 'quote_ready', label: PRINTING_PRICING_LABELS.QUOTE_READY },
] as const

export function DashboardBusinessOverview({
  requestActivity,
  pricingBreakdown,
}: DashboardBusinessOverviewProps) {
  const totalRequests = requestActivity.reduce((sum, point) => sum + point.count, 0)
  const maxCount = Math.max(0, ...requestActivity.map((point) => point.count))
  const pricedTotal =
    pricingBreakdown.unpriced +
    pricingBreakdown.estimated +
    pricingBreakdown.quote_required +
    pricingBreakdown.quote_ready

  return (
    <div className="grid gap-4 lg:grid-cols-5">
      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-3">
        <h3 className="font-medium">طلبات الطباعة خلال ١٤ يوماً</h3>
        {totalRequests === 0 ? (
          <div className="mt-4">
            <DashboardEmptyState
              title="لا توجد طلبات في هذه الفترة"
              description="الرسم يعرض الطلبات الفعلية حسب تاريخ الإنشاء، دون تقديرات."
            />
          </div>
        ) : (
          <div className="mt-5">
            <p className="sr-only">
              مجموع طلبات الطباعة خلال أربعة عشر يوماً: {totalRequests.toLocaleString('ar-SA')}
            </p>
            <div
              className="flex h-40 items-end gap-1.5"
              role="img"
              aria-label="مخطط أعمدة لعدد طلبات الطباعة يومياً"
            >
              {requestActivity.map((point) => {
                return (
                  <div key={point.date} className="flex min-w-0 flex-1 flex-col items-center gap-2">
                    <div className="flex h-28 w-full items-end">
                      <div
                        className={`w-full rounded-t-md ${point.count > 0 ? 'bg-slate-900' : 'bg-slate-200'}`}
                        style={{ height: point.count > 0 ? `${Math.max(12, (point.count / maxCount) * 100)}%` : '2px' }}
                        title={`${formatShortAxisDate(point.date)}: ${point.count}`}
                      />
                    </div>
                    <span className="hidden text-[10px] text-slate-500 sm:block">
                      {formatShortAxisDate(point.date)}
                    </span>
                  </div>
                )
              })}
            </div>
            <p className="mt-3 text-sm text-slate-600">
              المجموع في الفترة: {totalRequests.toLocaleString('ar-SA')} طلب
            </p>
          </div>
        )}
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
        <h3 className="font-medium">حالة تسعير الطلبات</h3>
        {pricedTotal === 0 ? (
          <div className="mt-4">
            <DashboardEmptyState
              title="لا توجد طلبات بعد"
              description="عند ورود طلبات طباعة ستظهر هنا حالتها الحالية."
            />
          </div>
        ) : (
          <ul className="mt-4 space-y-3">
            {BREAKDOWN_ITEMS.map((item) => {
              const count = pricingBreakdown[item.key]
              const width = pricedTotal === 0 ? 0 : Math.round((count / pricedTotal) * 100)

              return (
                <li key={item.key} className="space-y-1">
                  <div className="flex items-center justify-between gap-3 text-sm">
                    <span>{item.label}</span>
                    <span className="tabular-nums text-slate-600">{count.toLocaleString('ar-SA')}</span>
                  </div>
                  <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                      className="h-full rounded-full bg-amber-400"
                      style={{ width: `${width}%` }}
                    />
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </div>
    </div>
  )
}
