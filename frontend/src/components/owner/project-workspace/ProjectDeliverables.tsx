import type { ProjectDeliverableRow } from '../../../utils/projectExecution'

type Props = {
  deliverables: ProjectDeliverableRow[] | null | undefined
  onOpenTask?: (taskId: number) => void
}

export function ProjectDeliverables({ deliverables, onOpenTask }: Props) {
  const rows = deliverables ?? []

  if (rows.length === 0) {
    return (
      <p className="text-sm text-slate-500">
        لا تسليمات مشتقة بعد. تظهر هنا خطوط خدمات الحزمة المخصصة والمراجع المرتبطة بالمشروع.
      </p>
    )
  }

  return (
    <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
      {rows.map((row) => (
        <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-3 text-sm">
          <div className="min-w-0">
            <p className="font-medium text-slate-900">{row.name}</p>
            <p className="text-xs text-slate-500">
              {row.source === 'service_line' ? 'خدمة' : 'مرجع'}
              {row.quantity > 1 ? ` · ×${row.quantity.toLocaleString('ar-SA')}` : ''}
              {row.is_client_visible ? ' · ظاهر للعميل' : ' · داخلي'}
              {row.requires_customer_approval ? ' · يحتاج موافقة عميل' : ''}
              {row.due_date ? ` · ${row.due_date}` : ''}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">{row.status_label}</span>
            {row.task_id != null && onOpenTask ? (
              <button
                type="button"
                onClick={() => onOpenTask(row.task_id!)}
                className="text-xs underline"
              >
                المهمة
              </button>
            ) : null}
            {row.url ? (
              <a href={row.url} target="_blank" rel="noreferrer" className="text-xs underline">
                رابط
              </a>
            ) : null}
          </div>
        </li>
      ))}
    </ul>
  )
}
