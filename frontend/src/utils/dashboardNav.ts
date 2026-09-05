export type DashboardNavItem = {
  to: string
  label: string
  end?: boolean
  match?: string[]
  icon: DashboardIconName
}

export type DashboardIconName =
  | 'home'
  | 'services'
  | 'packages'
  | 'printing'
  | 'employees'
  | 'tasks'
  | 'projects'
  | 'consultant'
  | 'profile'
  | 'orders'
  | 'messages'
  | 'files'
  | 'notifications'
  | 'payments'

/**
 * Only routes that currently exist. Later employee/owner features
 * should be added here when their pages are implemented.
 */
export const OWNER_DASHBOARD_NAV: DashboardNavItem[] = [
  { to: '/owner', label: 'لوحة التحكم', end: true, icon: 'home' },
  { to: '/owner/employees', label: 'الموظفون', icon: 'employees' },
  { to: '/owner/orders', label: 'الطلبات', icon: 'orders' },
  { to: '/owner/payments', label: 'المدفوعات', icon: 'payments' },
  { to: '/owner/support', label: 'الدعم', icon: 'messages' },
  { to: '/owner/files', label: 'الملفات', icon: 'files' },
  { to: '/owner/notifications', label: 'الإشعارات', icon: 'notifications' },
  { to: '/owner/services', label: 'الخدمات', icon: 'services' },
  { to: '/owner/packages', label: 'الباقات', icon: 'packages' },
  { to: '/printing-requests', label: 'طلبات الطباعة', icon: 'printing' },
]

export function ownerNavForRole(role: string | undefined): DashboardNavItem[] {
  if (role === 'OWNER') {
    return OWNER_DASHBOARD_NAV
  }

  return OWNER_DASHBOARD_NAV.filter(
    (item) =>
      item.to !== '/owner' &&
      item.to !== '/owner/employees' &&
      item.to !== '/owner/orders' &&
      item.to !== '/owner/payments' &&
      item.to !== '/owner/support' &&
      item.to !== '/owner/files' &&
      item.to !== '/owner/notifications',
  )
}

export const PRINTING_SPECIALIST_NAV: DashboardNavItem[] = [
  { to: '/workspace', label: 'لوحة التحكم', end: true, icon: 'home' },
  { to: '/printing-requests', label: 'طلبات الطباعة', icon: 'printing' },
]

export const CUSTOMER_DASHBOARD_NAV: DashboardNavItem[] = [
  { to: '/dashboard', label: 'لوحة التحكم', match: ['/dashboard', '/customer'], icon: 'home' },
  { to: '/dashboard/projects', label: 'مشاريعي', icon: 'projects' },
  { to: '/dashboard/orders', label: 'طلباتي', icon: 'orders' },
  { to: '/dashboard/messages', label: 'الرسائل', icon: 'messages' },
  { to: '/dashboard/files', label: 'الملفات', icon: 'files' },
  { to: '/dashboard/notifications', label: 'الإشعارات', icon: 'notifications' },
  { to: '/customer/printing-requests', label: 'طلبات الطباعة', icon: 'printing' },
  { to: '/dashboard/profile', label: 'حسابي', icon: 'profile' },
  { to: '/consultant', label: 'المستشار الذكي', icon: 'consultant' },
]

export function isDashboardNavActive(item: DashboardNavItem, pathname: string): boolean {
  if (item.match) {
    return item.match.includes(pathname)
  }

  if (item.end) {
    return pathname === item.to
  }

  return pathname === item.to || pathname.startsWith(`${item.to}/`)
}
