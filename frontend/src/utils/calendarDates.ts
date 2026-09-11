export type CalendarViewMode = 'month' | 'week' | 'day' | 'agenda'

export function toDateInput(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function toDateTimeLocalValue(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

export function parseDateInput(value: string): Date {
  const [year, month, day] = value.split('-').map(Number)
  return new Date(year, (month ?? 1) - 1, day ?? 1)
}

export function startOfDay(date: Date): Date {
  const copy = new Date(date)
  copy.setHours(0, 0, 0, 0)
  return copy
}

export function endOfDay(date: Date): Date {
  const copy = new Date(date)
  copy.setHours(23, 59, 59, 999)
  return copy
}

/** Arabic calendars typically start the week on Sunday. */
export function startOfWeek(date: Date): Date {
  const copy = startOfDay(date)
  copy.setDate(copy.getDate() - copy.getDay())
  return copy
}

export function endOfWeek(date: Date): Date {
  const start = startOfWeek(date)
  const end = new Date(start)
  end.setDate(start.getDate() + 6)
  end.setHours(23, 59, 59, 999)
  return end
}

export function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

export function endOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0, 23, 59, 59, 999)
}

export function rangeFor(view: CalendarViewMode, cursor: Date): { from: string; to: string } {
  if (view === 'day') {
    const day = toDateInput(cursor)
    return { from: day, to: day }
  }

  if (view === 'week') {
    return { from: toDateInput(startOfWeek(cursor)), to: toDateInput(endOfWeek(cursor)) }
  }

  if (view === 'agenda') {
    const from = startOfDay(cursor)
    const to = new Date(from)
    to.setDate(from.getDate() + 13)
    to.setHours(23, 59, 59, 999)
    return { from: toDateInput(from), to: toDateInput(to) }
  }

  // Month view: include leading/trailing days so the grid matches the query range.
  const monthStart = startOfMonth(cursor)
  const monthEnd = endOfMonth(cursor)
  return {
    from: toDateInput(startOfWeek(monthStart)),
    to: toDateInput(endOfWeek(monthEnd)),
  }
}

export function shiftCursor(view: CalendarViewMode, cursor: Date, direction: -1 | 1): Date {
  const next = new Date(cursor)

  if (view === 'day') {
    next.setDate(next.getDate() + direction)
  } else if (view === 'week' || view === 'agenda') {
    next.setDate(next.getDate() + direction * (view === 'agenda' ? 14 : 7))
  } else {
    next.setMonth(next.getMonth() + direction)
  }

  return next
}

export function formatMonthTitle(date: Date): string {
  return date.toLocaleDateString('ar-SA', { month: 'long', year: 'numeric' })
}

export function formatDayTitle(date: Date): string {
  return date.toLocaleDateString('ar-SA', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

export function formatTimeShort(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' })
}

export function formatDateTimeShort(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ar-SA', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function sameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  )
}

export function dateKeyFromIso(iso: string | null | undefined, allDay = false): string {
  if (!iso) return 'unknown'
  // All-day values are stored as UTC midnight of the calendar date — never shift via local Date.
  if (allDay || /T00:00:00(\.000)?(Z|[+-]00:00)$/.test(iso)) {
    return iso.slice(0, 10)
  }
  return toDateInput(new Date(iso))
}

export function buildMonthCells(cursor: Date): Date[] {
  const start = startOfWeek(startOfMonth(cursor))
  const end = endOfWeek(endOfMonth(cursor))
  const cells: Date[] = []
  const walk = new Date(start)

  while (walk <= end) {
    cells.push(new Date(walk))
    walk.setDate(walk.getDate() + 1)
  }

  return cells
}

export function buildWeekDays(cursor: Date): Date[] {
  const start = startOfWeek(cursor)
  return Array.from({ length: 7 }, (_, index) => {
    const day = new Date(start)
    day.setDate(start.getDate() + index)
    return day
  })
}

/** Timed local datetime → UTC ISO. All-day → UTC midnight of the selected calendar date. */
export function isoFromDateTimeLocal(value: string, allDay = false): string {
  if (allDay) {
    const datePart = value.slice(0, 10)
    if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
      return new Date(value).toISOString()
    }
    return `${datePart}T00:00:00.000Z`
  }

  return new Date(value).toISOString()
}

export function dateTimeLocalFromIso(iso: string | null | undefined, fallback?: Date): string {
  if (!iso) {
    return toDateTimeLocalValue(fallback ?? new Date())
  }

  if (/T00:00:00(\.000)?(Z|[+-]00:00)$/.test(iso)) {
    const datePart = iso.slice(0, 10)
    return `${datePart}T00:00`
  }

  return toDateTimeLocalValue(new Date(iso))
}
