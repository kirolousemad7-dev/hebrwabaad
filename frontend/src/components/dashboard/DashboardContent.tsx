import { Outlet } from 'react-router-dom'

export function DashboardContent() {
  return (
    <main className="min-w-0 flex-1 px-4 py-6 sm:py-8 lg:px-8">
      <Outlet />
    </main>
  )
}
