import { apiGet } from './api'
import type { OwnerDashboardData } from '../types/api'

export function getOwnerDashboard() {
  return apiGet<OwnerDashboardData>('/api/admin/dashboard')
}
