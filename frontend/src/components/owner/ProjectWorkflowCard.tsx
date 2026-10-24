import { useState } from 'react'
import { Link } from 'react-router-dom'
import type { OperationsProjectListItem } from '../../services/operations'
import { resolveMediaUrl } from '../../utils/mediaUrl'
import {
  PROJECT_STATUS_LABELS,
  formatProjectDate,
  projectInitials,
  projectStatusVisual,
} from '../../utils/workspaceProjects'

function count(value: number): string {
  return value.toLocaleString('en-US')
}

export function ProjectWorkflowCard({ project }: { project: OperationsProjectListItem }) {
  const visual = projectStatusVisual(project.status)
  const progress = project.progress
  const percent = Math.round(progress?.percent ?? 0)
  const image = resolveMediaUrl(project.cover_url)
  const [failed, setFailed] = useState(false)
  const client = project.customer?.name

  return (
    <Link
      to={`/owner/projects/${project.id}`}
      className="group flex h-full min-w-0 cursor-pointer flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
    >
      <span className={`h-1.5 w-full ${visual.strip}`} aria-hidden="true" />
      <div className="flex flex-1 flex-col gap-3 p-4">
        {image && !failed ? (
          <img
            src={image}
            alt=""
            className="h-28 w-full rounded-xl object-cover"
            loading="lazy"
            onError={() => setFailed(true)}
          />
        ) : (
          <span className="inline-flex h-14 w-14 items-center justify-center rounded-xl bg-slate-100 text-sm font-semibold text-slate-700">
            {projectInitials(project.title)}
          </span>
        )}

        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <h2 className="truncate text-base font-semibold text-slate-900">{project.title}</h2>
            {client ? <p className="truncate text-xs text-slate-500">{client}</p> : null}
          </div>
          <span className={`shrink-0 rounded-full border px-2 py-0.5 text-xs ${visual.badge}`}>
            {PROJECT_STATUS_LABELS[project.status] ?? project.status}
          </span>
        </div>

        <div>
          <div className="mb-1 flex items-center justify-between text-xs text-slate-500">
            <span>التقدم</span>
            <span className="font-medium text-slate-800">{count(percent)}% مكتمل</span>
          </div>
          <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div className={`h-full rounded-full ${visual.bar}`} style={{ width: `${Math.min(100, Math.max(0, percent))}%` }} />
          </div>
        </div>

        <div className="rounded-xl bg-[#f4f6f8] px-3 py-2 text-xs text-slate-600">
          <p className="font-medium text-slate-800">المهام · {count(progress?.total ?? 0)} إجمالي</p>
          <p className="mt-1">
            {count(progress?.completed ?? 0)} مكتملة · {count(progress?.in_progress ?? 0)} قيد التنفيذ · {count(progress?.overdue ?? 0)} متأخرة
          </p>
        </div>

        <p className="mt-auto text-xs text-slate-500">التسليم {formatProjectDate(project.deadline)}</p>
      </div>
    </Link>
  )
}
