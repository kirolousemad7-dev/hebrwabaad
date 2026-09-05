import type { CustomerDashboardData, CustomerProject } from '../types/api'
import { apiGet } from './api'

export function getCustomerDashboard() {
  return apiGet<CustomerDashboardData>('/api/customer/dashboard')
}

export function getCustomerProjects() {
  return apiGet<CustomerProject[]>('/api/customer/projects')
}

export function getCustomerProject(id: number) {
  return apiGet<CustomerProject>(`/api/customer/projects/${id}`)
}
