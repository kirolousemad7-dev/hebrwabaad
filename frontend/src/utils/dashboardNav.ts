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
  | 'seo'
  | 'work'
  | 'suppliers'
  | 'crm'
  | 'calendar'

/**
 * Only routes that currently exist. Later employee/owner features
 * should be added here when their pages are implemented.
 */
export const OWNER_DASHBOARD_NAV: DashboardNavItem[] = [
  { to: '/owner', label: 'لوحة التحكم', end: true, icon: 'home' },
  { to: '/owner/calendar', label: 'التقويم', icon: 'calendar' },
  { to: '/owner/work', label: 'العمل', icon: 'tasks' },
  { to: '/owner/printing-ops', label: 'تشغيل الطباعة', icon: 'printing' },
  { to: '/owner/printing-quotations', label: 'عروض الطباعة', icon: 'printing' },
  { to: '/owner/quote-requests', label: 'طلبات التسعير', icon: 'orders' },
  { to: '/owner/requirements', label: 'اكتشف احتياجك', icon: 'messages' },
  { to: '/owner/printing-catalog', label: 'كتالوج الطباعة', icon: 'printing' },
  { to: '/owner/departments', label: 'الأقسام', icon: 'employees' },
  { to: '/owner/projects', label: 'المشاريع', icon: 'projects' },
  { to: '/owner/automations', label: 'الأتمتة', icon: 'work' },
  { to: '/owner/approvals', label: 'الموافقات', icon: 'tasks' },
  { to: '/owner/operations-insights', label: 'تقارير التشغيل', icon: 'seo' },
  { to: '/owner/integrations/webhooks', label: 'التكاملات', icon: 'messages' },
  { to: '/owner/integrations/inbound-webhooks', label: 'Inbound Webhooks', icon: 'messages' },
  { to: '/owner/operations-settings', label: 'إعدادات التشغيل', icon: 'payments' },
  { to: '/owner/settings', label: 'إعدادات المنصة', icon: 'seo' },
  { to: '/owner/employees', label: 'الموظفون', icon: 'employees' },
  { to: '/owner/orders', label: 'الطلبات', icon: 'orders' },
  { to: '/owner/payments', label: 'المدفوعات', icon: 'payments' },
  { to: '/owner/payments/reconciliation', label: 'مطابقة المدفوعات', icon: 'payments' },
  { to: '/owner/support', label: 'الدعم', icon: 'messages' },
  { to: '/owner/files', label: 'الملفات', icon: 'files' },
  { to: '/owner/notifications', label: 'الإشعارات', icon: 'notifications' },
  { to: '/owner/services', label: 'الخدمات', icon: 'services' },
  { to: '/owner/packages', label: 'الباقات', icon: 'packages' },
  { to: '/owner/catalog-control', label: 'مركز الكتالوج', icon: 'packages' },
  { to: '/owner/seo', label: 'SEO', icon: 'seo' },
  { to: '/owner/marketing', label: 'المحتوى التسويقي', icon: 'files' },
  { to: '/owner/work-reviews', label: 'مراجعة الأعمال', icon: 'work' },
  { to: '/owner/suppliers', label: 'الموردين', icon: 'suppliers' },
  { to: '/owner/supplier-categories', label: 'تصنيفات الموردين', icon: 'packages' },
  { to: '/owner/tags', label: 'الوسوم', icon: 'seo' },
  { to: '/owner/supplier-reviews', label: 'مراجعة الموردين', icon: 'work' },
  { to: '/crm', label: 'المبيعات / CRM', icon: 'crm' },
  { to: '/printing-requests', label: 'طلبات الطباعة', icon: 'printing' },
]

/** Routes Admin Manager keeps even when other owner-only items are filtered out. */
const ADMIN_MANAGER_OPS_ROUTES = new Set([
  '/owner/work',
  '/owner/printing-ops',
  '/owner/printing-quotations',
  '/owner/quote-requests',
  '/owner/departments',
  '/owner/projects',
  '/owner/automations',
  '/owner/approvals',
  '/owner/operations-insights',
  '/owner/integrations/webhooks',
  '/owner/integrations/inbound-webhooks',
  '/owner/operations-settings',
  '/owner/settings',
  '/owner/printing-catalog',
])

export function ownerNavForRole(role: string | undefined): DashboardNavItem[] {
  if (role === 'OWNER') {
    return OWNER_DASHBOARD_NAV
  }

  return OWNER_DASHBOARD_NAV.filter(
    (item) =>
      ADMIN_MANAGER_OPS_ROUTES.has(item.to) ||
      (item.to !== '/owner' &&
        item.to !== '/owner/calendar' &&
        item.to !== '/owner/employees' &&
        item.to !== '/owner/orders' &&
        item.to !== '/owner/payments' &&
        item.to !== '/owner/support' &&
        item.to !== '/owner/requirements' &&
        item.to !== '/owner/files' &&
        item.to !== '/owner/notifications'),
  )
}

export const PRINTING_SPECIALIST_NAV: DashboardNavItem[] = [
  { to: '/workspace', label: 'لوحة التحكم', end: true, icon: 'home' },
  { to: '/printing-requests', label: 'طلبات الطباعة', icon: 'printing' },
  { to: '/owner/printing-quotations', label: 'عروض الطباعة', icon: 'printing' },
]

export const CUSTOMER_DASHBOARD_NAV: DashboardNavItem[] = [
  { to: '/dashboard', label: 'لوحة التحكم', match: ['/dashboard', '/customer'], icon: 'home' },
  { to: '/dashboard/projects', label: 'مشاريعي', icon: 'projects' },
  { to: '/dashboard/orders', label: 'طلباتي', icon: 'orders' },
  { to: '/dashboard/quote-requests', label: 'طلبات التسعير', icon: 'orders' },
  { to: '/dashboard/messages', label: 'الرسائل', icon: 'messages' },
  { to: '/dashboard/files', label: 'الملفات', icon: 'files' },
  { to: '/dashboard/notifications', label: 'الإشعارات', icon: 'notifications' },
  { to: '/customer/printing-requests', label: 'طلبات الطباعة', icon: 'printing' },
  { to: '/dashboard/profile', label: 'حسابي', icon: 'profile' },
  { to: '/consultant', label: 'المستشار الذكي', icon: 'consultant' },
  { to: '/services', label: 'الخدمات', icon: 'services' },
  { to: '/packages', label: 'الباقات', icon: 'packages' },
  { to: '/marketing-packages', label: 'الباقات التسويقية', icon: 'packages' },
  { to: '/event-packages', label: 'الباقات للفعاليات', icon: 'packages' },
  { to: '/build-package', label: 'صمّم باقتك', icon: 'packages' },
  { to: '/printing-packaging', label: 'الطباعة والتغليف', icon: 'printing' },
  { to: '/suppliers', label: 'الموردون', icon: 'suppliers' },
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
