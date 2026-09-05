import { Link } from 'react-router-dom'
import { useAsyncData } from '../../../hooks/useAsyncData'
import { getManagedTasks } from '../../../services/workspaceTasks'
import { TASK_STATUS_LABELS } from '../../../utils/workspaceTasks'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

export function AccountManagerTasksWidget() {
  const { state, reload } = useAsyncData(() => getManagedTasks('?per_page=5'))

  return (
    <WorkspaceWidget title="المهام">
      {state.status === 'loading' ? <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-busy="true" /> : null}
      {state.status === 'error' ? <WorkspaceErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && state.data.items.length === 0 ? (
        <WorkspaceEmptyState title="لا توجد مهام بعد." description="أنشئ مهمة وعيّنها لموظف نشط من أدوار العمل." />
      ) : null}
      {state.status === 'ready' && state.data.items.length > 0 ? (
        <ul className="space-y-2 text-sm">
          {state.data.items.map((task) => (
            <li key={task.id}>
              <p className="font-medium">
                <Link
                  to={`/workspace/tasks/${task.id}`}
                  className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  {task.title}
                </Link>
              </p>
              <p className="text-slate-600">
                {task.project?.title ?? '—'}
                <span className="mx-1 text-slate-400" aria-hidden="true">
                  ·
                </span>
                {task.assignee?.name ?? '—'}
                <span className="mx-1 text-slate-400" aria-hidden="true">
                  ·
                </span>
                {TASK_STATUS_LABELS[task.status] ?? task.status}
              </p>
            </li>
          ))}
        </ul>
      ) : null}
      <Link
        to="/workspace/tasks"
        className="inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-4 py-2 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        إنشاء مهمة
      </Link>
    </WorkspaceWidget>
  )
}
