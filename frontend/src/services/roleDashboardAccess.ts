import { apiGet, apiPut } from './api'

export type DashboardModuleCatalogItem = {
  key: string
  label: string
  shell: string
  description: string | null
}

export type RoleDashboardAccessRow = {
  role: string
  modules: Record<string, boolean>
}

export type RoleDashboardAccessMatrix = {
  roles: RoleDashboardAccessRow[]
  catalog: DashboardModuleCatalogItem[]
}

export function getRoleDashboardAccessMatrix() {
  return apiGet<RoleDashboardAccessMatrix>('/api/admin/role-dashboard-access')
}

export function getRoleDashboardAccess(role: string) {
  return apiGet<{
    role: string
    modules: Record<string, boolean>
    catalog: DashboardModuleCatalogItem[]
  }>(`/api/admin/role-dashboard-access/${role}`)
}

export function updateRoleDashboardAccess(role: string, modules: Record<string, boolean>) {
  return apiPut<{ role: string; modules: Record<string, boolean> }>(
    `/api/admin/role-dashboard-access/${role}`,
    { modules },
  )
}

