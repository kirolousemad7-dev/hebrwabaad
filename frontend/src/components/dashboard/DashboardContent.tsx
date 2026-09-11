import { Outlet } from 'react-router-dom'

export function DashboardContent() {
  return (
    <main className="min-w-0 flex-1 overflow-x-hidden px-4 py-6 sm:py-8 lg:px-6 xl:px-8">
      <Outlet />
    </main>
  )
}
