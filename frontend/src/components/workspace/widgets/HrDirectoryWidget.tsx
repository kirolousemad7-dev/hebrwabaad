import { Link } from 'react-router-dom'
import { useAsyncData } from '../../../hooks/useAsyncData'
import { getHrEmployees } from '../../../services/workspaceTasks'
import { roleLabelFor } from '../../../utils/employeeWorkspace'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

export function HrDirectoryWidget() {
  const { state, reload } = useAsyncData(() => getHrEmployees('?per_page=5'))

  return (
    <WorkspaceWidget title="دليل الموظفين">
      {state.status === 'loading' ? <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-busy="true" /> : null}
      {state.status === 'error' ? <WorkspaceErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && state.data.summary ? (
        <p className="text-sm text-slate-600">
          الإجمالي {state.data.summary.total.toLocaleString('ar-SA')} · نشط {state.data.summary.active.toLocaleString('ar-SA')} · غير نشط{' '}
          {state.data.summary.inactive.toLocaleString('ar-SA')}
        </p>
      ) : null}
      {state.status === 'ready' && state.data.items.length === 0 ? (
        <WorkspaceEmptyState title="لا يوجد موظفون." description="سيظهر الدليل هنا عند إضافة الموظفين بواسطة المالك." />
      ) : null}
      {state.status === 'ready' && state.data.items.length > 0 ? (
        <ul className="space-y-2 text-sm">
          {state.data.items.map((employee) => (
            <li key={employee.id} className="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 py-2">
              <span className="min-w-0 truncate font-medium">{employee.name}</span>
              <span className="shrink-0 text-slate-600">
                {roleLabelFor(employee.role) ?? employee.role}
                <span className="mx-1 text-slate-400" aria-hidden="true">
                  ·
                </span>
                {employee.is_active ? 'نشط' : 'غير نشط'}
              </span>
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
