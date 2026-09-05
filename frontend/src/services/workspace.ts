import { apiGet } from './api'
import type { EmployeeWorkspaceData } from '../types/api'

export function getEmployeeWorkspace() {
  return apiGet<EmployeeWorkspaceData>('/api/workspace')
}
