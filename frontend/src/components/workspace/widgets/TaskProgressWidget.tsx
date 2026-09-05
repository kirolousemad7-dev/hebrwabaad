import { Link } from 'react-router-dom'
import { useAsyncData } from '../../../hooks/useAsyncData'
import { getManagedTasks } from '../../../services/workspaceTasks'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

export function TaskProgressWidget() {
  const { state, reload } = useAsyncData(() => getManagedTasks('?per_page=1'))
  const summary = state.status === 'ready' ? state.data.summary : null

  return (
    <WorkspaceWidget title="تقدم المهام">
      {state.status === 'loading' ? <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-busy="true" /> : null}
      {state.status === 'error' ? <WorkspaceErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && summary ? (
        <dl className="grid grid-cols-2 gap-3 text-sm">
          <div>
            <dt className="text-slate-500">إجمالي المهام</dt>
            <dd className="text-lg font-semibold">{summary.total.toLocaleString('ar-SA')}</dd>
          </div>
          <div>
            <dt className="text-slate-500">قيد التنفيذ</dt>
            <dd className="text-lg font-semibold">{summary.in_progress.toLocaleString('ar-SA')}</dd>
          </div>
          <div>
            <dt className="text-slate-500">مكتملة</dt>
            <dd className="text-lg font-semibold">{summary.completed.toLocaleString('ar-SA')}</dd>
          </div>
          <div>
            <dt className="text-slate-500">متأخرة</dt>
            <dd className="text-lg font-semibold">{summary.overdue.toLocaleString('ar-SA')}</dd>
          </div>
        </dl>
      ) : null}
      {state.status === 'ready' && summary?.total === 0 ? (
        <WorkspaceEmptyState title="لا توجد مهام بعد." description="أنشئ مهمة وعيّنها لموظف نشط." />
      ) : null}
      <Link
        to="/workspace/tasks"
        className="inline-flex min-h-11 items-center text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        إدارة المهام
      </Link>
    </WorkspaceWidget>
  )
}
