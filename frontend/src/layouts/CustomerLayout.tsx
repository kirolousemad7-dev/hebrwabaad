import { DashboardLayout } from './DashboardLayout'
import { CUSTOMER_DASHBOARD_NAV } from '../utils/dashboardNav'

export function CustomerLayout() {
  return (
    <DashboardLayout
      title="مساحة العمل"
      subtitle="تابع مشاريعك وطلباتك وتواصل مع فريق HEBR من مكان واحد."
      items={CUSTOMER_DASHBOARD_NAV}
      profileTo="/dashboard/profile"
    />
  )
}
