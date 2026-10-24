import { Outlet } from 'react-router-dom'

export function DashboardContent() {
  return (
    <main className="min-w-0 px-4 py-5 sm:px-5 lg:px-8 lg:py-6">
      <Outlet />
    </main>
  )
}
