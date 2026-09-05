import type { PrintingRequest } from '../../types/api'
import {
  formatPrintingDate,
  PRINTING_FINISHING_LABELS,
  PRINTING_METHOD_LABELS,
  PRINTING_SHAPE_LABELS,
  PRINTING_UNIT_LABELS,
  type PrintingFinishing,
  type PrintingMethod,
  type PrintingShape,
  type PrintingUnit,
} from '../../utils/printingRequest'

type PrintingRequestSpecsProps = {
  request: PrintingRequest
}

function labelOf<T extends string>(value: string, labels: Record<T, string>): string {
  return labels[value as T] ?? value
}

export function PrintingRequestSpecs({ request }: PrintingRequestSpecsProps) {
  const finishing = (request.finishing ?? []).map((item) =>
    labelOf(item, PRINTING_FINISHING_LABELS as Record<PrintingFinishing, string>),
  )

  return (
    <dl className="grid gap-3 sm:grid-cols-2">
      <div>
        <dt className="text-xs text-slate-500">المنتج</dt>
        <dd className="text-sm">{request.product_name}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">المعرّف</dt>
        <dd className="text-sm" dir="ltr">
          {request.product_slug}
        </dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">الأبعاد</dt>
        <dd className="text-sm">
          {request.width} × {request.height} {labelOf(request.dimension_unit, PRINTING_UNIT_LABELS as Record<PrintingUnit, string>)}
        </dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">الشكل</dt>
        <dd className="text-sm">{labelOf(request.shape, PRINTING_SHAPE_LABELS as Record<PrintingShape, string>)}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">الخامة</dt>
        <dd className="text-sm">{request.material}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">الكمية</dt>
        <dd className="text-sm">{request.quantity.toLocaleString('ar-SA')}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">طريقة الطباعة</dt>
        <dd className="text-sm">{labelOf(request.printing_method, PRINTING_METHOD_LABELS as Record<PrintingMethod, string>)}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">التشطيب</dt>
        <dd className="text-sm">{finishing.join('، ')}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">تاريخ التسليم المطلوب</dt>
        <dd className="text-sm">{formatPrintingDate(request.required_date)}</dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">ملف التصميم</dt>
        <dd className="text-sm">{request.filename}</dd>
      </div>
      {request.notes ? (
        <div className="sm:col-span-2">
          <dt className="text-xs text-slate-500">ملاحظات العميل</dt>
          <dd className="whitespace-pre-wrap text-sm text-slate-700">{request.notes}</dd>
        </div>
      ) : null}
    </dl>
  )
}
