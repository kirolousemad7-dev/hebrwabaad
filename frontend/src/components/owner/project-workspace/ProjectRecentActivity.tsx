import type { ProjectActivity } from '../../../types/api'
import { formatDateTimeShort } from '../../../utils/calendarDates'
import { projectActivityHeadline } from '../../../utils/projectActivityLabels'

type Props = {
  activities: ProjectActivity[] | null | undefined
  emptyLabel?: string
  loading?: boolean
  error?: string | null
  limit?: number
  taskTitles?: Record<number, string>
}

export function ProjectRecentActivity({
  activities,
  emptyLabel = 'لا تحديثات حديثة.',
  loading = false,
  error = null,
  limit = 5,
  taskTitles,
}: Props) {
  if (loading) {
    return (
      <div className="space-y-2" aria-busy="true" aria-label="جاري تحميل النشاط">
        {[0, 1, 2].map((index) => (
          <div key={index} className="h-12 animate-pulse rounded-xl bg-slate-100" />
        ))}
      </div>
    )
  }

  if (error) {
    return <p className="text-sm text-red-700">{error}</p>
  }

  const rows = activities ?? []

  if (rows.length === 0) {
    return <p className="text-sm text-slate-500">{emptyLabel}</p>
  }

  return (
    <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
      {rows.slice(0, limit).map((activity) => (
        <li key={activity.id} className="px-3 py-2.5 text-sm">
          <p className="font-medium leading-6 text-slate-900">{projectActivityHeadline(activity, taskTitles)}</p>
          <p className="text-xs text-slate-500">
            {activity.actor?.name ? `${activity.actor.name} · ` : ''}
            {activity.created_at ? formatDateTimeShort(activity.created_at) : ''}
          </p>
        </li>
      ))}
    </ul>
  )
}
