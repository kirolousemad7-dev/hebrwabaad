import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { BrandWatermark } from '../components/brand/BrandWatermark'
import { DashboardContent } from '../components/dashboard/DashboardContent'
import { DashboardHeader } from '../components/dashboard/DashboardHeader'
import { DashboardSidebar } from '../components/dashboard/DashboardSidebar'
import { useAuth } from '../context/AuthContext'
import type { DashboardNavItem, DashboardNavSection } from '../utils/dashboardNav'

const SIDEBAR_COLLAPSED_KEY = 'hebr-dashboard-sidebar-collapsed'

function readCollapsedPreference(): boolean {
  try {
    return window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1'
  } catch {
    return false
  }
}

type DashboardLayoutProps = {
  title: string
  subtitle?: string | null
  items?: DashboardNavItem[]
  sections?: DashboardNavSection[]
  profileTo?: string
}

export function DashboardLayout({
  title,
  subtitle,
  items = [],
  sections,
  profileTo,
}: DashboardLayoutProps) {
  const { user } = useAuth()
  const location = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)
  const [sidebarCollapsed, setSidebarCollapsed] = useState(readCollapsedPreference)

  useEffect(() => {
    setMenuOpen(false)
  }, [location.pathname, location.search])

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setMenuOpen(false)
      }
    }

    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [])

  useEffect(() => {
    document.body.style.overflow = menuOpen ? 'hidden' : ''
    return () => {
      document.body.style.overflow = ''
    }
  }, [menuOpen])

  useEffect(() => {
    try {
      window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, sidebarCollapsed ? '1' : '0')
    } catch {
      // Ignore private-mode / blocked storage.
    }
  }, [sidebarCollapsed])

  return (
    <div className="relative isolate min-h-screen overflow-x-hidden bg-brand-paper text-brand-ink-900 lg:flex lg:items-stretch">
      <BrandWatermark />
      <div className="relative z-10 min-h-screen w-full min-w-0 lg:flex lg:items-stretch">
        <DashboardSidebar
          title={title}
          items={items}
          sections={sections}
          pathname={location.pathname}
          open={menuOpen}
          collapsed={sidebarCollapsed}
          onClose={() => setMenuOpen(false)}
          onToggleCollapsed={() => setSidebarCollapsed((current) => !current)}
        />
        <div className="flex min-w-0 flex-1 flex-col">
          <DashboardHeader
            title={title}
            subtitle={subtitle}
            userName={user?.name}
            menuOpen={menuOpen}
            onMenuToggle={() => setMenuOpen((open) => !open)}
            profileTo={profileTo}
          />
          <DashboardContent />
        </div>
      </div>
    </div>
  )
}
