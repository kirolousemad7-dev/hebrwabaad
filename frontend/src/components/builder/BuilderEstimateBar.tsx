type BuilderEstimateBarProps = {
  serviceCount: number
  totalLabel: string
  durationLabel: string
}

export function BuilderEstimateBar({ serviceCount, totalLabel, durationLabel }: BuilderEstimateBarProps) {
  return (
    <aside
      className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"
      aria-label="ملخص تقديري"
    >
      <div>
        <p className="text-xs text-slate-500">التقدير الحالي · {serviceCount.toLocaleString('ar-SA')} خدمة</p>
        <p className="text-lg font-semibold tabular-nums">{totalLabel}</p>
      </div>
      <p className="text-sm text-slate-600">{durationLabel}</p>
    </aside>
  )
}
