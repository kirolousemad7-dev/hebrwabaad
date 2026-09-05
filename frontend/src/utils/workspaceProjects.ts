export const PROJECT_STATUS_LABELS: Record<string, string> = {
  PLANNING: 'تخطيط',
  IN_PROGRESS: 'قيد التنفيذ',
  REVIEW: 'مراجعة',
  COMPLETED: 'مكتمل',
  CANCELLED: 'ملغى',
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
