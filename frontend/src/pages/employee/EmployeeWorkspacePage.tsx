import { EmployeeDashboardShell } from '../../components/workspace/EmployeeDashboardShell'
import { WorkspaceEmptyState, WorkspaceErrorState, WorkspaceSkeleton } from '../../components/workspace/WorkspaceStatus'
import { useAuth } from '../../context/AuthContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getEmployeeWorkspace } from '../../services/workspace'
import { authorizedWidgets, getWorkspaceForRole } from '../../utils/employeeWorkspace'

export function EmployeeWorkspacePage() {
  const { refreshUser } = useAuth()
  const { state, reload } = useAsyncData(async () => {
    await refreshUser()
    return getEmployeeWorkspace()
  })

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل مساحة العمل..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
  }

  const config = getWorkspaceForRole(state.data.role)

  if (!config) {
    return (
      <WorkspaceEmptyState
        title="مساحة العمل غير مُعدّة."
        description="هذا الدور لا يملك لوحة موظفين مهيأة بعد، ولم يُمنح وصولاً إلى لوحة المالك."
      />
    )
  }

  const widgets = authorizedWidgets(config, state.data.widgets, state.data.capabilities)

  return <EmployeeDashboardShell config={config} widgets={widgets} domains={state.data.domains} />
}
