import { useAuth } from '../context/AuthContext'
import { ownerNavSectionsForRole, PRINTING_SPECIALIST_NAV } from '../utils/dashboardNav'
import { isCatalogManager } from '../utils/roles'
import { DashboardLayout } from './DashboardLayout'

export function PrintingOperationsLayout() {
  const { user } = useAuth()
  const catalogManager = isCatalogManager(user?.role)

  return (
    <DashboardLayout
      title={catalogManager ? 'لوحة المالك' : 'طلبات الطباعة'}
      sections={catalogManager ? ownerNavSectionsForRole(user?.role) : undefined}
      items={catalogManager ? undefined : PRINTING_SPECIALIST_NAV}
    />
  )
}
