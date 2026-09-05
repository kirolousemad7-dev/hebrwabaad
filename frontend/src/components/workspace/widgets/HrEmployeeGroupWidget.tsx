import { Link } from 'react-router-dom'
import { useAsyncData } from '../../../hooks/useAsyncData'
import { getHrEmployees } from '../../../services/workspaceTasks'
import { roleLabelFor } from '../../../utils/employeeWorkspace'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

type HrEmployeeGroupWidgetProps = {
  active: boolean
}

export function HrEmployeeGroupWidget({ active }: HrEmployeeGroupWidgetProps) {
  const query = active ? '?is_active=true&per_page=5' : '?is_active=false&per_page=5'
  const { state, reload } = useAsyncData(() => getHrEmployees(query))
  const title = active ? 'الموظفون النشطون' : 'الموظفون غير النشطين'
  const empty = active ? 'لا يوجد موظفون نشطون.' : 'لا يوجد موظفون غير نشطين.'

  return (
    <WorkspaceWidget title={title}>
      {state.status === 'loading' ? <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-busy="true" /> : null}
      {state.status === 'error' ? <WorkspaceErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && state.data.summary ? (
        <p className="text-sm text-slate-600">
          {active
            ? `${state.data.summary.active.toLocaleString('ar-SA')} نشط`
            : `${state.data.summary.inactive.toLocaleString('ar-SA')} غير نشط`}
        </p>
      ) : null}
      {state.status === 'ready' && state.data.items.length === 0 ? (
        <WorkspaceEmptyState title={empty} description="الأرقام والحالات تأتي من حسابات الموظفين الحقيقية." />
      ) : null}
      {state.status === 'ready' && state.data.items.length > 0 ? (
        <ul className="space-y-2 text-sm">
          {state.data.items.map((employee) => (
            <li key={employee.id} className="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 py-2">
              <span className="min-w-0 truncate font-medium">{employee.name}</span>
              <span className="shrink-0 text-slate-600">{roleLabelFor(employee.role) ?? employee.role}</span>
            </li>
          ))}
        </ul>
      ) : null}
      <Link
        to="/workspace/directory"
        className="inline-flex min-h-11 items-center text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        فتح الدليل
      </Link>
    </WorkspaceWidget>
  )
}
