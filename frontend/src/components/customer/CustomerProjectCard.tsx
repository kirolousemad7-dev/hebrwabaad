import { StatusBadge } from '../ui/StatusBadge'
import { Link } from 'react-router-dom'
import type { CustomerProject } from '../../types/api'
import { customerProjectPath } from '../../utils/customerDashboard'
import { formatProjectDate, formatProjectProgress, PROJECT_STATUS_LABELS } from '../../utils/workspaceProjects'

type CustomerProjectCardProps = {
  project: CustomerProject
}

export function CustomerProjectCard({ project }: CustomerProjectCardProps) {
  return (
    <article className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-xs text-slate-500">مشروع #{project.id}</p>
          <h3 className="font-semibold">{project.title}</h3>
        </div>
        <StatusBadge status={project.status} label={PROJECT_STATUS_LABELS[project.status] ?? project.status} />
      </div>
      <p className="text-sm text-slate-600">
        التقدم: {formatProjectProgress(project.progress.percent)} · المهام {project.progress.completed.toLocaleString('ar-SA')} /{' '}
        {project.progress.total.toLocaleString('ar-SA')}
      </p>
      <p className="text-sm text-slate-500">
        البداية: {formatProjectDate(project.started_at)} · التسليم: {formatProjectDate(project.deadline)}
      </p>
      {project.account_manager ? (
        <p className="text-sm text-slate-500">مدير الحساب: {project.account_manager.name}</p>
      ) : null}
      <Link
        to={customerProjectPath(project.id)}
        className="mt-auto inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        عرض المشروع
      </Link>
    </article>
  )
}
