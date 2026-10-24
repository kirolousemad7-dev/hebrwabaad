export const PROJECT_STATUS_LABELS: Record<string, string> = {
  PLANNING: 'تخطيط',
  IN_PROGRESS: 'قيد التنفيذ',
  REVIEW: 'مراجعة',
  COMPLETED: 'مكتمل',
  CANCELLED: 'ملغى',
}

export const PROJECT_STATUS_FILTERS = [
  { value: '', label: 'كل المشاريع' },
  { value: 'PLANNING', label: 'تخطيط' },
  { value: 'IN_PROGRESS', label: 'قيد التنفيذ' },
  { value: 'REVIEW', label: 'مراجعة' },
  { value: 'COMPLETED', label: 'مكتملة' },
  { value: 'CANCELLED', label: 'ملغاة' },
] as const

export const TASK_STATUS_LABELS: Record<string, string> = {
  TODO: 'جديدة',
  IN_PROGRESS: 'قيد التنفيذ',
  REVIEW: 'تحت المراجعة',
  REVISION: 'بانتظار تعديل',
  COMPLETED: 'مكتملة',
}

type StatusVisual = {
  strip: string
  badge: string
  bar: string
}

const STATUS_VISUAL: Record<string, StatusVisual> = {
  PLANNING: {
    strip: 'bg-brand-cobalt-500',
    badge: 'border-brand-cobalt-300 bg-brand-cobalt-100 text-brand-cobalt-900',
    bar: 'bg-brand-cobalt-500',
  },
  IN_PROGRESS: {
    strip: 'bg-emerald-600',
    badge: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    bar: 'bg-emerald-600',
  },
  REVIEW: {
    strip: 'bg-amber-500',
    badge: 'border-amber-200 bg-amber-50 text-amber-900',
    bar: 'bg-amber-500',
  },
  COMPLETED: {
    strip: 'bg-emerald-700',
    badge: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    bar: 'bg-emerald-700',
  },
  CANCELLED: {
    strip: 'bg-slate-300',
    badge: 'border-slate-200 bg-slate-100 text-slate-600',
    bar: 'bg-slate-400',
  },
}

export function projectStatusVisual(status: string): StatusVisual {
  return STATUS_VISUAL[status] ?? STATUS_VISUAL.PLANNING
}

export function projectInitials(title: string): string {
  const parts = title.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) {
    return 'م'
  }
  if (parts.length === 1) {
    return parts[0].slice(0, 2)
  }
  return `${parts[0].slice(0, 1)}${parts[1].slice(0, 1)}`
}

export function formatProjectDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleDateString('ar-SA', { dateStyle: 'medium' })
}

export function formatProjectProgress(percent: number): string {
  return `${percent.toLocaleString('ar-SA')}٪`
}
