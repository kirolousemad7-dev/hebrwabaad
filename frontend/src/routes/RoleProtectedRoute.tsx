import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { homePathForRole } from '../utils/roles'

type RoleProtectedRouteProps = {
  roles: string[]
}

export function RoleProtectedRoute({ roles }: RoleProtectedRouteProps) {
  const { isAuthenticated, isReady, user } = useAuth()
  const location = useLocation()

  if (!isReady) {
    return <p className="text-sm text-slate-500">جاري التحميل...</p>
  }

  if (!isAuthenticated) {
    return (
      <Navigate
        to="/login"
        replace
        state={{ from: `${location.pathname}${location.search}` }}
      />
    )
  }

  if (!user || !roles.includes(user.role)) {
    const fallback = homePathForRole(user?.role)

    return <Navigate to={fallback === location.pathname ? '/' : fallback} replace />
  }

  return <Outlet />
}
