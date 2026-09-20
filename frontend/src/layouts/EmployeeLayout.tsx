import { DashboardLayout } from './DashboardLayout'
import { useAuth } from '../context/AuthContext'
import { filterNavItemsByDashboardAccess } from '../utils/dashboardAccess'
import { getWorkspaceForRole, roleLabelFor } from '../utils/employeeWorkspace'
import type { DashboardNavItem } from '../utils/dashboardNav'

const FALLBACK_NAV: DashboardNavItem[] = [
  { to: '/workspace', label: 'لوحة التحكم', end: true, icon: 'home' },
]

export function EmployeeLayout() {
  const { user } = useAuth()
  const workspace = getWorkspaceForRole(user?.role)
  const navigation = filterNavItemsByDashboardAccess(
    workspace?.navigation ?? FALLBACK_NAV,
    user?.role,
    user?.dashboard_access,
  )

  return (
    <DashboardLayout
      title={workspace?.label ?? 'مساحة العمل'}
      subtitle={roleLabelFor(user?.role)}
      items={navigation}
    />
  )
}
