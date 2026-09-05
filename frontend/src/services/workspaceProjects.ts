import { apiGet, apiPost, apiPut } from './api'
import type { Employee, WorkspaceProject, WorkspaceProjectListData, WorkspaceTaskListData } from '../types/api'

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

export function createWorkspaceProject(payload: {
  title: string
  description?: string
  customer_id: number
  status?: string
  started_at?: string
  deadline?: string
}) {
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
  return apiGet<Employee[]>(`/api/workspace/account-manager/customers${query}`)
}
