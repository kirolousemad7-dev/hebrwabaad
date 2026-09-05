import { apiGet } from './api'
import type { HealthData } from '../types/api'

export function getHealth() {
  return apiGet<HealthData>('/api/health')
}
