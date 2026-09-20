import type {
  CustomerDashboardData,
  CustomerProject,
  CustomerProjectActivityListData,
} from '../types/api'
import { apiGet } from './api'

export type CustomerProjectActivitiesQuery = {
  page?: number
  per_page?: number
}

export function customerProjectActivitiesPath(
  projectId: number,
  query: CustomerProjectActivitiesQuery = {},
): string {
  const params = new URLSearchParams()
  const page = query.page ?? 1
  const perPage = query.per_page ?? 25
  params.set('page', String(page))
  params.set('per_page', String(perPage))

  return `/api/customer/projects/${projectId}/activities?${params.toString()}`
}

export function getCustomerDashboard() {
  return apiGet<CustomerDashboardData>('/api/customer/dashboard')
}

export function getCustomerProjects() {
  return apiGet<CustomerProject[]>('/api/customer/projects')
}

export function getCustomerProject(id: number) {
  return apiGet<CustomerProject>(`/api/customer/projects/${id}`)
}

/**
 * Customer-safe per-project activity feed (no metadata).
 */
export function getCustomerProjectActivities(projectId: number, page = 1, perPage = 25) {
  return apiGet<CustomerProjectActivityListData>(
    customerProjectActivitiesPath(projectId, { page, per_page: perPage }),
  )
}
