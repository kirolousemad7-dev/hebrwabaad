import { toneForStatus, type StatusTone } from '../../utils/statusTone'

const TONE_CLASS: Record<StatusTone, string> = {
  neutral: 'bg-slate-100 text-slate-700',
  pending: 'bg-slate-100 text-slate-700',
  progress: 'bg-sky-100 text-sky-900',
  review: 'bg-teal-50 text-teal-900',
  revision: 'bg-orange-50 text-brand-orange',
  success: 'bg-emerald-100 text-emerald-800',
  danger: 'bg-red-100 text-red-800',
  warning: 'bg-amber-100 text-amber-900',
  unavailable: 'bg-amber-50 text-amber-900',
}

type StatusBadgeProps = {
  status?: string | null
  label: string
  tone?: StatusTone
}

export function StatusBadge({ status, label, tone }: StatusBadgeProps) {
  const resolved = tone ?? toneForStatus(status)

  return (
    <span className={`inline-flex max-w-full items-center rounded-full px-3 py-1 text-xs font-medium ${TONE_CLASS[resolved]}`}>
      <span className="truncate">{label}</span>
    </span>
  )
}
