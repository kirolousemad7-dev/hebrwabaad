import { Link, NavLink } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { useAuth } from '../../context/AuthContext'
import { isDashboardNavActive, type DashboardNavItem } from '../../utils/dashboardNav'
import { DashboardIcon } from './DashboardIcon'

type DashboardSidebarProps = {
  title: string
  items: DashboardNavItem[]
  pathname: string
  open: boolean
  onClose: () => void
}

export function DashboardSidebar({ title, items, pathname, open, onClose }: DashboardSidebarProps) {
  const { logout } = useAuth()

  function NavBody() {
    return (
      <>
        <div className="space-y-3 px-4 py-5">
          <div className="rounded-xl bg-white p-2">
            <BrandLogo size="sidebar" to="/" />
          </div>
          <p className="px-1 text-sm font-medium text-white/80">{title}</p>
        </div>
        <nav aria-label="تنقل لوحة التحكم" className="flex flex-1 flex-col gap-1 px-3">
          {items.map((item) => {
            const active = isDashboardNavActive(item, pathname)

            return (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                aria-current={active ? 'page' : undefined}
                className={[
                  'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400',
                  active
                    ? 'bg-amber-400 font-medium text-slate-900'
                    : 'text-white/85 hover:bg-white/10',
                ].join(' ')}
              >
                <DashboardIcon name={item.icon} />
                {item.label}
              </NavLink>
            )
          })}
        </nav>
        <div className="mt-auto space-y-1 border-t border-white/15 px-3 py-4">
          <Link
            to="/"
            className="flex items-center rounded-lg px-3 py-2.5 text-sm text-white/85 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
          >
            الموقع
          </Link>
          <button
            type="button"
            onClick={() => void logout()}
            className="flex w-full items-center rounded-lg px-3 py-2.5 text-sm text-white/85 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
          >
            تسجيل الخروج
          </button>
        </div>
      </>
    )
  }

  return (
    <>
      <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-e border-slate-800 bg-slate-900 lg:flex">
        <NavBody />
      </aside>

      {open ? (
        <div className="lg:hidden">
          <button
            type="button"
            aria-label="إغلاق القائمة"
            className="fixed inset-0 z-40 bg-slate-900/40"
            onClick={onClose}
          />
          <aside
            id="dashboard-sidebar-menu"
            role="dialog"
            aria-modal="true"
            aria-label={title}
            className="fixed inset-y-0 start-0 z-50 flex w-[min(18rem,88vw)] flex-col overflow-y-auto bg-slate-900 text-white shadow-xl"
          >
            <NavBody />
          </aside>
        </div>
      ) : null}
    </>
  )
}
