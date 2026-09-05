import { formatMoney } from '../../utils/catalog'
import type { PrintingRequest } from '../../types/api'

type PrintingPricingStatusProps = {
  request: PrintingRequest
}

export function PrintingPricingStatus({ request }: PrintingPricingStatusProps) {
  if (request.pricing_type === 'ESTIMATED' && request.estimated_price) {
    return (
      <div className="space-y-1 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
        <p className="font-medium">السعر التقديري: {formatMoney(request.estimated_price)}</p>
        <p>هذا السعر تقديري وقد يتغير بعد المراجعة النهائية.</p>
        {request.pricing_notes ? <p className="text-amber-900/80">{request.pricing_notes}</p> : null}
      </div>
    )
  }

  if (request.pricing_type === 'QUOTE_REQUIRED') {
    return (
      <div className="space-y-1 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
        <p className="font-medium">طلب عرض سعر</p>
        <p>سيتم مراجعة تفاصيل طلبك وإرسال عرض سعر مخصص.</p>
        {request.pricing_notes ? <p className="text-slate-600">{request.pricing_notes}</p> : null}
      </div>
    )
  }

  if (request.pricing_type === 'QUOTE_READY' && request.quoted_price) {
    return (
      <div className="space-y-1 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
        <p className="font-medium">عرض السعر: {formatMoney(request.quoted_price)}</p>
        <p>تم تجهيز عرض السعر لطلبك. تأكيد الدفع سيُفعَّل لاحقاً.</p>
        {request.pricing_notes ? <p className="text-emerald-900/80">{request.pricing_notes}</p> : null}
      </div>
    )
  }

  return (
    <div className="space-y-1 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
      <p className="font-medium">طلبك قيد المراجعة</p>
      <p>سيظهر السعر التقديري أو طلب عرض السعر بعد مراجعة الفريق.</p>
    </div>
  )
}
