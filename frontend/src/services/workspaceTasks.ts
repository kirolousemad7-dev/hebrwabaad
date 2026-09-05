import { apiGet, apiPatch, apiPost, apiPut } from './api'
import type { Employee, EmployeeListData, WorkspaceTask, WorkspaceTaskListData } from '../types/api'

export function getMyTasks(query = '') {
  return apiGet<WorkspaceTaskListData>(`/api/workspace/tasks${query}`)
}

export function getWorkspaceTask(taskId: number) {
  return apiGet<WorkspaceTask>(`/api/workspace/tasks/${taskId}`)
}

export function updateMyTaskStatus(taskId: number, status: string) {
  return apiPatch<WorkspaceTask>(`/api/workspace/tasks/${taskId}/status`, { status })
}

export function getManagedTasks(query = '') {
  return apiGet<WorkspaceTaskListData>(`/api/workspace/account-manager/tasks${query}`)
}

export type ManagedTaskPayload = {
  title: string
  description?: string
  project_id: number
  assigned_to: number
  priority: string
  deadline?: string
  status?: string
}

export function createManagedTask(payload: ManagedTaskPayload) {
  return apiPost<WorkspaceTask>('/api/workspace/account-manager/tasks', payload)
}

export function updateManagedTask(taskId: number, payload: Required<Pick<ManagedTaskPayload, 'title' | 'project_id' | 'assigned_to' | 'priority' | 'status'>> & ManagedTaskPayload) {
  return apiPut<WorkspaceTask>(`/api/workspace/account-manager/tasks/${taskId}`, payload)
}

export function getTaskAssignees() {
  return apiGet<Employee[]>('/api/workspace/account-manager/assignees')
}

export function getHrEmployees(query = '') {
  return apiGet<EmployeeListData>(`/api/workspace/hr/employees${query}`)
}
