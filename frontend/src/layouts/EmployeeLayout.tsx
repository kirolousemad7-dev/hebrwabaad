import { DashboardLayout } from './DashboardLayout'
import { useAuth } from '../context/AuthContext'
import { getWorkspaceForRole, roleLabelFor } from '../utils/employeeWorkspace'

export function EmployeeLayout() {
  const { user } = useAuth()
  const workspace = getWorkspaceForRole(user?.role)

  return (
    <DashboardLayout
      title={workspace?.label ?? 'مساحة العمل'}
      subtitle={roleLabelFor(user?.role)}
      items={workspace?.navigation ?? [{ to: '/workspace', label: 'لوحة التحكم', end: true, icon: 'home' }]}
    />
  )
}
