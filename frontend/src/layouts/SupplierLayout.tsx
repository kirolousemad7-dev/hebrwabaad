import { DashboardLayout } from './DashboardLayout'
import { useAuth } from '../context/AuthContext'

export function SupplierLayout() {
  const { user } = useAuth()

  return (
    <DashboardLayout
      title="لوحة المورد"
      subtitle={user?.name}
      items={[
        { to: '/supplier', label: 'محتواي', end: true, icon: 'home' },
        { to: '/supplier/profile', label: 'الملف', icon: 'profile' },
        { to: '/supplier/notifications', label: 'الإشعارات', icon: 'notifications' },
      ]}
    />
  )
}
