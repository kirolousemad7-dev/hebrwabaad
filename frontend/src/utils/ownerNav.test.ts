import { describe, expect, it } from 'vitest'
import {
  isDashboardNavActive,
  navItemHref,
  ownerNavForRole,
  ownerNavSectionsForRole,
} from './dashboardNav'

describe('owner grouped navigation', () => {
  it('shows finance and users only to OWNER', () => {
    const ownerPaths = ownerNavForRole('OWNER').map((item) => navItemHref(item))
    const adminPaths = ownerNavForRole('ADMIN_MANAGER').map((item) => navItemHref(item))

    expect(ownerPaths).toContain('/owner/payments')
    expect(ownerPaths).toContain('/owner/employees')
    expect(ownerPaths).toContain('/owner/files')
    expect(adminPaths).not.toContain('/owner/payments')
    expect(adminPaths).not.toContain('/owner/employees')
    expect(adminPaths).not.toContain('/owner/files')
  })

  it('keeps catalog and CRM links for both OWNER and ADMIN_MANAGER', () => {
    for (const role of ['OWNER', 'ADMIN_MANAGER'] as const) {
      const paths = ownerNavForRole(role).map((item) => item.to)
      expect(paths).toContain('/owner/services')
      expect(paths).toContain('/owner/seo')
      expect(paths).toContain('/owner/blog')
      expect(paths).toContain('/crm/leads')
      expect(paths).toContain('/owner/suppliers')
    }
  })

  it('returns non-empty grouped sections without inventing missing routes', () => {
    const sections = ownerNavSectionsForRole('OWNER')
    const ids = sections.map((section) => section.id)
    expect(ids).toEqual(['dashboard', 'crm', 'operations', 'suppliers', 'catalog', 'content', 'reports', 'settings'])
    expect(sections.every((section) => section.items.length > 0)).toBe(true)
    expect(ownerNavForRole('OWNER').some((item) => item.to.includes('invoice'))).toBe(false)
    expect(ownerNavForRole('OWNER').some((item) => item.label.includes('صلاحيات'))).toBe(false)
  })

  it('distinguishes settings deep-links by query tab', () => {
    const brand = ownerNavForRole('OWNER').find((item) => item.search === '?tab=brand')
    const website = ownerNavForRole('OWNER').find((item) => item.search === '?tab=website')
    expect(brand).toBeTruthy()
    expect(website).toBeTruthy()
    expect(isDashboardNavActive(brand!, '/owner/settings', '?tab=brand')).toBe(true)
    expect(isDashboardNavActive(brand!, '/owner/settings', '?tab=website')).toBe(false)
    expect(isDashboardNavActive(website!, '/owner/settings', '?tab=website')).toBe(true)
  })
})
