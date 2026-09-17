import { DashboardLayout } from './DashboardLayout'
import { useAuth } from '../context/AuthContext'

export function SupplierLayout() {
  const { user } = useAuth()

  return (
    <DashboardLayout
      title="لوحة المورد"
      subtitle={user?.name}
      items={[
        { to: '/supplier', label: 'الرئيسية', end: true, icon: 'home' },
        { to: '/supplier/profile', label: 'الملف', icon: 'profile' },
        { to: '/supplier/services', label: 'الخدمات', icon: 'services' },
        { to: '/supplier/products', label: 'المنتجات', icon: 'packages' },
        { to: '/supplier/portfolio', label: 'المعرض', icon: 'work' },
        { to: '/supplier/documents', label: 'المستندات', icon: 'files' },
        { to: '/supplier/quotations', label: 'عروض الأسعار', icon: 'orders' },
        { to: '/supplier/projects', label: 'المشاريع', icon: 'projects' },
        { to: '/supplier/tasks', label: 'المهام', icon: 'tasks' },
        { to: '/supplier/calendar', label: 'التقويم', icon: 'calendar' },
        { to: '/supplier/notifications', label: 'الإشعارات', icon: 'notifications' },
        { to: '/supplier/messages', label: 'الرسائل', icon: 'messages' },
        { to: '/supplier/settings', label: 'الإعدادات', icon: 'seo' },
      ]}
    />
  )
}
