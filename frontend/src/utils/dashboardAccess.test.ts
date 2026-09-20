import { describe, expect, it } from 'vitest'
import {
  canAccessDashboardPath,
  filterNavItemsByDashboardAccess,
  moduleForPath,
} from './dashboardAccess'
import { ownerNavForRole } from './dashboardNav'

describe('dashboardAccess', () => {
  it('maps known owner and workspace paths', () => {
    expect(moduleForPath('/owner/projects')).toBe('projects')
    expect(moduleForPath('/owner/payments/reconciliation')).toBe('finance')
    expect(moduleForPath('/workspace/tasks')).toBe('workspace.tasks')
    expect(moduleForPath('/crm/leads')).toBe('crm')
  })

  it('owner always passes module checks', () => {
    expect(canAccessDashboardPath('OWNER', { finance: false }, '/owner/payments')).toBe(true)
  })

  it('filters nav when module is disabled for a role', () => {
    const access = { projects: false, crm: true, catalog: true, content: true, suppliers: true, reports: true, invoices: true, settings: true, notifications: true, integrations: true, work: true, approvals: true, automations: true, departments: true, printing: true }
    const paths = ownerNavForRole('ADMIN_MANAGER', access).map((item) => item.to)
    expect(paths).not.toContain('/owner/projects')
    expect(paths).toContain('/crm')
  })

  it('filters workspace items by access map', () => {
    const items = [
      { to: '/workspace', label: 'home' },
      { to: '/workspace/projects', label: 'projects' },
      { to: '/workspace/orders', label: 'orders' },
    ]
    const filtered = filterNavItemsByDashboardAccess(items, 'ACCOUNT_MANAGER', {
      workspace: true,
      'workspace.projects': false,
      'workspace.orders': true,
    })
    expect(filtered.map((item) => item.to)).toEqual(['/workspace', '/workspace/orders'])
  })
})
