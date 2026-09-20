import { DashboardLayout } from './DashboardLayout'
import { useAuth } from '../context/AuthContext'
import { ownerNavSectionsForRole } from '../utils/dashboardNav'
import { ROLE_WORKSPACE } from '../utils/staff'

export function OwnerLayout() {
  const { user } = useAuth()
  const title = user?.role === 'ADMIN_MANAGER' ? ROLE_WORKSPACE.ADMIN_MANAGER.label : ROLE_WORKSPACE.OWNER.label

  return (
    <DashboardLayout
      title={title}
      sections={ownerNavSectionsForRole(user?.role, user?.dashboard_access)}
    />
  )
}
