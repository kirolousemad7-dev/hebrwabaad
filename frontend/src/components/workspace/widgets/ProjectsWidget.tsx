import { Link } from 'react-router-dom'
import { useAsyncData } from '../../../hooks/useAsyncData'
import { getWorkspaceProjects } from '../../../services/workspaceProjects'
import { formatProjectProgress, PROJECT_STATUS_LABELS } from '../../../utils/workspaceProjects'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

export function ProjectsWidget() {
  const { state, reload } = useAsyncData(() => getWorkspaceProjects('?per_page=5'))

  return (
    <WorkspaceWidget title="المشاريع">
      {state.status === 'loading' ? <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-busy="true" /> : null}
      {state.status === 'error' ? <WorkspaceErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && state.data.items.length === 0 ? (
        <WorkspaceEmptyState title="لا توجد مشاريع بعد." description="ستظهر هنا المشاريع المرتبطة بمهامك أو التي تديرها." />
      ) : null}
      {state.status === 'ready' && state.data.items.length > 0 ? (
        <ul className="space-y-2 text-sm">
          {state.data.items.map((project) => (
            <li key={project.id}>
              <Link
                to={`/workspace/projects/${project.id}`}
                className="block rounded-xl border border-slate-200 p-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
              >
                <p className="font-medium">{project.title}</p>
                <p className="mt-1 text-slate-600">
                  {PROJECT_STATUS_LABELS[project.status] ?? project.status}
                  <span className="mx-1 text-slate-400" aria-hidden="true">
                    ·
                  </span>
                  التقدم {formatProjectProgress(project.progress.percent)}
                </p>
              </Link>
            </li>
          ))}
        </ul>
      ) : null}
      <Link
        to="/workspace/projects"
        className="inline-flex min-h-11 items-center text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        عرض كل المشاريع
      </Link>
    </WorkspaceWidget>
  )
}
