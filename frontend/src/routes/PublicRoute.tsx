import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { isSafeInternalPath } from '../utils/orderIntent'
import { homePathForRole, isCatalogManager } from '../utils/roles'

export function PublicRoute() {
  const { isAuthenticated, isReady, user } = useAuth()
  const location = useLocation()

  if (!isReady) {
    return <p className="text-sm text-slate-500">جاري التحميل...</p>
  }

  if (isAuthenticated) {
    const allowWhileAuthenticated =
      location.pathname === '/forgot-password' || location.pathname === '/reset-password'

    if (!allowWhileAuthenticated) {
      const from = (location.state as { from?: string } | null)?.from
      const destination =
        from && !isCatalogManager(user?.role) && isSafeInternalPath(from)
          ? from
          : homePathForRole(user?.role)

      return <Navigate to={destination} replace />
    }
  }

  return <Outlet />
}
