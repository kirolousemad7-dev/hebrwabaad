export const TASK_STATUS_LABELS: Record<string, string> = {
  TODO: 'قيد الانتظار',
  IN_PROGRESS: 'قيد التنفيذ',
  REVIEW: 'مراجعة',
  REVISION: 'تحتاج تعديلاً',
  COMPLETED: 'مكتملة',
}

export const TASK_PRIORITY_LABELS: Record<string, string> = {
  LOW: 'منخفضة',
  MEDIUM: 'متوسطة',
  HIGH: 'مرتفعة',
  URGENT: 'عاجلة',
}

export function formatTaskDeadline(value: string | null | undefined): string {
  return formatTaskDate(value)
}

export function formatTaskAssignedDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleDateString('ar-SA', { dateStyle: 'medium' })
}

function formatTaskDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleDateString('ar-SA', { dateStyle: 'medium' })
}
