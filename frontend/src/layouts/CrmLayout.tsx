import { DashboardLayout } from './DashboardLayout'
import { useAuth } from '../context/AuthContext'
import { filterNavItemsByDashboardAccess } from '../utils/dashboardAccess'
import { crmNavForRole } from '../utils/crmNav'
import { ROLE_WORKSPACE } from '../utils/staff'

export function CrmLayout() {
  const { user } = useAuth()
  const workspace = user?.role ? ROLE_WORKSPACE[user.role] : undefined
  const title = workspace?.label ?? 'المبيعات / CRM'
  const items = filterNavItemsByDashboardAccess(
    crmNavForRole(user?.role),
    user?.role,
    user?.dashboard_access,
  )

  return <DashboardLayout title={title} subtitle={user?.name} items={items} />
}
