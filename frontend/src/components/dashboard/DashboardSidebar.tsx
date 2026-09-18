import { useEffect, useMemo, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { useAuth } from '../../context/AuthContext'
import {
  isDashboardNavActive,
  navItemHref,
  sectionHasActiveItem,
  type DashboardNavItem,
  type DashboardNavSection,
} from '../../utils/dashboardNav'
import { DashboardIcon } from './DashboardIcon'

type DashboardSidebarProps = {
  title: string
  items?: DashboardNavItem[]
  sections?: DashboardNavSection[]
  pathname: string
  open: boolean
  collapsed: boolean
  onClose: () => void
  onToggleCollapsed: () => void
}

function SidebarCollapseIcon({ collapsed }: { collapsed: boolean }) {
  return (
    <svg
      viewBox="0 0 24 24"
      className={`h-4 w-4 transition-transform duration-200 ${collapsed ? '' : 'scale-x-[-1]'}`}
      aria-hidden="true"
    >
      <path
        d="M14.5 6.5 9 12l5.5 5.5"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

function ChevronIcon({ open }: { open: boolean }) {
  return (
    <svg
      viewBox="0 0 24 24"
      className={`h-3.5 w-3.5 shrink-0 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
      aria-hidden="true"
    >
      <path
        d="M6 9l6 6 6-6"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

export function DashboardSidebar({
  title,
  items = [],
  sections,
  pathname,
  open,
  collapsed,
  onClose,
  onToggleCollapsed,
}: DashboardSidebarProps) {
  const { logout } = useAuth()
  const location = useLocation()
  const search = location.search

  const resolvedSections = useMemo<DashboardNavSection[]>(() => {
    if (sections && sections.length > 0) {
      return sections
    }
    if (items.length === 0) {
      return []
    }
    return [{ id: 'main', label: '', items }]
  }, [items, sections])

  const [expandedIds, setExpandedIds] = useState<string[]>(() =>
    resolvedSections.filter((section) => sectionHasActiveItem(section, pathname, search)).map((section) => section.id),
  )

  useEffect(() => {
    setExpandedIds((current) => {
      const next = new Set(current)
      resolvedSections.forEach((section) => {
        if (sectionHasActiveItem(section, pathname, search)) {
          next.add(section.id)
        }
      })
      return Array.from(next)
    })
  }, [pathname, search, resolvedSections])

  function toggleSection(id: string) {
    setExpandedIds((current) => (current.includes(id) ? current.filter((value) => value !== id) : [...current, id]))
  }

  function renderItem(item: DashboardNavItem, compact: boolean, closeOnNavigate: boolean) {
    const active = isDashboardNavActive(item, pathname, search)
    const href = navItemHref(item)

    return (
      <NavLink
        key={`${item.to}${item.search ?? ''}${item.label}`}
        to={href}
        end={item.end}
        title={compact ? item.label : undefined}
        aria-label={compact ? item.label : undefined}
        aria-current={active ? 'page' : undefined}
        onClick={closeOnNavigate ? onClose : undefined}
        className={[
          'flex items-center rounded-lg text-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary',
          compact ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2',
          active ? 'bg-brand-primary font-medium text-white' : 'text-white/85 hover:bg-white/10',
        ].join(' ')}
      >
        <DashboardIcon name={item.icon} />
        {!compact ? <span className="truncate">{item.label}</span> : null}
      </NavLink>
    )
  }

  function NavBody({ compact, closeOnNavigate }: { compact: boolean; closeOnNavigate: boolean }) {
    return (
      <>
        <div className={`space-y-3 py-4 ${compact ? 'px-2' : 'px-4'}`}>
          <div className={`rounded-xl bg-white ${compact ? 'p-1.5' : 'p-2'}`}>
            <BrandLogo
              size={compact ? 'symbol' : 'sidebar'}
              markOnly={compact}
              to="/"
              className={compact ? 'mx-auto' : undefined}
            />
          </div>
          {!compact ? <p className="px-1 text-sm font-medium text-white/80">{title}</p> : null}
          <button
            type="button"
            onClick={onToggleCollapsed}
            aria-expanded={!collapsed}
            aria-controls="dashboard-sidebar-desktop"
            aria-label={collapsed ? 'توسيع القائمة الجانبية' : 'طي القائمة الجانبية'}
            title={collapsed ? 'توسيع القائمة' : 'طي القائمة'}
            className={[
              'hidden min-h-10 items-center rounded-lg border border-white/15 text-sm text-white/90 transition hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500 lg:flex',
              compact ? 'w-full justify-center px-2' : 'w-full justify-between gap-2 px-3',
            ].join(' ')}
          >
            {!compact ? <span>طي القائمة</span> : null}
            <SidebarCollapseIcon collapsed={collapsed} />
          </button>
        </div>

        <nav
          aria-label="تنقل لوحة التحكم"
          className={`flex flex-1 flex-col gap-1 overflow-y-auto pb-2 ${compact ? 'px-2' : 'px-3'}`}
        >
          {resolvedSections.map((section) => {
            const expanded = compact || !section.label || expandedIds.includes(section.id)
            const sectionActive = sectionHasActiveItem(section, pathname, search)

            return (
              <div key={section.id} className={compact ? 'space-y-1' : 'mb-1 space-y-1'}>
                {!compact && section.label ? (
                  <button
                    type="button"
                    onClick={() => toggleSection(section.id)}
                    aria-expanded={expanded}
                    className={[
                      'flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-[11px] font-semibold tracking-wide uppercase transition',
                      sectionActive ? 'text-white' : 'text-white/55 hover:bg-white/5 hover:text-white/80',
                    ].join(' ')}
                  >
                    <span>{section.label}</span>
                    <ChevronIcon open={expanded} />
                  </button>
                ) : null}

                {expanded
                  ? section.items.map((item) => renderItem(item, compact, closeOnNavigate))
                  : null}
              </div>
            )
          })}
        </nav>

        <div className={`mt-auto space-y-1 border-t border-white/15 py-4 ${compact ? 'px-2' : 'px-3'}`}>
          <Link
            to="/"
            title={compact ? 'الموقع' : undefined}
            aria-label={compact ? 'الموقع' : undefined}
            onClick={closeOnNavigate ? onClose : undefined}
            className={[
              'flex items-center rounded-lg text-sm text-white/85 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary',
              compact ? 'justify-center px-2 py-2.5' : 'px-3 py-2.5',
            ].join(' ')}
          >
            {compact ? (
              <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0" aria-hidden="true">
                <path
                  d="M4 11.5 12 5l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  strokeLinejoin="round"
                />
              </svg>
            ) : (
              'الموقع'
            )}
          </Link>
          <button
            type="button"
            title={compact ? 'تسجيل الخروج' : undefined}
            aria-label={compact ? 'تسجيل الخروج' : undefined}
            onClick={() => void logout()}
            className={[
              'flex w-full items-center rounded-lg text-sm text-white/85 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
              compact ? 'justify-center px-2 py-2.5' : 'px-3 py-2.5',
            ].join(' ')}
          >
            {compact ? (
              <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0" aria-hidden="true">
                <path
                  d="M10 7V5a1 1 0 0 1 1-1h8v16h-8a1 1 0 0 1-1-1v-2M4 12h10M7 9l-3 3 3 3"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            ) : (
              'تسجيل الخروج'
            )}
          </button>
        </div>
      </>
    )
  }

  return (
    <>
      <aside
        id="dashboard-sidebar-desktop"
        className={[
          'hidden min-h-screen shrink-0 flex-col self-stretch border-e border-brand-ink-700 bg-brand-ink-900 transition-[width] duration-300 ease-out lg:flex',
          collapsed ? 'w-[4.5rem] min-w-[4.5rem]' : 'w-64 min-w-64',
        ].join(' ')}
      >
        <NavBody compact={collapsed} closeOnNavigate={false} />
      </aside>

      {open ? (
        <div className="lg:hidden">
          <button
            type="button"
            aria-label="إغلاق القائمة"
            className="fixed inset-0 z-40 bg-brand-ink-900/40"
            onClick={onClose}
          />
          <aside
            id="dashboard-sidebar-menu"
            role="dialog"
            aria-modal="true"
            aria-label={title}
            className="fixed inset-y-0 start-0 z-50 flex w-[min(18rem,88vw)] flex-col overflow-y-auto bg-brand-ink-900 text-white shadow-xl [scrollbar-width:none] [&::-webkit-scrollbar]:h-0 [&::-webkit-scrollbar]:w-0"
          >
            <NavBody compact={false} closeOnNavigate />
          </aside>
        </div>
      ) : null}
    </>
  )
}
