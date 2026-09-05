import { describe, expect, it } from 'vitest'
import { EMPLOYEE_WORKSPACE_ROLES } from './staff'
import {
  authorizedWidgets,
  getWorkspaceForRole,
  hasCapability,
} from './employeeWorkspace'
import { homePathForRole } from './roles'

const LIVE_NAV_ROUTES = [
  '/workspace',
  '/workspace/tasks',
  '/workspace/projects',
  '/workspace/files',
  '/workspace/notifications',
  '/workspace/orders',
  '/workspace/support',
  '/workspace/directory',
  '/printing-requests',
]

describe('employee workspace resolver', () => {
  it('maps each supported employee role to its workspace', () => {
    const expected: Record<string, string> = {
      WEB_DEVELOPER: 'web-developer',
      GRAPHIC_DESIGNER: 'graphic-designer',
      VIDEO_EDITOR: 'video-editor',
      MARKETING_SPECIALIST: 'marketing',
      EVENT_SPECIALIST: 'event',
      PRINTING_SPECIALIST: 'printing',
      MEDIA_BUYER: 'media-buyer',
      ACCOUNT_MANAGER: 'account-manager',
      HR: 'hr',
    }

    for (const role of EMPLOYEE_WORKSPACE_ROLES) {
      const workspace = getWorkspaceForRole(role)
      expect(workspace).not.toBeNull()
      expect(workspace?.key).toBe(expected[role])
      expect(workspace?.homePath).toBe('/workspace')
      expect(workspace?.navigation.some((item) => item.to === '/workspace')).toBe(true)
      expect(workspace?.navigation.every((item) => LIVE_NAV_ROUTES.includes(item.to))).toBe(true)
    }
  })

  it('returns a safe fallback for unknown and non-workspace roles', () => {
    expect(getWorkspaceForRole(undefined)).toBeNull()
    expect(getWorkspaceForRole('OWNER')).toBeNull()
    expect(getWorkspaceForRole('CUSTOMER')).toBeNull()
    expect(getWorkspaceForRole('ADMIN_MANAGER')).toBeNull()
    expect(getWorkspaceForRole('SUPERADMIN')).toBeNull()
  })

  it('keeps existing destinations for owner, customer, and admin manager', () => {
    expect(homePathForRole('OWNER')).toBe('/owner')
    expect(homePathForRole('CUSTOMER')).toBe('/dashboard')
    expect(homePathForRole('ADMIN_MANAGER')).toBe('/owner/services')
    expect(homePathForRole('WEB_DEVELOPER')).toBe('/workspace')
    expect(homePathForRole('PRINTING_SPECIALIST')).toBe('/workspace')
    expect(homePathForRole('MEDIA_BUYER')).toBe('/workspace')
    expect(homePathForRole('ACCOUNT_MANAGER')).toBe('/workspace')
    expect(homePathForRole('HR')).toBe('/workspace')
  })

  it('only includes the printing queue route for printing specialists', () => {
    const printing = getWorkspaceForRole('PRINTING_SPECIALIST')
    const developer = getWorkspaceForRole('WEB_DEVELOPER')

    expect(printing?.navigation.map((item) => item.to)).toEqual([
      '/workspace',
      '/workspace/tasks',
      '/workspace/projects',
      '/workspace/files',
      '/workspace/notifications',
      '/printing-requests',
    ])
    expect(developer?.navigation.map((item) => item.to)).toEqual([
      '/workspace',
      '/workspace/tasks',
      '/workspace/projects',
      '/workspace/files',
      '/workspace/notifications',
    ])
  })

  it('authorizes widgets from server capabilities without inventing data widgets', () => {
    const designer = getWorkspaceForRole('GRAPHIC_DESIGNER')
    expect(designer).not.toBeNull()

    const widgets = authorizedWidgets(designer!, ['overview', 'projects', 'revisions'], [
      'projects.view',
      'revisions.view',
    ])

    expect(widgets.map((widget) => widget.id)).toEqual(['overview', 'projects', 'revisions'])
    expect(widgets.some((widget) => widget.id === 'printing-queue')).toBe(false)
  })

  it('does not grant owner capabilities to employee workspaces', () => {
    const developer = getWorkspaceForRole('WEB_DEVELOPER')
    expect(developer).not.toBeNull()
    expect(developer?.capabilities.includes('employees.manage' as never)).toBe(false)
    expect(hasCapability(developer!.capabilities, 'projects.view')).toBe(true)
    expect(hasCapability(developer!.capabilities, 'printing.queue.view')).toBe(false)
  })

  it('configures the web developer workspace without dead routes or fake metrics', () => {
    const developer = getWorkspaceForRole('WEB_DEVELOPER')
    expect(developer).not.toBeNull()
    expect(developer?.key).toBe('web-developer')
    expect(developer?.label).toBe('مساحة المطوّر')
    expect(developer?.navigation.map((item) => item.to)).toEqual([
      '/workspace',
      '/workspace/tasks',
      '/workspace/projects',
      '/workspace/files',
      '/workspace/notifications',
    ])
    expect(developer?.navigation.some((item) => item.to.startsWith('/owner'))).toBe(false)
    expect(developer?.widgets.map((widget) => widget.id)).toEqual([
      'overview',
      'tasks',
      'projects',
      'deadlines',
      'requirements',
      'revisions',
      'files',
      'messages',
    ])
    expect(developer?.widgets.find((widget) => widget.id === 'tasks')?.unavailableMessage).toBeUndefined()
    expect(developer?.widgets.find((widget) => widget.id === 'projects')?.unavailableMessage).toBeUndefined()
    expect(developer?.widgets.find((widget) => widget.id === 'deadlines')?.unavailableMessage).toBeUndefined()
  })

  it('drops developer widgets when the role changes', () => {
    const developer = getWorkspaceForRole('WEB_DEVELOPER')
    const designer = getWorkspaceForRole('GRAPHIC_DESIGNER')

    expect(developer?.widgets.map((widget) => widget.id)).toContain('requirements')
    expect(developer?.widgets.map((widget) => widget.id)).not.toContain('design-briefs')
    expect(designer?.widgets.map((widget) => widget.id)).toContain('design-briefs')
    expect(designer?.widgets.map((widget) => widget.id)).not.toContain('requirements')
    expect(designer?.navigation.every((item) => LIVE_NAV_ROUTES.includes(item.to))).toBe(true)
  })

  it('configures the graphic designer workspace without dead routes or fake metrics', () => {
    const designer = getWorkspaceForRole('GRAPHIC_DESIGNER')
    expect(designer).not.toBeNull()
    expect(designer?.key).toBe('graphic-designer')
    expect(designer?.label).toBe('مساحة المصمم')
    expect(designer?.description).toBe('مساحة عمل مخصصة لإدارة ومتابعة مهام التصميم والمراجعات والملفات.')
    expect(designer?.homePath).toBe('/workspace')
    expect(designer?.navigation.map((item) => item.to)).toEqual([
      '/workspace',
      '/workspace/tasks',
      '/workspace/projects',
      '/workspace/files',
      '/workspace/notifications',
    ])
    expect(designer?.navigation.some((item) => item.to.startsWith('/owner'))).toBe(false)
    expect(designer?.navigation.some((item) => item.to.includes('/projects'))).toBe(true)
    expect(designer?.widgets.map((widget) => widget.id)).toEqual([
      'overview',
      'projects',
      'design-briefs',
      'tasks',
      'deadlines',
      'files',
      'revisions',
      'messages',
    ])
    expect(designer?.widgets.find((widget) => widget.id === 'projects')?.unavailableMessage).toBeUndefined()
    expect(designer?.widgets.find((widget) => widget.id === 'design-briefs')?.unavailableMessage).toBe(
      'التصميمات غير متاحة بعد',
    )
    expect(designer?.widgets.find((widget) => widget.id === 'revisions')?.unavailableMessage).toBe(
      'التعديلات غير متاحة بعد',
    )
    expect(hasCapability(designer!.capabilities, 'briefs.view')).toBe(true)
    expect(hasCapability(designer!.capabilities, 'printing.queue.view')).toBe(false)
  })

  it('resolves designer to event workspace without leftover designer widgets', () => {
    const designer = getWorkspaceForRole('GRAPHIC_DESIGNER')
    const event = getWorkspaceForRole('EVENT_SPECIALIST')

    expect(designer?.widgets.map((widget) => widget.id)).toContain('design-briefs')
    expect(event?.key).toBe('event')
    expect(event?.label).toBe('مساحة الفعاليات')
    expect(event?.widgets.map((widget) => widget.id)).not.toContain('design-briefs')
    expect(event?.widgets.map((widget) => widget.id)).not.toContain('revisions')
    expect(event?.widgets.map((widget) => widget.id)).toContain('events')
  })

  it('keeps printing specialist live navigation and does not copy developer widgets', () => {
    const printing = getWorkspaceForRole('PRINTING_SPECIALIST')
    expect(printing?.widgets.map((widget) => widget.id)).toEqual([
      'overview',
      'printing-queue',
      'tasks',
      'projects',
      'deadlines',
      'files',
      'messages',
    ])
    expect(printing?.widgets.map((widget) => widget.id)).toContain('deadlines')
    expect(printing?.widgets.map((widget) => widget.id)).not.toContain('requirements')
  })

  it('configures media buyer, video editor, account manager, and HR workspaces', () => {
    const mediaBuyer = getWorkspaceForRole('MEDIA_BUYER')
    const videoEditor = getWorkspaceForRole('VIDEO_EDITOR')
    const accountManager = getWorkspaceForRole('ACCOUNT_MANAGER')
    const hr = getWorkspaceForRole('HR')

    expect(mediaBuyer?.key).toBe('media-buyer')
    expect(mediaBuyer?.label).toBe('مساحة الميديا باير')
    expect(mediaBuyer?.homePath).toBe('/workspace')
    expect(mediaBuyer?.navigation.map((item) => item.to)).toEqual([
      '/workspace',
      '/workspace/tasks',
      '/workspace/projects',
      '/workspace/notifications',
    ])
    expect(mediaBuyer?.widgets.map((widget) => widget.id)).toEqual([
      'overview',
      'tasks',
      'projects',
      'campaigns',
      'budgets',
      'performance',
      'deadlines',
      'reports',
    ])
    expect(mediaBuyer?.widgets.find((widget) => widget.id === 'campaigns')?.unavailableMessage).toBe(
      'الحملات غير متاحة بعد',
    )
    expect(mediaBuyer?.widgets.find((widget) => widget.id === 'tasks')?.unavailableMessage).toBeUndefined()

    expect(videoEditor?.key).toBe('video-editor')
    expect(videoEditor?.label).toBe('مساحة المونتير')
    expect(videoEditor?.widgets.map((widget) => widget.id)).toEqual([
      'overview',
      'tasks',
      'projects',
      'files',
      'revisions',
      'deadlines',
      'messages',
    ])

    expect(accountManager?.key).toBe('account-manager')
    expect(accountManager?.label).toBe('مساحة الأكونت مانجر')
    expect(accountManager?.navigation.map((item) => item.to)).toEqual([
      '/workspace',
      '/workspace/tasks',
      '/workspace/projects',
      '/workspace/files',
      '/workspace/notifications',
      '/workspace/orders',
      '/workspace/support',
    ])
    expect(accountManager?.widgets.map((widget) => widget.id)).toEqual([
      'overview',
      'projects',
      'tasks',
      'task-progress',
      'deadlines',
      'files',
      'clients',
      'client-requests',
    ])
    expect(hasCapability(accountManager!.capabilities, 'tasks.manage')).toBe(true)
    expect(accountManager?.navigation.some((item) => item.to.startsWith('/owner'))).toBe(false)

    expect(hr?.key).toBe('hr')
    expect(hr?.label).toBe('مساحة الـ HR')
    expect(hr?.navigation.map((item) => item.to)).toEqual([
      '/workspace',
      '/workspace/tasks',
      '/workspace/notifications',
      '/workspace/directory',
    ])
    expect(hr?.widgets.map((widget) => widget.id)).toEqual([
      'overview',
      'employees',
      'active-employees',
      'inactive-employees',
      'tasks',
      'employee-requests',
      'attendance',
    ])
    expect(hr?.widgets.find((widget) => widget.id === 'attendance')?.unavailableMessage).toBe(
      'الحضور والانصراف غير متاح بعد',
    )
    expect(hasCapability(hr!.capabilities, 'employees.directory.view')).toBe(true)
    expect(hasCapability(hr!.capabilities, 'tasks.manage')).toBe(false)
  })

  it('drops previous workspace capabilities after a role change', () => {
    const mediaBuyer = getWorkspaceForRole('MEDIA_BUYER')
    const accountManager = getWorkspaceForRole('ACCOUNT_MANAGER')
    const hr = getWorkspaceForRole('HR')
    const developer = getWorkspaceForRole('WEB_DEVELOPER')

    expect(mediaBuyer?.widgets.map((widget) => widget.id)).toContain('campaigns')
    expect(developer?.widgets.map((widget) => widget.id)).not.toContain('campaigns')
    expect(accountManager?.widgets.map((widget) => widget.id)).toContain('task-progress')
    expect(developer?.widgets.map((widget) => widget.id)).not.toContain('task-progress')
    expect(hr?.widgets.map((widget) => widget.id)).toContain('employees')
    expect(accountManager?.widgets.map((widget) => widget.id)).not.toContain('employees')
    expect(hr?.navigation.map((item) => item.to)).toContain('/workspace/directory')
    expect(accountManager?.navigation.map((item) => item.to)).not.toContain('/workspace/directory')
  })

  it('configures marketing, event, and printing workspaces without dead routes', () => {
    const marketing = getWorkspaceForRole('MARKETING_SPECIALIST')
    const event = getWorkspaceForRole('EVENT_SPECIALIST')
    const printing = getWorkspaceForRole('PRINTING_SPECIALIST')

    expect(marketing?.key).toBe('marketing')
    expect(marketing?.label).toBe('مساحة التسويق')
    expect(marketing?.homePath).toBe('/workspace')
    expect(marketing?.navigation.map((item) => item.to)).toEqual([
      '/workspace',
      '/workspace/tasks',
      '/workspace/projects',
      '/workspace/notifications',
    ])
    expect(marketing?.widgets.map((widget) => widget.id)).toEqual([
      'overview',
      'campaigns',
      'tasks',
      'projects',
      'content',
      'deadlines',
      'client-requests',
      'reports',
    ])
    expect(marketing?.widgets.find((widget) => widget.id === 'campaigns')?.unavailableMessage).toBe(
      'الحملات غير متاحة بعد',
    )
    expect(marketing?.widgets.find((widget) => widget.id === 'tasks')?.unavailableMessage).toBeUndefined()

    expect(event?.key).toBe('event')
    expect(event?.label).toBe('مساحة الفعاليات')
    expect(event?.widgets.map((widget) => widget.id)).toEqual([
      'overview',
      'tasks',
      'projects',
      'events',
      'client-requests',
      'deadlines',
      'files',
    ])

    expect(printing?.key).toBe('printing')
    expect(printing?.label).toBe('مساحة الطباعة')
    expect(printing?.description).toBe('مساحة الطباعة لمتابعة طابور الطلبات الحقيقي والمهام المعيّنة.')
    expect(printing?.navigation.map((item) => item.to)).toEqual([
      '/workspace',
      '/workspace/tasks',
      '/workspace/projects',
      '/workspace/files',
      '/workspace/notifications',
      '/printing-requests',
    ])
  })
})
