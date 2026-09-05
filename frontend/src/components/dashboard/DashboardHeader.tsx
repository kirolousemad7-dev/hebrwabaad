import { Link } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { NotificationBell } from '../notifications/NotificationBell'
import { customerInitials } from '../../utils/customerDashboard'

type DashboardHeaderProps = {
  title: string
  subtitle?: string | null
  userName?: string
  menuOpen: boolean
  onMenuToggle: () => void
  profileTo?: string
}

export function DashboardHeader({
  title,
  subtitle,
  userName,
  menuOpen,
  onMenuToggle,
  profileTo,
}: DashboardHeaderProps) {
  return (
    <header className="sticky top-0 z-30 border-b border-slate-200 bg-slate-50/95 backdrop-blur">
      <div className="flex items-center justify-between gap-3 px-4 py-3 lg:px-8">
        <div className="flex min-w-0 items-center gap-3">
          <button
            type="button"
            aria-expanded={menuOpen}
            aria-controls="dashboard-sidebar-menu"
            onClick={onMenuToggle}
            className="min-h-11 rounded-lg border border-slate-300 px-3 text-sm lg:hidden focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            {menuOpen ? 'إغلاق' : 'القائمة'}
          </button>
          <span className="lg:hidden">
            <BrandLogo size="nav" to="/" />
          </span>
          <div className="hidden min-w-0 lg:block">
            <p className="truncate font-medium">{title}</p>
            {subtitle ? <p className="truncate text-xs text-slate-500">{subtitle}</p> : null}
          </div>
        </div>

        <div className="flex shrink-0 items-center gap-2">
          <NotificationBell />

          {profileTo && userName ? (
            <Link
              to={profileTo}
              className="inline-flex items-center gap-2 rounded-lg px-1 py-1 text-sm text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            >
              <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-medium text-white">
                {customerInitials(userName)}
              </span>
              <span className="hidden max-w-[10rem] truncate sm:inline">{userName}</span>
            </Link>
          ) : userName ? (
            <p className="shrink-0 text-sm text-slate-600">{userName}</p>
          ) : null}
        </div>
      </div>
    </header>
  )
}
