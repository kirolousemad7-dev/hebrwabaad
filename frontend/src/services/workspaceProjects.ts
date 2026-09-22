import { apiGet, apiPost, apiPut } from './api'
import { getManagedOrderLookups } from './orders'
import type { WorkspaceProject, WorkspaceProjectListData, WorkspaceTaskListData } from '../types/api'

export type CreateWorkspaceProjectPayload = {
  title: string
  description?: string
  customer_id: number
  account_manager_id?: number
  status?: string
  started_at?: string
  deadline?: string
}

export type CreateWorkspaceProjectFormInput = {
  title: string
  description?: string
  customerId: string
  accountManagerId?: string
  status?: string
  startedAt?: string
  deadline?: string
}

/**
 * Builds the create-project POST body. Optional fields are omitted when empty
 * so Account Manager callers stay compatible without account_manager_id.
 */
export function buildCreateWorkspaceProjectPayload(
  input: CreateWorkspaceProjectFormInput,
): CreateWorkspaceProjectPayload {
  const title = input.title.trim()
  const customerId = Number(input.customerId)

  const payload: CreateWorkspaceProjectPayload = {
    title,
    customer_id: customerId,
  }

  const description = input.description?.trim()
  if (description) {
    payload.description = description
  }

  const accountManagerId = input.accountManagerId?.trim()
  if (accountManagerId) {
    payload.account_manager_id = Number(accountManagerId)
  }

  const status = input.status?.trim()
  if (status) {
    payload.status = status
  }

  const startedAt = input.startedAt?.trim()
  if (startedAt) {
    payload.started_at = startedAt
  }

  const deadline = input.deadline?.trim()
  if (deadline) {
    payload.deadline = deadline
  }

  return payload
}

export function getWorkspaceProjects(query = '') {
  return apiGet<WorkspaceProjectListData>(`/api/workspace/projects${query}`)
}

export function getWorkspaceProject(projectId: number) {
  return apiGet<WorkspaceProject>(`/api/workspace/projects/${projectId}`)
}

export function getWorkspaceProjectTasks(projectId: number, query = '') {
  return apiGet<WorkspaceTaskListData & { project?: WorkspaceProject }>(
    `/api/workspace/projects/${projectId}/tasks${query}`,
  )
}

export function createWorkspaceProject(payload: CreateWorkspaceProjectPayload) {
  return apiPost<WorkspaceProject>('/api/workspace/projects', payload)
}

export function updateWorkspaceProject(
  projectId: number,
  payload: {
    title: string
    description?: string
    customer_id: number
    status: string
    started_at?: string
    deadline?: string
  },
) {
  return apiPut<WorkspaceProject>(`/api/workspace/projects/${projectId}`, payload)
}

export function getProjectCustomers(query = '') {
  return apiGet<
    Array<{
      id: number
      name: string
      email: string
      role?: string
      workspace?: string | null
      is_active: boolean
      has_account?: boolean
      account_status?: string
      projects_count?: number
      created_at: string | null
      last_seen_at?: string | null
    }>
  >(`/api/workspace/account-manager/customers${query}`)
}

/**
 * Active Account Managers for Owner project creation.
 * Reuses the existing order lookups endpoint (role-filtered on the backend).
 */
export async function getProjectAccountManagers() {
  const response = await getManagedOrderLookups()
  return { data: response.data.account_managers }
}
