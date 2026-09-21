/**
 * Mirrors backend App\Support\DashboardModules path → module map.
 * Longest prefix wins.
 */
const PATH_MODULE_MAP: Array<[string, string]> = [
  ['/owner/role-dashboard-access', 'role_access'],
  ['/owner/payments/reconciliation', 'finance'],
  ['/owner/payments/settings', 'finance'],
  ['/owner/payments', 'finance'],
  ['/owner/invoices', 'invoices'],
  ['/owner/operations-insights', 'reports'],
  ['/crm/reports', 'reports'],
  ['/owner/employees', 'employees'],
  ['/owner/account', 'settings'],
  ['/owner/integrations', 'integrations'],
  ['/owner/operations-settings', 'settings'],
  ['/owner/notification-preferences', 'notifications'],
  ['/owner/notifications', 'notifications'],
  ['/owner/settings', 'settings'],
  ['/owner/services', 'catalog'],
  ['/owner/packages', 'catalog'],
  ['/owner/catalog-control', 'catalog'],
  ['/owner/printing-catalog', 'catalog'],
  ['/owner/blog', 'content'],
  ['/owner/seo', 'content'],
  ['/owner/marketing', 'content'],
  ['/owner/work-reviews', 'content'],
  ['/owner/suppliers', 'suppliers'],
  ['/owner/supplier-categories', 'suppliers'],
  ['/owner/supplier-reviews', 'suppliers'],
  ['/owner/tags', 'suppliers'],
  ['/owner/projects', 'projects'],
  ['/owner/work', 'work'],
  ['/owner/calendar', 'calendar'],
  ['/owner/files', 'files'],
  ['/owner/approvals', 'approvals'],
  ['/owner/automations', 'automations'],
  ['/owner/departments', 'departments'],
  ['/owner/printing-ops', 'printing'],
  ['/owner/printing-quotations', 'printing'],
  ['/printing-requests', 'printing'],
  ['/owner/orders', 'orders'],
  ['/owner/support', 'support'],
  ['/owner/quote-requests', 'crm'],
  ['/owner/requirements', 'crm'],
  ['/owner/commercial-quotations', 'crm'],
  ['/crm', 'crm'],
  ['/owner', 'dashboard'],
  ['/workspace/calendar', 'workspace.calendar'],
  ['/workspace/work', 'workspace.work'],
  ['/workspace/tasks', 'workspace.tasks'],
  ['/workspace/projects', 'workspace.projects'],
  ['/workspace/files', 'workspace.files'],
  ['/workspace/orders', 'workspace.orders'],
  ['/workspace/support', 'workspace.support'],
  ['/workspace/directory', 'workspace.directory'],
  ['/workspace/team', 'workspace.team'],
  ['/workspace/portfolio', 'workspace.portfolio'],
  ['/workspace/notifications', 'workspace.notifications'],
  ['/workspace', 'workspace'],
]

export type DashboardAccessMap = Record<string, boolean>

export function moduleForPath(path: string): string | null {
  const normalized = `/${path.replace(/^\/+/, '').split('?')[0]}`
  const sorted = [...PATH_MODULE_MAP].sort((a, b) => b[0].length - a[0].length)

  for (const [prefix, module] of sorted) {
    if (normalized === prefix || normalized.startsWith(`${prefix}/`)) {
      return module
    }
  }

  return null
}

export function canAccessDashboardModule(
  role: string | undefined,
  access: DashboardAccessMap | null | undefined,
  moduleKey: string | null,
): boolean {
  if (!moduleKey) {
    return true
  }

  if (role === 'OWNER') {
    return true
  }

  if (!access) {
    return true
  }

  return access[moduleKey] === true
}

export function canAccessDashboardPath(
  role: string | undefined,
  access: DashboardAccessMap | null | undefined,
  path: string,
): boolean {
  return canAccessDashboardModule(role, access, moduleForPath(path))
}

export function filterNavItemsByDashboardAccess<T extends { to: string }>(
  items: T[],
  role: string | undefined,
  access: DashboardAccessMap | null | undefined,
): T[] {
  return items.filter((item) => canAccessDashboardPath(role, access, item.to))
}
