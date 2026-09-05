import { Link } from 'react-router-dom'
import { TaskStatusSelect } from '../WorkspaceListControls'
import { useAsyncData } from '../../../hooks/useAsyncData'
import { getMyTasks, updateMyTaskStatus } from '../../../services/workspaceTasks'
import { TASK_PRIORITY_LABELS, TASK_STATUS_LABELS, formatTaskAssignedDate, formatTaskDeadline } from '../../../utils/workspaceTasks'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

export function MyTasksWidget() {
  const { state, reload } = useAsyncData(() => getMyTasks('?per_page=5'))

  return (
    <WorkspaceWidget title="مهامي">
      {state.status === 'loading' ? <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-busy="true" /> : null}
      {state.status === 'error' ? <WorkspaceErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && state.data.items.length === 0 ? (
        <WorkspaceEmptyState title="لا توجد مهام معيّنة." description="ستظهر هنا المهام التي يعيّنها الأكونت مانجر." />
      ) : null}
      {state.status === 'ready' && state.data.items.length > 0 ? (
        <ul className="space-y-3 text-sm">
          {state.data.items.map((task) => (
            <li key={task.id} className="rounded-xl border border-slate-200 p-3">
              <p className="font-medium">
                <Link
                  to={`/workspace/tasks/${task.id}`}
                  className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  {task.title}
                </Link>
              </p>
              {task.project ? <p className="mt-1 text-xs text-slate-500">{task.project.title}</p> : null}
              <p className="mt-1 text-slate-600">
                {TASK_PRIORITY_LABELS[task.priority] ?? task.priority}
                <span className="mx-1 text-slate-400" aria-hidden="true">
                  ·
                </span>
                {TASK_STATUS_LABELS[task.status] ?? task.status}
                <span className="mx-1 text-slate-400" aria-hidden="true">
                  ·
                </span>
                {formatTaskDeadline(task.deadline)}
                {task.is_overdue ? <span className="ms-2 text-red-700">متأخرة</span> : null}
              </p>
              <p className="mt-1 text-xs text-slate-500">تاريخ التعيين: {formatTaskAssignedDate(task.created_at)}</p>
              <label className="mt-2 block text-xs text-slate-500">
                تحديث الحالة
                <TaskStatusSelect
                  value={task.status}
                  label={`تحديث حالة ${task.title}`}
                  onChange={(status) => {
                    void updateMyTaskStatus(task.id, status).then(() => reload())
                  }}
                />
              </label>
            </li>
          ))}
        </ul>
      ) : null}
      <Link
        to="/workspace/tasks"
        className="inline-flex min-h-11 items-center text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        عرض كل المهام
      </Link>
    </WorkspaceWidget>
  )
}
