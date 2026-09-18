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

export type DashboardNavItem = {
  to: string
  label: string
  end?: boolean
  match?: string[]
  /** Query string including `?`, e.g. `?tab=brand` (same path, different deep-link). */
  search?: string
  icon: DashboardIconName
  /** When set, item is visible only if the user role is in this list. */
  roles?: readonly string[]
}

export type DashboardNavSection = {
  id: string
  label: string
  items: DashboardNavItem[]
}

/**
 * Owner sidebar — grouped, scalable. Only links to routes that already exist.
 * Missing Phase-12 destinations (invoices, roles matrix, media library, etc.) are omitted.
 */
export const OWNER_NAV_SECTIONS: DashboardNavSection[] = [
  {
    id: 'dashboard',
    label: 'لوحة التحكم',
    items: [{ to: '/owner', label: 'نظرة عامة', end: true, icon: 'home', roles: ['OWNER'] }],
  },
  {
    id: 'crm',
    label: 'CRM',
    items: [
      { to: '/crm', label: 'لوحة المبيعات', end: true, icon: 'crm', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/crm/companies', label: 'الشركات', icon: 'suppliers', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/crm/contacts', label: 'جهات الاتصال', icon: 'employees', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/crm/leads', label: 'العملاء المحتملون', icon: 'crm', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/crm/quotations', label: 'عروض الأسعار', icon: 'orders', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/quote-requests', label: 'طلبات التسعير', icon: 'orders', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/requirements', label: 'اكتشف احتياجك', icon: 'messages', roles: ['OWNER'] },
    ],
  },
  {
    id: 'operations',
    label: 'التشغيل',
    items: [
      { to: '/owner/projects', label: 'المشاريع', icon: 'projects', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/work', label: 'المهام', icon: 'tasks', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/calendar', label: 'التقويم', icon: 'calendar', roles: ['OWNER'] },
      { to: '/owner/files', label: 'الملفات', icon: 'files', roles: ['OWNER'] },
      { to: '/owner/approvals', label: 'الموافقات', icon: 'tasks', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/automations', label: 'الأتمتة', icon: 'work', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/departments', label: 'الأقسام', icon: 'employees', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/printing-ops', label: 'تشغيل الطباعة', icon: 'printing', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/printing-quotations', label: 'عروض الطباعة', icon: 'printing', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/printing-requests', label: 'طلبات الطباعة', icon: 'printing', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/orders', label: 'الطلبات', icon: 'orders', roles: ['OWNER'] },
      { to: '/owner/support', label: 'الدعم', icon: 'messages', roles: ['OWNER'] },
    ],
  },
  {
    id: 'suppliers',
    label: 'الموردون',
    items: [
      { to: '/owner/suppliers', label: 'الموردون', icon: 'suppliers', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/supplier-categories', label: 'تصنيفات الموردين', icon: 'packages', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/supplier-reviews', label: 'مراجعة الموردين', icon: 'work', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/tags', label: 'الوسوم', icon: 'seo', roles: ['OWNER', 'ADMIN_MANAGER'] },
    ],
  },
  {
    id: 'catalog',
    label: 'الكتالوج',
    items: [
      { to: '/owner/services', label: 'الخدمات', icon: 'services', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/packages', label: 'الباقات', icon: 'packages', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/catalog-control', label: 'الإضافات / مركز الكتالوج', icon: 'packages', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/printing-catalog', label: 'الطباعة', icon: 'printing', roles: ['OWNER', 'ADMIN_MANAGER'] },
    ],
  },
  {
    id: 'content',
    label: 'المحتوى',
    items: [
      { to: '/owner/blog', label: 'المدونة', icon: 'files', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/seo', label: 'الصفحات / SEO', icon: 'seo', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/marketing', label: 'المعرض التسويقي', icon: 'files', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/work-reviews', label: 'مراجعة الأعمال', icon: 'work', roles: ['OWNER', 'ADMIN_MANAGER'] },
    ],
  },
  {
    id: 'reports',
    label: 'التقارير',
    items: [
      { to: '/owner/operations-insights', label: 'التشغيل', icon: 'seo', roles: ['OWNER', 'ADMIN_MANAGER'] },
      { to: '/owner/payments', label: 'المالي', icon: 'payments', roles: ['OWNER'] },
      { to: '/owner/payments/reconciliation', label: 'مطابقة المدفوعات', icon: 'payments', roles: ['OWNER'] },
      { to: '/crm/reports', label: 'الأداء / CRM', icon: 'crm', roles: ['OWNER', 'ADMIN_MANAGER'] },
    ],
  },
  {
    id: 'settings',
    label: 'الإعدادات',
    items: [
      { to: '/owner/employees', label: 'المستخدمون', icon: 'employees', roles: ['OWNER'] },
      {
        to: '/owner/integrations/webhooks',
        label: 'التكاملات',
        icon: 'messages',
        roles: ['OWNER', 'ADMIN_MANAGER'],
        match: ['/owner/integrations/webhooks', '/owner/integrations/inbound-webhooks'],
      },
      {
        to: '/owner/settings',
        label: 'الهوية البصرية',
        icon: 'seo',
        search: '?tab=brand',
        roles: ['OWNER', 'ADMIN_MANAGER'],
      },
      {
        to: '/owner/settings',
        label: 'الموقع',
        icon: 'seo',
        search: '?tab=website',
        roles: ['OWNER', 'ADMIN_MANAGER'],
      },
      { to: '/owner/notifications', label: 'الإشعارات', icon: 'notifications', roles: ['OWNER'] },
      {
        to: '/owner/notification-preferences',
        label: 'تفضيلات الإشعارات',
        icon: 'notifications',
        roles: ['OWNER', 'ADMIN_MANAGER'],
      },
      { to: '/owner/operations-settings', label: 'إعدادات التشغيل', icon: 'payments', roles: ['OWNER', 'ADMIN_MANAGER'] },
    ],
  },
]

/** @deprecated Prefer OWNER_NAV_SECTIONS; kept as flat list for tests and printing layout fallbacks. */
export const OWNER_DASHBOARD_NAV: DashboardNavItem[] = OWNER_NAV_SECTIONS.flatMap((section) => section.items)

function itemVisibleToRole(item: DashboardNavItem, role: string | undefined): boolean {
  if (!item.roles || item.roles.length === 0) {
    return true
  }
  return role !== undefined && item.roles.includes(role)
}

export function ownerNavSectionsForRole(role: string | undefined): DashboardNavSection[] {
  return OWNER_NAV_SECTIONS.map((section) => ({
    ...section,
    items: section.items.filter((item) => itemVisibleToRole(item, role)),
  })).filter((section) => section.items.length > 0)
}

/** Flat list derived from grouped nav (used by existing tests and printing specialist fallbacks). */
export function ownerNavForRole(role: string | undefined): DashboardNavItem[] {
  return ownerNavSectionsForRole(role).flatMap((section) => section.items)
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

export function navItemHref(item: DashboardNavItem): string {
  return `${item.to}${item.search ?? ''}`
}

export function isDashboardNavActive(item: DashboardNavItem, pathname: string, search = ''): boolean {
  if (item.match) {
    return item.match.includes(pathname)
  }

  const pathMatches = item.end ? pathname === item.to : pathname === item.to || pathname.startsWith(`${item.to}/`)

  if (!pathMatches) {
    return false
  }

  if (item.search) {
    const wanted = new URLSearchParams(item.search.startsWith('?') ? item.search.slice(1) : item.search)
    const current = new URLSearchParams(search.startsWith('?') ? search.slice(1) : search)
    for (const [key, value] of wanted.entries()) {
      if (current.get(key) !== value) {
        return false
      }
    }
    return true
  }

  // Generic /owner/settings without a tab search should not steal active from tabbed siblings.
  if (item.to === '/owner/settings' && !item.search) {
    return pathname === item.to && !search.includes('tab=')
  }

  return true
}

export function sectionHasActiveItem(section: DashboardNavSection, pathname: string, search = ''): boolean {
  return section.items.some((item) => isDashboardNavActive(item, pathname, search))
}
