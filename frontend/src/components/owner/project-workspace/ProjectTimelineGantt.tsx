import { useMemo } from 'react'
import type { ProjectStructure, ProjectStructureTask } from '../../../services/operations'

type Scale = 'day' | 'week' | 'month'

type Props = {
  structure: ProjectStructure
  scale: Scale
  onScaleChange: (scale: Scale) => void
}

type BarItem = {
  id: string
  label: string
  kind: 'phase' | 'milestone' | 'task'
  start: Date
  end: Date
  status: string
  progress?: number
  assignee?: string | null
  clientVisible?: boolean
}

function parseDate(value?: string | null): Date | null {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

function startOfDay(date: Date) {
  const copy = new Date(date)
  copy.setHours(0, 0, 0, 0)
  return copy
}

function addDays(date: Date, days: number) {
  const copy = new Date(date)
  copy.setDate(copy.getDate() + days)
  return copy
}

function daysBetween(a: Date, b: Date) {
  return Math.max(1, Math.round((startOfDay(b).getTime() - startOfDay(a).getTime()) / 86400000) + 1)
}

function collectBars(structure: ProjectStructure): BarItem[] {
  const bars: BarItem[] = []

  for (const phase of structure.phases) {
    const phaseStart = parseDate(phase.starts_at) ?? parseDate(phase.ends_at)
    const phaseEnd = parseDate(phase.ends_at) ?? parseDate(phase.starts_at)
    if (phaseStart && phaseEnd) {
      bars.push({
        id: `phase-${phase.id}`,
        label: phase.title,
        kind: 'phase',
        start: phaseStart,
        end: phaseEnd,
        status: phase.status,
        progress: phase.progress?.percent,
        clientVisible: phase.is_client_visible,
      })
    }

    for (const milestone of phase.milestones ?? []) {
      const due = parseDate(milestone.due_date) ?? parseDate(milestone.starts_at)
      if (due) {
        bars.push({
          id: `ms-${milestone.id}`,
          label: milestone.title,
          kind: 'milestone',
          start: due,
          end: due,
          status: milestone.status,
          progress: milestone.progress?.percent,
          clientVisible: milestone.is_client_visible,
        })
      }
      for (const task of milestone.tasks ?? []) {
        pushTask(bars, task)
      }
    }

    for (const task of phase.tasks ?? []) {
      pushTask(bars, task)
    }
  }

  for (const task of structure.unassigned_tasks) {
    pushTask(bars, task)
  }

  return bars
}

function pushTask(bars: BarItem[], task: ProjectStructureTask) {
  const start = parseDate(task.start_at) ?? parseDate(task.deadline) ?? parseDate(task.due_at)
  const end = parseDate(task.due_at) ?? parseDate(task.deadline) ?? parseDate(task.start_at)
  if (!start || !end) return
  bars.push({
    id: `task-${task.id}`,
    label: task.title,
    kind: 'task',
    start,
    end: end < start ? start : end,
    status: task.status,
    assignee: task.assignee?.name ?? null,
    clientVisible: task.is_client_visible,
  })
}

function kindTone(kind: BarItem['kind'], status: string, end: Date) {
  const today = startOfDay(new Date())
  const isDone = status === 'DONE' || status === 'COMPLETED' || status === 'Completed'
  if (isDone) return 'bg-emerald-600'
  if (!isDone && end < today) return 'bg-red-500'
  if (kind === 'phase') return 'bg-slate-700'
  if (kind === 'milestone') return 'bg-amber-500'
  return 'bg-brand-primary/80'
}

export function ProjectTimelineGantt({ structure, scale, onScaleChange }: Props) {
  const bars = useMemo(() => collectBars(structure), [structure])

  const range = useMemo(() => {
    if (bars.length === 0) {
      const today = startOfDay(new Date())
      return { start: today, end: addDays(today, scale === 'day' ? 14 : scale === 'week' ? 42 : 90) }
    }
    const min = bars.reduce((acc, bar) => (bar.start < acc ? bar.start : acc), bars[0].start)
    const max = bars.reduce((acc, bar) => (bar.end > acc ? bar.end : acc), bars[0].end)
    const pad = scale === 'day' ? 2 : scale === 'week' ? 7 : 14
    return { start: addDays(startOfDay(min), -pad), end: addDays(startOfDay(max), pad) }
  }, [bars, scale])

  const totalDays = daysBetween(range.start, range.end)
  const tickEvery = scale === 'day' ? 1 : scale === 'week' ? 7 : 30
  const ticks: Date[] = []
  for (let i = 0; i < totalDays; i += tickEvery) {
    ticks.push(addDays(range.start, i))
  }

  if (bars.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-slate-200 p-6 text-sm text-slate-500">
        لا عناصر بتاريخ لعرض الخط الزمني. أضف تواريخ للمراحل أو المعالم أو المهام.
      </div>
    )
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-2">
        {([
          ['day', 'يوم'],
          ['week', 'أسبوع'],
          ['month', 'شهر'],
        ] as const).map(([value, label]) => (
          <button
            key={value}
            type="button"
            onClick={() => onScaleChange(value)}
            className={`rounded-full px-3 py-1 text-xs ${
              scale === value ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <div className="min-w-[720px]">
          <div className="grid grid-cols-[200px_1fr] border-b border-slate-100 text-xs text-slate-500">
            <div className="px-3 py-2">العنصر</div>
            <div className="relative h-8">
              {ticks.map((tick) => {
                const left = ((startOfDay(tick).getTime() - range.start.getTime()) / 86400000 / totalDays) * 100
                return (
                  <span
                    key={tick.toISOString()}
                    className="absolute top-2 -translate-x-1/2 whitespace-nowrap"
                    style={{ left: `${left}%` }}
                  >
                    {tick.toLocaleDateString('ar-SA', { month: 'short', day: 'numeric' })}
                  </span>
                )
              })}
              {(() => {
                const today = startOfDay(new Date())
                if (today < range.start || today > range.end) return null
                const left =
                  ((today.getTime() - range.start.getTime()) / 86400000 / totalDays) * 100
                return (
                  <span
                    className="absolute top-0 h-full w-px bg-rose-500"
                    style={{ left: `${left}%` }}
                    title="اليوم"
                  />
                )
              })()}
            </div>
          </div>

          {bars.map((bar) => {
            const offset = (startOfDay(bar.start).getTime() - range.start.getTime()) / 86400000
            const width = daysBetween(bar.start, bar.end)
            const left = (offset / totalDays) * 100
            const widthPct = Math.max(1.2, (width / totalDays) * 100)
            const today = startOfDay(new Date())
            const todayLeft =
              today >= range.start && today <= range.end
                ? ((today.getTime() - range.start.getTime()) / 86400000 / totalDays) * 100
                : null
            return (
              <div key={bar.id} className="grid grid-cols-[200px_1fr] border-b border-slate-50 text-sm">
                <div className="truncate px-3 py-2">
                  <p className="truncate font-medium text-slate-900">{bar.label}</p>
                  <p className="truncate text-xs text-slate-500">
                    {bar.kind === 'phase' ? 'مرحلة' : bar.kind === 'milestone' ? 'معلم' : 'مهمة'}
                    {bar.assignee ? ` · ${bar.assignee}` : ''}
                    {bar.clientVisible ? ' · ظاهر للعميل' : ''}
                  </p>
                </div>
                <div className="relative h-10 bg-[linear-gradient(to_left,rgba(148,163,184,0.12)_1px,transparent_1px)] bg-[length:48px_100%]">
                  {todayLeft != null ? (
                    <span
                      className="absolute inset-y-0 w-px bg-rose-400/80"
                      style={{ left: `${todayLeft}%` }}
                      aria-hidden
                    />
                  ) : null}
                  <div
                    className={`absolute top-2 h-5 rounded-md ${kindTone(bar.kind, bar.status, bar.end)} text-[10px] text-white`}
                    style={{ left: `${left}%`, width: `${widthPct}%` }}
                    title={`${bar.label} · ${bar.status}`}
                  >
                    <span className="block truncate px-1 leading-5">
                      {bar.progress != null ? `${Math.round(bar.progress)}%` : bar.status}
                    </span>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
