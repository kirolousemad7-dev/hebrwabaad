import { Link } from 'react-router-dom'
import { useAuth } from '../../../context/AuthContext'
import type { WorkspaceDomainStatus } from '../../../types/api'
import { roleLabelFor } from '../../../utils/employeeWorkspace'
import { WorkspaceWidget } from '../WorkspaceWidget'

type RoleOverviewWidgetProps = {
  workspaceLabel: string
  intro: string
  domainOrder: readonly string[]
  domainLabels: Record<string, string>
  domains?: Record<string, WorkspaceDomainStatus>
  action?: { to: string; label: string }
}

export function RoleOverviewWidget({
  workspaceLabel,
  intro,
  domainOrder,
  domainLabels,
  domains,
  action,
}: RoleOverviewWidgetProps) {
  const { user } = useAuth()
  const roleLabel = roleLabelFor(user?.role)
  const domainEntries = domainOrder.flatMap((key) => {
    const domain = domains?.[key]
    return domain ? [[key, domain] as const] : []
  })

  return (
    <WorkspaceWidget title="نظرة عامة" className="md:col-span-2 xl:col-span-3">
      <dl className="grid gap-3 text-sm sm:grid-cols-3">
        <div>
          <dt className="text-slate-500">الموظف</dt>
          <dd className="font-medium">{user?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">الدور</dt>
          <dd>{roleLabel ?? user?.role ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">مساحة العمل</dt>
          <dd>{workspaceLabel}</dd>
        </div>
      </dl>
      <p className="text-sm text-slate-600">{intro}</p>
      {domainEntries.length > 0 ? (
        <ul className="flex flex-wrap gap-2" aria-label="حالة مجالات العمل">
          {domainEntries.map(([key, domain]) => (
            <li
              key={key}
              className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700"
            >
              <span>{domainLabels[key] ?? key}</span>
              <span className="mx-1 text-slate-400" aria-hidden="true">
                ·
              </span>
              <span>{domain.available ? 'متاح' : 'غير متاح بعد'}</span>
            </li>
          ))}
        </ul>
      ) : null}
      <div className="flex flex-wrap gap-2 pt-1">
        <Link
          to={action?.to ?? '/'}
          className="inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-4 py-2 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {action?.label ?? 'فتح الموقع'}
        </Link>
      </div>
    </WorkspaceWidget>
  )
}
