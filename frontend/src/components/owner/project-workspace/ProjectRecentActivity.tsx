import type { ProjectTimelineEvent } from '../../../services/operations'

type Props = {
  events: ProjectTimelineEvent[] | null | undefined
  emptyLabel?: string
}

export function ProjectRecentActivity({ events, emptyLabel = 'لا تحديثات حديثة.' }: Props) {
  const rows = events ?? []

  if (rows.length === 0) {
    return <p className="text-sm text-slate-500">{emptyLabel}</p>
  }

  return (
    <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
      {rows.slice(0, 12).map((event, index) => (
        <li key={`${event.type}-${event.related_id ?? 'x'}-${event.occurred_at}-${index}`} className="px-3 py-2.5 text-sm">
          <p className="font-medium text-slate-900">{event.title}</p>
          <p className="text-xs text-slate-500">
            {event.type}
            {event.occurred_at
              ? ` · ${new Date(event.occurred_at).toLocaleString('ar-SA', {
                  dateStyle: 'medium',
                  timeStyle: 'short',
                })}`
              : ''}
          </p>
        </li>
      ))}
    </ul>
  )
}
