import type { CalendarRecurrenceScope } from '../services/calendar'

/** Ask for recurrence edit/delete/reschedule scope. Returns null if cancelled. */
export function askRecurrenceScope(actionLabel = 'تطبيق التغيير'): CalendarRecurrenceScope | null {
  const choice = window.prompt(
    `${actionLabel} على التكرار:\n1 = هذا فقط\n2 = هذا وما بعده\n3 = الكل\n\nأدخل الرقم:`,
    '1',
  )
  if (choice === null) return null
  const trimmed = choice.trim()
  if (trimmed === '1' || trimmed === 'هذا' || trimmed === 'this') return 'this'
  if (trimmed === '2' || trimmed === 'future') return 'future'
  if (trimmed === '3' || trimmed === 'all' || trimmed === 'الكل') return 'all'
  return 'this'
}

export function itemHasRecurrence(item: {
  recurrence_rule?: string | null
  is_occurrence?: boolean
}): boolean {
  return Boolean(item.is_occurrence || (item.recurrence_rule && item.recurrence_rule.trim() !== ''))
}
