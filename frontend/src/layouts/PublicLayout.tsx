import { Outlet } from 'react-router-dom'
import { BrandWatermark } from '../components/brand/BrandWatermark'
import { FloatingWhatsAppButton } from '../components/public/FloatingWhatsAppButton'
import { NeedsDiscoveryWidget } from '../components/needs-discovery/NeedsDiscoveryWidget'
import { PublicFooter } from '../components/public/PublicFooter'
import { PublicNav } from '../components/public/PublicNav'

export function PublicLayout() {
  return (
    <div className="relative isolate min-h-screen bg-brand-paper text-brand-ink-900">
      <BrandWatermark />
      <div className="relative z-10 flex min-h-screen flex-col">
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:absolute focus:right-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:shadow"
        >
          تخطي إلى المحتوى
        </a>
        <PublicNav />
        <main id="main-content" className="marketing-container w-full flex-1 py-10 sm:py-12">
          <Outlet />
        </main>
        <PublicFooter />
      </div>
      <NeedsDiscoveryWidget />
      <FloatingWhatsAppButton />
    </div>
  )
}
