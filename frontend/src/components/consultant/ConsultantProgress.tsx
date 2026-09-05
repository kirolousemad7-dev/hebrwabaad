type ConsultantProgressProps = {
  current: number
  total: number
  percent: number
}

export function ConsultantProgress({ current, total, percent }: ConsultantProgressProps) {
  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
        <span>التقدم</span>
        <span>
          {current.toLocaleString('ar-SA')} من {total.toLocaleString('ar-SA')}
        </span>
      </div>
      <div
        className="h-2 overflow-hidden rounded-full bg-slate-200"
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={percent}
        aria-label="تقدم الاستشارة"
      >
        <div className="h-full rounded-full bg-amber-400 transition-[width]" style={{ width: `${percent}%` }} />
      </div>
    </div>
  )
}
