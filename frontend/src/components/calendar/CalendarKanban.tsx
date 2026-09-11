import { useMemo, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { StatusBadge } from '../ui/StatusBadge'
import {
  bulkUpdateCalendarItems,
  updateCalendarItem,
  type CalendarItem,
  type CalendarItemStatus,
} from '../../services/calendar'
import {
  calendarPriorityLabel,
  calendarStatusLabel,
  calendarStatusTone,
  calendarTypeLabel,
} from '../../utils/calendarLabels'
import { formatTimeShort } from '../../utils/calendarDates'
import { describeApiError } from '../../utils/errors'

const COLUMNS: CalendarItemStatus[] = ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED']

type CalendarKanbanProps = {
  items: CalendarItem[]
  onOpenItem: (item: CalendarItem) => void
  onChanged: () => void
}

export function CalendarKanban({ items, onOpenItem, onChanged }: CalendarKanbanProps) {
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [draggingId, setDraggingId] = useState<string | null>(null)

  const byStatus = useMemo(() => {
    const map = new Map<string, CalendarItem[]>()
    for (const status of COLUMNS) map.set(status, [])
    for (const item of items) {
      const key = COLUMNS.includes(item.status as CalendarItemStatus)
        ? item.status
        : item.status === 'OVERDUE'
          ? 'SCHEDULED'
          : 'SCHEDULED'
      const list = map.get(key) ?? []
      list.push(item)
      map.set(key, list)
    }
    return map
  }, [items])

  async function moveToStatus(item: CalendarItem, status: string) {
    if (item.status === status) return
    if (!item.can_edit || item.is_linked) {
      setError('لا يمكن نقل عنصر مرتبط أو غير قابل للتعديل.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      if (status === 'COMPLETED') {
        await bulkUpdateCalendarItems([item.id], { status: 'COMPLETED' })
      } else {
        await updateCalendarItem(item.id, { status })
      }
      onChanged()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث الحالة.'))
    } finally {
      setBusy(false)
      setDraggingId(null)
    }
  }

  return (
    <div className="min-w-0 space-y-3">
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      <div className="grid min-w-0 gap-3 md:grid-cols-2 xl:grid-cols-4">
        {COLUMNS.map((status) => {
          const columnItems = byStatus.get(status) ?? []
          return (
            <section
              key={status}
              className="min-w-0 rounded-2xl border border-slate-200 bg-slate-50/60 p-3"
              onDragOver={(event) => {
                event.preventDefault()
              }}
              onDrop={(event) => {
                event.preventDefault()
                const raw = event.dataTransfer.getData('text/calendar-item-id')
                if (!raw) return
                const item = items.find((entry) => String(entry.id) === raw)
                if (item) void moveToStatus(item, status)
              }}
            >
              <div className="mb-3 flex items-center justify-between gap-2">
                <StatusBadge status={status} label={calendarStatusLabel(status)} tone={calendarStatusTone(status)} />
                <span className="text-xs text-slate-500">{columnItems.length.toLocaleString('ar-SA')}</span>
              </div>
              <ul className="space-y-2">
                {columnItems.length === 0 ? (
                  <li className="rounded-xl border border-dashed border-slate-200 bg-white px-3 py-6 text-center text-xs text-slate-400">
                    اسحب هنا
                  </li>
                ) : (
                  columnItems.map((item) => {
                    const draggable = item.can_edit && !item.is_linked && !busy
                    return (
                      <li key={String(item.id)}>
                        <button
                          type="button"
                          draggable={draggable}
                          onDragStart={(event) => {
                            if (!draggable) {
                              event.preventDefault()
                              return
                            }
                            setDraggingId(String(item.id))
                            event.dataTransfer.setData('text/calendar-item-id', String(item.id))
                            event.dataTransfer.effectAllowed = 'move'
                          }}
                          onDragEnd={() => setDraggingId(null)}
                          onClick={() => onOpenItem(item)}
                          className={`w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-start text-sm shadow-sm hover:border-amber-300 ${
                            draggingId === String(item.id) ? 'opacity-50' : ''
                          } ${item.status === 'COMPLETED' ? 'opacity-70' : ''}`}
                        >
                          <p className="font-medium text-slate-900">{item.title}</p>
                          <p className="mt-1 text-xs text-slate-500">
                            {calendarTypeLabel(item.type)} · {formatTimeShort(item.starts_at)}
                            {item.priority ? ` · ${calendarPriorityLabel(item.priority)}` : ''}
                          </p>
                        </button>
                      </li>
                    )
                  })
                )}
              </ul>
            </section>
          )
        })}
      </div>
    </div>
  )
}
