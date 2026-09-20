import { Link } from 'react-router-dom'
import type { ProjectHealth, ProjectWorkspace } from '../../../services/operations'

type Props = {
  workspace: ProjectWorkspace
  onAddTask?: () => void
  onAddMilestone?: () => void
  onAddPhase?: () => void
  canManage?: boolean
}

function healthTone(status: string) {
  if (status === 'overdue') return 'border-red-300 bg-red-50 text-red-900'
  if (status === 'needs_attention') return 'border-amber-300 bg-amber-50 text-amber-900'
  return 'border-emerald-200 bg-emerald-50 text-emerald-900'
}

function warningLabel(health: ProjectHealth, progress: ProjectWorkspace['progress']) {
  if (health.status === 'overdue' || progress.overdue > 0) return 'متأخر'
  if (health.status === 'needs_attention') return 'يستحق المتابعة قريباً'
  return 'على المسار'
}

export function ProjectWorkspaceHeader({
  workspace,
  onAddTask,
  onAddMilestone,
  onAddPhase,
  canManage = true,
}: Props) {
  const { project, progress, health, current_phase, next_milestone, next_task, client_profile } = workspace
  const summary = workspace.execution_summary
  const industry =
    typeof client_profile?.industry === 'string' ? client_profile.industry : null
  const business =
    typeof client_profile?.company_name === 'string'
      ? client_profile.company_name
      : project.customer?.name

  return (
    <header className="space-y-4">
      <Link to="/owner/projects" className="text-sm text-slate-600 underline">
        العودة للمشاريع
      </Link>

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="min-w-0 space-y-1">
            <p className="text-xs uppercase tracking-wide text-slate-500">العميل</p>
            <p className="text-sm font-medium text-slate-800">
              {business ?? 'بدون عميل'}
              {industry ? ` · ${industry}` : ''}
            </p>
            <h1 className="text-2xl font-semibold text-slate-900">{project.title}</h1>
            <p className="text-sm text-slate-600">
              الحالة: {project.status}
              {project.started_at ? ` · البداية ${project.started_at}` : ''}
              {project.deadline ? ` · التسليم ${project.deadline}` : ''}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <span className={`rounded-full border px-3 py-1 text-xs ${healthTone(health.status)}`}>
              {health.label || warningLabel(health, progress)}
            </span>
            {(summary?.overdue_tasks ?? progress.overdue) > 0 ? (
              <span className="rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs text-red-900">
                متأخر: {(summary?.overdue_tasks ?? progress.overdue).toLocaleString('ar-SA')}
              </span>
            ) : null}
            {(summary?.attention_count ?? 0) > 0 ? (
              <span className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs text-amber-900">
                متابعة: {summary!.attention_count.toLocaleString('ar-SA')}
              </span>
            ) : null}
          </div>
        </div>

        <div className="mt-4">
          <div className="flex items-center justify-between text-sm">
            <span className="text-slate-600">تقدم المشروع</span>
            <span className="font-semibold">
              {Math.round(summary?.completion_percent ?? progress.percent).toLocaleString('ar-SA')}%
            </span>
          </div>
          <div className="mt-2 h-3 overflow-hidden rounded-full bg-slate-100">
            <div
              className="h-full rounded-full bg-brand-primary/80 transition-all"
              style={{
                width: `${Math.min(100, Math.max(0, summary?.completion_percent ?? progress.percent))}%`,
              }}
            />
          </div>
        </div>

        <dl className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
          <div>
            <dt className="text-xs text-slate-500">مدير الحساب</dt>
            <dd className="text-sm font-medium">{project.account_manager?.name ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">المرحلة الحالية</dt>
            <dd className="text-sm font-medium">
              {summary?.current_phase?.title ?? current_phase?.title ?? '—'}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">المعلم النشط</dt>
            <dd className="text-sm font-medium">
              {summary?.active_milestone?.title ?? next_milestone?.title ?? '—'}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">مهام مفتوحة</dt>
            <dd className="text-sm font-medium">
              {(summary?.open_tasks ?? Math.max(0, progress.total - progress.completed)).toLocaleString(
                'ar-SA',
              )}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">الموعد التالي</dt>
            <dd className="text-sm font-medium">
              {summary?.next_deadline ?? workspace.next_deadline ?? next_task?.deadline ?? '—'}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">بانتظار مراجعة</dt>
            <dd className="text-sm font-medium">
              {(summary?.in_review_tasks ?? progress.review + progress.revision).toLocaleString('ar-SA')}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">تحديثات حديثة</dt>
            <dd className="text-sm font-medium">
              {(summary?.recent_activity_count ?? workspace.recent_activity?.length ?? 0).toLocaleString(
                'ar-SA',
              )}
            </dd>
          </div>
        </dl>

        <div className="mt-4 flex flex-wrap gap-3 text-xs text-slate-600">
          <span>مكتمل {progress.completed.toLocaleString('ar-SA')}</span>
          <span>قيد التنفيذ {progress.in_progress.toLocaleString('ar-SA')}</span>
          <span>معلّق {progress.todo.toLocaleString('ar-SA')}</span>
          <span>الإجمالي {progress.total.toLocaleString('ar-SA')}</span>
          {(workspace.risks?.overdue_tasks ?? 0) > 0 ? (
            <span className="font-medium text-red-700">
              مهام متأخرة {workspace.risks!.overdue_tasks.toLocaleString('ar-SA')}
            </span>
          ) : null}
          {(workspace.risks?.overdue_milestones ?? 0) > 0 ? (
            <span className="font-medium text-red-700">
              معالم متأخرة {workspace.risks!.overdue_milestones.toLocaleString('ar-SA')}
            </span>
          ) : null}
          {workspace.risks?.due_soon ? (
            <span className="font-medium text-amber-800">موعد قريب خلال أسبوع</span>
          ) : null}
        </div>

        {canManage ? (
          <div className="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            {onAddTask ? (
              <button
                type="button"
                onClick={onAddTask}
                className="rounded-lg bg-slate-900 px-3 py-1.5 text-sm text-white"
              >
                + مهمة
              </button>
            ) : null}
            {onAddMilestone ? (
              <button
                type="button"
                onClick={onAddMilestone}
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
              >
                + معلم
              </button>
            ) : null}
            {onAddPhase ? (
              <button
                type="button"
                onClick={onAddPhase}
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
              >
                + مرحلة
              </button>
            ) : null}
            <Link to="/owner/work" className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm underline">
              فتح العمل الموحّد
            </Link>
          </div>
        ) : null}
      </div>
    </header>
  )
}
