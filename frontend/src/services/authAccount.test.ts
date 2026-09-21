import { describe, expect, it } from 'vitest'
import { ownerNavForRole } from '../utils/dashboardNav'
import { canAccessDashboardPath } from '../utils/dashboardAccess'

describe('owner account access', () => {
  it('exposes حسابي in the Owner settings navigation', () => {
    const paths = ownerNavForRole('OWNER').map((item) => item.to)
    expect(paths).toContain('/owner/account')
    expect(paths).toContain('/owner/pages')
    expect(ownerNavForRole('OWNER').some((item) => item.label === 'حسابي')).toBe(true)
  })

  it('maps /owner/account under the settings module while Owner always retains access', () => {
    expect(canAccessDashboardPath('OWNER', { settings: false }, '/owner/account')).toBe(true)
    expect(canAccessDashboardPath('ADMIN_MANAGER', { settings: true }, '/owner/account')).toBe(true)
    expect(canAccessDashboardPath('ADMIN_MANAGER', { settings: false }, '/owner/account')).toBe(false)
  })
})
