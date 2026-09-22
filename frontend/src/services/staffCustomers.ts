import { apiPost } from './api'
import { getProjectCustomers } from './workspaceProjects'

export type StaffCustomer = {
  id: number
  name: string
  email: string
  role?: string
  workspace?: string | null
  is_active: boolean
  has_account: boolean
  account_status: 'ACTIVE' | 'NO_ACCOUNT' | string
  projects_count: number
  created_at: string | null
  last_seen_at?: string | null
}

export type CreateStaffCustomerPayload = {
  name: string
  email: string
}

export function listStaffCustomers(query = '') {
  return getProjectCustomers(query) as Promise<{ data: StaffCustomer[] }>
}

export function createStaffCustomer(payload: CreateStaffCustomerPayload) {
  return apiPost<StaffCustomer>('/api/workspace/account-manager/customers', payload)
}
