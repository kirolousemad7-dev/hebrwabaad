import { apiGet, apiPatch, apiPost, apiPut } from './api'
import type { Employee, EmployeeListData } from '../types/api'

export type EmployeeListFilters = {
  q?: string
  role?: string
  is_active?: '' | 'true' | 'false'
  sort?: 'name' | 'created_at' | 'is_active'
  direction?: 'asc' | 'desc'
  page?: number
}

export type EmployeeWritePayload = {
  name: string
  email: string
  role: string
  password?: string
  password_confirmation?: string
  is_active?: boolean
}

export function employeeListQuery(filters: EmployeeListFilters): string {
  const params = new URLSearchParams()

  if (filters.q?.trim()) {
    params.set('q', filters.q.trim())
  }

  if (filters.role) {
    params.set('role', filters.role)
  }

  if (filters.is_active === 'true' || filters.is_active === 'false') {
    params.set('is_active', filters.is_active)
  }

  if (filters.sort) {
    params.set('sort', filters.sort)
  }

  if (filters.direction) {
    params.set('direction', filters.direction)
  }

  if (filters.page && filters.page > 1) {
    params.set('page', String(filters.page))
  }

  const query = params.toString()
  return query === '' ? '' : `?${query}`
}

export function getEmployees(filters: EmployeeListFilters = {}) {
  return apiGet<EmployeeListData>(`/api/admin/employees${employeeListQuery(filters)}`)
}

export function getEmployee(id: number) {
  return apiGet<Employee>(`/api/admin/employees/${id}`)
}

export function createEmployee(payload: EmployeeWritePayload) {
  return apiPost<Employee>('/api/admin/employees', payload)
}

export function updateEmployee(id: number, payload: EmployeeWritePayload) {
  return apiPut<Employee>(`/api/admin/employees/${id}`, {
    name: payload.name,
    email: payload.email,
    role: payload.role,
  })
}

export function setEmployeeActive(id: number, isActive: boolean) {
  return apiPatch<Employee>(`/api/admin/employees/${id}/status`, { is_active: isActive })
}
