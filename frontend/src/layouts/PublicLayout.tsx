import { Outlet } from 'react-router-dom'
import { BrandWatermark } from '../components/brand/BrandWatermark'
import { PublicFooter } from '../components/public/PublicFooter'
import { PublicNav } from '../components/public/PublicNav'

export function PublicLayout() {
  return (
    <div className="relative isolate min-h-screen bg-slate-50 text-slate-900">
      <BrandWatermark />
      <div className="relative z-10 flex min-h-screen flex-col">
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:absolute focus:right-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:shadow"
        >
          تخطي إلى المحتوى
        </a>
        <PublicNav />
        <main id="main-content" className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-10">
          <Outlet />
        </main>
        <PublicFooter />
      </div>
    </div>
  )
}
