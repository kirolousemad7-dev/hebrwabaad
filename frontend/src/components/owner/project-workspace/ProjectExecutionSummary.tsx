import type { ProjectExecutionSummary } from '../../../utils/projectExecution'

type Props = {
  summary: ProjectExecutionSummary | null | undefined
}

export function ProjectExecutionSummaryPanel({ summary }: Props) {
  if (!summary) {
    return (
      <div className="rounded-xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">
        ملخص التنفيذ غير متاح.
      </div>
    )
  }

  const cells = [
    { label: 'المرحلة الحالية', value: summary.current_phase?.title ?? '—' },
    { label: 'المعلم النشط', value: summary.active_milestone?.title ?? '—' },
    { label: 'مهام مفتوحة', value: summary.open_tasks.toLocaleString('ar-SA') },
    { label: 'متأخرة', value: summary.overdue_tasks.toLocaleString('ar-SA') },
    { label: 'بانتظار مراجعة', value: summary.in_review_tasks.toLocaleString('ar-SA') },
    { label: 'الموعد التالي', value: summary.next_deadline ?? '—' },
    {
      label: 'الإنجاز',
      value: `${Math.round(summary.completion_percent).toLocaleString('ar-SA')}%`,
    },
    {
      label: 'يحتاج متابعة',
      value: summary.attention_count.toLocaleString('ar-SA'),
    },
  ]

  return (
    <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {cells.map((cell) => (
        <div key={cell.label} className="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
          <dt className="text-xs text-slate-500">{cell.label}</dt>
          <dd className="mt-0.5 text-sm font-medium text-slate-900">{cell.value}</dd>
        </div>
      ))}
    </dl>
  )
}
