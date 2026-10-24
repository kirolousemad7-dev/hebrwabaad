import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import type { ProjectHealth, ProjectWorkspace } from '../../../services/operations'
import { PROJECT_STATUS_LABELS, formatProjectDate, projectStatusVisual } from '../../../utils/workspaceProjects'
import { ProjectCoverControl } from '../ProjectCoverControl'

type Props = {
  workspace: ProjectWorkspace
  onAddTask?: () => void
  onAddMilestone?: () => void
  onAddPhase?: () => void
  canManage?: boolean
  onCoverUpdated?: (url: string | null) => void
}

function healthTone(status: string) {
  if (status === 'overdue') return 'bg-red-50 text-red-800'
  if (status === 'needs_attention') return 'bg-amber-50 text-amber-900'
  return 'bg-emerald-50 text-emerald-800'
}

function warningLabel(health: ProjectHealth, progress: ProjectWorkspace['progress']) {
  if (health.status === 'overdue' || progress.overdue > 0) return 'متأخر'
  if (health.status === 'needs_attention') return 'يستحق المتابعة'
  return 'على المسار'
}

const primaryButton =
  'inline-flex min-h-10 items-center rounded-lg bg-brand-cobalt-500 px-3 text-sm font-medium text-white transition hover:bg-brand-cobalt-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500'
const secondaryButton =
  'inline-flex min-h-10 items-center rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 transition hover:border-slate-300'

export function ProjectWorkspaceHeader({
  workspace,
  onAddTask,
  onAddMilestone,
  onAddPhase,
  canManage = true,
  onCoverUpdated,
}: Props) {
  const { project, progress, health, current_phase, client_profile } = workspace
  const summary = workspace.execution_summary
  const [coverUrl, setCoverUrl] = useState(project.cover_url ?? null)
  useEffect(() => {
    setCoverUrl(project.cover_url ?? null)
  }, [project.id, project.cover_url])
  const statusVisual = projectStatusVisual(project.status)
  const percent = Math.round(summary?.completion_percent ?? progress.percent)
  const industry = typeof client_profile?.industry === 'string' ? client_profile.industry : null
  const business =
    typeof client_profile?.company_name === 'string' ? client_profile.company_name : project.customer?.name
  const teamCount = workspace.team_stats?.length ?? workspace.members.length
  const overdue = summary?.overdue_tasks ?? progress.overdue
  const phaseTitle = summary?.current_phase?.title ?? current_phase?.title

  return (
    <header className="space-y-3">
      <Link to="/owner/projects" className="text-sm text-slate-500 transition hover:text-slate-800">
        العودة للمشاريع
      </Link>

      <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex min-w-0 items-start gap-3">
            {canManage ? (
              <ProjectCoverControl
                projectId={project.id}
                title={project.title}
                coverUrl={coverUrl}
                onUpdated={(url) => {
                  setCoverUrl(url)
                  onCoverUpdated?.(url)
                }}
              />
            ) : null}
            <div className="min-w-0">
              <p className="truncate text-sm text-slate-500">{business ?? 'بدون عميل'}{industry ? ` · ${industry}` : ''}</p>
              <h1 className="mt-0.5 text-xl font-semibold leading-8 text-slate-900 sm:text-2xl">{project.title}</h1>
              <span className={`mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${statusVisual.badge}`}>
                {PROJECT_STATUS_LABELS[project.status] ?? project.status}
              </span>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${healthTone(health.status)}`}>
              {health.label || warningLabel(health, progress)}
            </span>
            {canManage && onAddTask ? (
              <button type="button" onClick={onAddTask} className={primaryButton}>
                إنشاء مهمة
              </button>
            ) : null}
          </div>
        </div>

        <div className="mt-4 grid gap-4 border-t border-slate-100 pt-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
          <div>
            <div className="flex flex-wrap items-end justify-between gap-2">
              <p className="text-2xl font-semibold text-slate-900">{percent.toLocaleString('en-US')}% مكتمل</p>
              <p className="text-sm text-slate-500">
                {progress.completed.toLocaleString('en-US')} من {progress.total.toLocaleString('en-US')} مهام مكتملة
              </p>
            </div>
            <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" dir="ltr">
              <div
                className={`ms-auto h-full rounded-full ${statusVisual.bar} transition-[width] duration-500`}
                style={{ width: `${Math.min(100, Math.max(0, percent))}%` }}
              />
            </div>
            {overdue > 0 ? (
              <p className="mt-2 text-xs font-medium text-red-700">
                {overdue.toLocaleString('en-US')} مهمة متأخرة
              </p>
            ) : null}
          </div>
          <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <div>
              <dt className="text-xs text-slate-500">البداية</dt>
              <dd className="font-medium text-slate-800">{formatProjectDate(project.started_at)}</dd>
            </div>
            <div>
              <dt className="text-xs text-slate-500">التسليم</dt>
              <dd className="font-medium text-slate-800">{formatProjectDate(project.deadline)}</dd>
            </div>
            <div>
              <dt className="text-xs text-slate-500">مدير الحساب</dt>
              <dd className="truncate font-medium text-slate-800">{project.account_manager?.name ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs text-slate-500">الفريق</dt>
              <dd className="font-medium text-slate-800">{teamCount.toLocaleString('en-US')}</dd>
            </div>
          </dl>
        </div>
        {phaseTitle ? <p className="mt-3 text-xs text-slate-500">المرحلة الحالية: {phaseTitle}</p> : null}

        {canManage && (onAddMilestone || onAddPhase) ? (
          <div className="mt-4 flex flex-wrap gap-2">
            {onAddMilestone ? (
              <button type="button" onClick={onAddMilestone} className={secondaryButton}>
                معلم
              </button>
            ) : null}
            {onAddPhase ? (
              <button type="button" onClick={onAddPhase} className={secondaryButton}>
                مرحلة
              </button>
            ) : null}
            <Link to="/owner/work" className={secondaryButton}>
              فتح العمل الموحّد
            </Link>
          </div>
        ) : null}
      </div>
    </header>
  )
}
