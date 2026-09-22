import { describe, expect, it, vi, beforeEach } from 'vitest'
import { createStaffCustomer, listStaffCustomers } from './staffCustomers'

vi.mock('./api', () => ({
  apiPost: vi.fn(),
  apiGet: vi.fn(),
}))

vi.mock('./workspaceProjects', () => ({
  getProjectCustomers: vi.fn(),
}))

import { apiPost } from './api'
import { getProjectCustomers } from './workspaceProjects'

describe('staffCustomers service', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('creates a staff customer via workspace endpoint', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      data: {
        id: 9,
        name: 'عميل',
        email: 'c@example.com',
        is_active: true,
        has_account: false,
        account_status: 'NO_ACCOUNT',
        projects_count: 0,
        created_at: null,
      },
    })

    const result = await createStaffCustomer({ name: 'عميل', email: 'c@example.com' })

    expect(apiPost).toHaveBeenCalledWith('/api/workspace/account-manager/customers', {
      name: 'عميل',
      email: 'c@example.com',
    })
    expect(result.data.id).toBe(9)
    expect(result.data.has_account).toBe(false)
  })

  it('lists customers through the shared project customers endpoint', async () => {
    vi.mocked(getProjectCustomers).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          name: 'A',
          email: 'a@example.com',
          is_active: true,
          has_account: true,
          account_status: 'ACTIVE',
          projects_count: 2,
          created_at: null,
        },
      ],
    })

    const result = await listStaffCustomers('?q=A')
    expect(getProjectCustomers).toHaveBeenCalledWith('?q=A')
    expect(result.data[0]?.projects_count).toBe(2)
  })
})
