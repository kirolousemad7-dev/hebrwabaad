/**
 * Roles allowed to manage the catalog. The backend is the source of truth
 * (auth:sanctum + role:OWNER,ADMIN_MANAGER); this list only drives UI visibility.
 */
import { isEmployeeWorkspaceRole } from './staff'

export const CATALOG_MANAGER_ROLES = ['OWNER', 'ADMIN_MANAGER']

export const PRINTING_OPERATIONS_ROLES = ['OWNER', 'ADMIN_MANAGER', 'PRINTING_SPECIALIST']

export function isOwner(role: string | undefined): boolean {
  return role === 'OWNER'
}

export function isCatalogManager(role: string | undefined): boolean {
  return role !== undefined && CATALOG_MANAGER_ROLES.includes(role)
}

export function canReviewPrintingRequests(role: string | undefined): boolean {
  return role !== undefined && PRINTING_OPERATIONS_ROLES.includes(role)
}

export function homePathForRole(role: string | undefined): string {
  if (isOwner(role)) {
    return '/owner'
  }

  if (role === 'ADMIN_MANAGER') {
    return '/owner/services'
  }

  if (isEmployeeWorkspaceRole(role)) {
    return '/workspace'
  }

  return '/dashboard'
}

export { EMPLOYEE_ROLES, EMPLOYEE_WORKSPACE_ROLES, isEmployeeRole, isEmployeeWorkspaceRole, workspaceKeyForRole } from './staff'
