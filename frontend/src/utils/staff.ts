/**
 * Central role → workspace mapping for employee dashboards.
 */
export const EMPLOYEE_ROLES = [
  'ADMIN_MANAGER',
  'WEB_DEVELOPER',
  'GRAPHIC_DESIGNER',
  'VIDEO_EDITOR',
  'MARKETING_SPECIALIST',
  'EVENT_SPECIALIST',
  'PRINTING_SPECIALIST',
  'MEDIA_BUYER',
  'ACCOUNT_MANAGER',
  'HR',
] as const

export type EmployeeRole = (typeof EMPLOYEE_ROLES)[number]

export const EMPLOYEE_ROLE_LABELS: Record<EmployeeRole, string> = {
  ADMIN_MANAGER: 'مدير إداري',
  WEB_DEVELOPER: 'مطوّر ويب',
  GRAPHIC_DESIGNER: 'مصمم جرافيك',
  VIDEO_EDITOR: 'مونتير',
  MARKETING_SPECIALIST: 'أخصائي تسويق',
  EVENT_SPECIALIST: 'أخصائي فعاليات',
  PRINTING_SPECIALIST: 'أخصائي طباعة',
  MEDIA_BUYER: 'ميديا باير',
  ACCOUNT_MANAGER: 'أكونت مانجر',
  HR: 'الموارد البشرية',
}

export const ROLE_WORKSPACE: Record<string, { key: string; label: string }> = {
  OWNER: { key: 'owner', label: 'لوحة المالك' },
  CUSTOMER: { key: 'customer', label: 'لوحة العميل' },
  ADMIN_MANAGER: { key: 'admin-manager', label: 'إدارة الكتالوج' },
  WEB_DEVELOPER: { key: 'web-developer', label: 'مساحة المطوّر' },
  GRAPHIC_DESIGNER: { key: 'graphic-designer', label: 'مساحة المصمم' },
  VIDEO_EDITOR: { key: 'video-editor', label: 'مساحة المونتير' },
  MARKETING_SPECIALIST: { key: 'marketing', label: 'مساحة التسويق' },
  EVENT_SPECIALIST: { key: 'event', label: 'مساحة الفعاليات' },
  PRINTING_SPECIALIST: { key: 'printing', label: 'مساحة الطباعة' },
  MEDIA_BUYER: { key: 'media-buyer', label: 'مساحة الميديا باير' },
  ACCOUNT_MANAGER: { key: 'account-manager', label: 'مساحة الأكونت مانجر' },
  HR: { key: 'hr', label: 'مساحة الـ HR' },
}

export function isEmployeeRole(role: string | undefined): role is EmployeeRole {
  return role !== undefined && (EMPLOYEE_ROLES as readonly string[]).includes(role)
}

export const EMPLOYEE_WORKSPACE_ROLES = [
  'WEB_DEVELOPER',
  'GRAPHIC_DESIGNER',
  'VIDEO_EDITOR',
  'MARKETING_SPECIALIST',
  'EVENT_SPECIALIST',
  'PRINTING_SPECIALIST',
  'MEDIA_BUYER',
  'ACCOUNT_MANAGER',
  'HR',
] as const

export type EmployeeWorkspaceRole = (typeof EMPLOYEE_WORKSPACE_ROLES)[number]

export function isEmployeeWorkspaceRole(role: string | undefined): role is EmployeeWorkspaceRole {
  return role !== undefined && (EMPLOYEE_WORKSPACE_ROLES as readonly string[]).includes(role)
}

export function workspaceKeyForRole(role: string | undefined): string | null {
  if (!role) {
    return null
  }

  return ROLE_WORKSPACE[role]?.key ?? null
}
