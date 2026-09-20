import type { CustomerProjectActivity } from '../../types/api'
import { formatDateTimeShort } from '../../utils/calendarDates'
import {
  customerProjectActivityActorLabel,
  customerProjectActivityPrimary,
} from '../../utils/customerProjectActivity'

type Props = {
  activities: CustomerProjectActivity[] | null | undefined
  emptyLabel?: string
  loading?: boolean
  error?: string | null
}

export function CustomerProjectActivityList({
  activities,
  emptyLabel = 'لا تحديثات ظاهرة بعد.',
  loading = false,
  error = null,
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
      {rows.slice(0, 12).map((activity) => {
        const actor = customerProjectActivityActorLabel(activity)
        const when = activity.created_at ? formatDateTimeShort(activity.created_at) : null

        return (
          <li key={activity.id} className="px-3 py-2.5 text-sm">
            <p className="font-medium text-slate-900">{customerProjectActivityPrimary(activity)}</p>
            <p className="text-xs text-slate-500">
              {[actor, when].filter(Boolean).join(' · ')}
            </p>
          </li>
        )
      })}
    </ul>
  )
}
