import { Link } from 'react-router-dom'
import { useAsyncData } from '../../../hooks/useAsyncData'
import { getManagedTasks, getMyTasks } from '../../../services/workspaceTasks'
import { formatTaskDeadline } from '../../../utils/workspaceTasks'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

type TaskDeadlinesWidgetProps = {
  managed?: boolean
}

export function TaskDeadlinesWidget({ managed = false }: TaskDeadlinesWidgetProps) {
  const { state, reload } = useAsyncData(() =>
    managed ? getManagedTasks('?upcoming=1&per_page=5') : getMyTasks('?upcoming=1&per_page=5'),
  )
  const items =
    state.status === 'ready'
      ? state.data.items
          .filter((task) => task.deadline && task.status !== 'COMPLETED')
          .sort((left, right) => String(left.deadline).localeCompare(String(right.deadline)))
          .slice(0, 5)
      : []

  return (
    <WorkspaceWidget title="المواعيد النهائية">
      {state.status === 'loading' ? <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-busy="true" /> : null}
      {state.status === 'error' ? <WorkspaceErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && items.length === 0 ? (
        <WorkspaceEmptyState title="لا توجد مواعيد نهائية." description="ستظهر هنا مواعيد المهام المعيّنة." />
      ) : null}
      {items.length > 0 ? (
        <ul className="space-y-2 text-sm">
          {items.map((task) => (
            <li key={task.id}>
              <p className="font-medium">{task.title}</p>
              <p className="text-slate-600">
                {formatTaskDeadline(task.deadline)}
                {task.is_overdue ? <span className="ms-2 text-red-700">متأخرة</span> : null}
              </p>
            </li>
          ))}
        </ul>
      ) : null}
      <Link
        to="/workspace/tasks"
        className="inline-flex min-h-11 items-center text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        فتح المهام
      </Link>
    </WorkspaceWidget>
  )
}
