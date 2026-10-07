import { Outlet, useLocation } from 'react-router-dom'

export function DashboardContent() {
  const location = useLocation()
  const isOwnerHome = location.pathname === '/owner'

  return (
    <main
      className={
        isOwnerHome
          ? 'w-full min-w-0 max-w-[1280px] px-4 py-4 sm:px-5 sm:py-5 lg:px-8 lg:py-6'
          : 'min-w-0 px-4 py-6 sm:py-8 lg:px-6 xl:px-8'
      }
    >
      <Outlet />
    </main>
  )
}
