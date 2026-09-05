import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { BrandWatermark } from '../components/brand/BrandWatermark'
import { DashboardContent } from '../components/dashboard/DashboardContent'
import { DashboardHeader } from '../components/dashboard/DashboardHeader'
import { DashboardSidebar } from '../components/dashboard/DashboardSidebar'
import { useAuth } from '../context/AuthContext'
import type { DashboardNavItem } from '../utils/dashboardNav'

type DashboardLayoutProps = {
  title: string
  subtitle?: string | null
  items: DashboardNavItem[]
  profileTo?: string
}

export function DashboardLayout({
  title,
  subtitle,
  items,
  profileTo,
}: DashboardLayoutProps) {
  const { user } = useAuth()
  const location = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)

  useEffect(() => {
    setMenuOpen(false)
  }, [location.pathname])

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

  return (
    <div className="relative isolate min-h-screen bg-slate-50 text-slate-900 lg:flex">
      <BrandWatermark />
      <div className="relative z-10 min-h-screen w-full lg:flex">
        <DashboardSidebar
          title={title}
          items={items}
          pathname={location.pathname}
          open={menuOpen}
          onClose={() => setMenuOpen(false)}
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
