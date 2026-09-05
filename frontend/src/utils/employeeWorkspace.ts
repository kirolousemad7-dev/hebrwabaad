import type { DashboardNavItem } from './dashboardNav'
import {
  EMPLOYEE_ROLE_LABELS,
  ROLE_WORKSPACE,
  isEmployeeWorkspaceRole,
  type EmployeeWorkspaceRole,
} from './staff'

export const EMPLOYEE_CAPABILITIES = [
  'projects.view',
  'tasks.view',
  'tasks.manage',
  'files.view',
  'messages.view',
  'revisions.view',
  'requirements.view',
  'deadlines.view',
  'briefs.view',
  'campaigns.view',
  'events.view',
  'printing.queue.view',
  'budgets.view',
  'reports.view',
  'clients.view',
  'requests.view',
  'employees.directory.view',
  'attendance.view',
] as const

export type EmployeeCapability = (typeof EMPLOYEE_CAPABILITIES)[number]

export const WORKSPACE_WIDGET_IDS = [
  'overview',
  'projects',
  'tasks',
  'task-progress',
  'files',
  'messages',
  'revisions',
  'requirements',
  'deadlines',
  'design-briefs',
  'campaigns',
  'events',
  'printing-queue',
  'budgets',
  'reports',
  'clients',
  'client-requests',
  'employees',
  'active-employees',
  'inactive-employees',
  'attendance',
  'employee-requests',
  'content',
  'performance',
] as const

export type WorkspaceWidgetId = (typeof WORKSPACE_WIDGET_IDS)[number]

export type WorkspaceWidgetDefinition = {
  id: WorkspaceWidgetId
  title: string
  requiredCapability?: EmployeeCapability
  order: number
  unavailableMessage?: string
}

export type EmployeeWorkspaceConfig = {
  key: string
  role: EmployeeWorkspaceRole
  label: string
  description: string
  homePath: '/workspace'
  navigation: DashboardNavItem[]
  capabilities: EmployeeCapability[]
  widgets: WorkspaceWidgetDefinition[]
}

const WIDGET_CATALOG: Record<Exclude<WorkspaceWidgetId, 'overview'>, WorkspaceWidgetDefinition> = {
  projects: {
    id: 'projects',
    title: 'المشاريع',
    requiredCapability: 'projects.view',
    order: 20,
  },
  tasks: {
    id: 'tasks',
    title: 'المهام',
    requiredCapability: 'tasks.view',
    order: 12,
  },
  'task-progress': {
    id: 'task-progress',
    title: 'تقدم المهام',
    requiredCapability: 'tasks.manage',
    order: 14,
  },
  deadlines: {
    id: 'deadlines',
    title: 'المواعيد النهائية',
    requiredCapability: 'deadlines.view',
    order: 16,
  },
  requirements: {
    id: 'requirements',
    title: 'المتطلبات',
    requiredCapability: 'requirements.view',
    order: 38,
    unavailableMessage: 'المتطلبات غير متاحة بعد',
  },
  files: {
    id: 'files',
    title: 'الملفات',
    requiredCapability: 'files.view',
    order: 40,
  },
  messages: {
    id: 'messages',
    title: 'الرسائل',
    requiredCapability: 'messages.view',
    order: 50,
    unavailableMessage: 'الرسائل غير متاحة بعد',
  },
  revisions: {
    id: 'revisions',
    title: 'التعديلات',
    requiredCapability: 'revisions.view',
    order: 60,
    unavailableMessage: 'التعديلات غير متاحة بعد',
  },
  'design-briefs': {
    id: 'design-briefs',
    title: 'التصميمات',
    requiredCapability: 'briefs.view',
    order: 22,
    unavailableMessage: 'التصميمات غير متاحة بعد',
  },
  campaigns: {
    id: 'campaigns',
    title: 'الحملات',
    requiredCapability: 'campaigns.view',
    order: 18,
    unavailableMessage: 'الحملات غير متاحة بعد',
  },
  events: {
    id: 'events',
    title: 'الفعاليات',
    requiredCapability: 'events.view',
    order: 18,
    unavailableMessage: 'الفعاليات غير متاحة بعد',
  },
  'printing-queue': {
    id: 'printing-queue',
    title: 'طابور الطباعة',
    requiredCapability: 'printing.queue.view',
    order: 12,
  },
  budgets: {
    id: 'budgets',
    title: 'الميزانيات',
    requiredCapability: 'budgets.view',
    order: 40,
    unavailableMessage: 'الميزانيات غير متاحة بعد',
  },
  reports: {
    id: 'reports',
    title: 'التقارير',
    requiredCapability: 'reports.view',
    order: 50,
    unavailableMessage: 'التقارير غير متاحة بعد',
  },
  clients: {
    id: 'clients',
    title: 'العملاء',
    requiredCapability: 'clients.view',
    order: 40,
    unavailableMessage: 'لا يوجد عملاء مرتبطون بعد',
  },
  'client-requests': {
    id: 'client-requests',
    title: 'طلبات العملاء',
    requiredCapability: 'requests.view',
    order: 45,
    unavailableMessage: 'طلبات العملاء غير متاحة بعد',
  },
  employees: {
    id: 'employees',
    title: 'دليل الموظفين',
    requiredCapability: 'employees.directory.view',
    order: 12,
  },
  'active-employees': {
    id: 'active-employees',
    title: 'الموظفون النشطون',
    requiredCapability: 'employees.directory.view',
    order: 13,
  },
  'inactive-employees': {
    id: 'inactive-employees',
    title: 'الموظفون غير النشطين',
    requiredCapability: 'employees.directory.view',
    order: 14,
  },
  attendance: {
    id: 'attendance',
    title: 'الحضور والانصراف',
    requiredCapability: 'attendance.view',
    order: 50,
    unavailableMessage: 'الحضور والانصراف غير متاح بعد',
  },
  'employee-requests': {
    id: 'employee-requests',
    title: 'طلبات الموظفين',
    order: 40,
    unavailableMessage: 'طلبات الموظفين غير متاحة بعد',
  },
  content: {
    id: 'content',
    title: 'المحتوى',
    order: 16,
    unavailableMessage: 'المحتوى غير متاح بعد',
  },
  performance: {
    id: 'performance',
    title: 'الأداء',
    order: 18,
    unavailableMessage: 'تقارير الأداء غير متاحة بعد',
  },
}

const CAPABILITIES_BY_ROLE: Record<EmployeeWorkspaceRole, EmployeeCapability[]> = {
  WEB_DEVELOPER: [
    'tasks.view',
    'projects.view',
    'deadlines.view',
    'requirements.view',
    'revisions.view',
    'files.view',
    'messages.view',
  ],
  GRAPHIC_DESIGNER: [
    'projects.view',
    'briefs.view',
    'tasks.view',
    'deadlines.view',
    'files.view',
    'revisions.view',
    'messages.view',
  ],
  VIDEO_EDITOR: ['projects.view', 'tasks.view', 'files.view', 'revisions.view', 'deadlines.view', 'messages.view'],
  MARKETING_SPECIALIST: ['campaigns.view', 'tasks.view', 'projects.view', 'deadlines.view', 'reports.view', 'requests.view'],
  EVENT_SPECIALIST: ['events.view', 'tasks.view', 'projects.view', 'deadlines.view', 'files.view', 'requests.view'],
  PRINTING_SPECIALIST: ['printing.queue.view', 'tasks.view', 'projects.view', 'deadlines.view', 'files.view', 'messages.view'],
  MEDIA_BUYER: ['campaigns.view', 'tasks.view', 'projects.view', 'deadlines.view', 'budgets.view', 'reports.view'],
  ACCOUNT_MANAGER: [
    'tasks.view',
    'tasks.manage',
    'deadlines.view',
    'clients.view',
    'requests.view',
    'projects.view',
    'files.view',
  ],
  HR: ['employees.directory.view', 'tasks.view', 'attendance.view'],
}

const WIDGET_ORDER_BY_ROLE: Partial<Record<EmployeeWorkspaceRole, Partial<Record<WorkspaceWidgetId, number>>>> = {
  WEB_DEVELOPER: {
    overview: 10, tasks: 12, projects: 14, deadlines: 16, requirements: 40, revisions: 45, files: 50, messages: 60,
  },
  GRAPHIC_DESIGNER: {
    overview: 10, projects: 12, 'design-briefs': 14, tasks: 16, deadlines: 18, files: 40, revisions: 45, messages: 50,
  },
  VIDEO_EDITOR: {
    overview: 10, tasks: 12, projects: 14, files: 16, revisions: 18, deadlines: 20, messages: 50,
  },
  MEDIA_BUYER: {
    overview: 10, tasks: 12, projects: 13, campaigns: 14, budgets: 16, performance: 18, deadlines: 20, reports: 50,
  },
  ACCOUNT_MANAGER: {
    overview: 10, projects: 11, tasks: 12, 'task-progress': 14, deadlines: 16, files: 38, clients: 40, 'client-requests': 45,
  },
  HR: {
    overview: 10, employees: 12, 'active-employees': 13, 'inactive-employees': 14, tasks: 16, 'employee-requests': 40, attendance: 50,
  },
  MARKETING_SPECIALIST: {
    overview: 10, campaigns: 12, tasks: 14, projects: 15, content: 16, deadlines: 18, 'client-requests': 20, reports: 22,
  },
  EVENT_SPECIALIST: {
    overview: 10, tasks: 12, projects: 13, events: 14, 'client-requests': 16, deadlines: 18, files: 40,
  },
  PRINTING_SPECIALIST: {
    overview: 10, 'printing-queue': 12, tasks: 14, projects: 15, deadlines: 16, files: 40, messages: 50,
  },
}

const OVERVIEW_WIDGET: WorkspaceWidgetDefinition = {
  id: 'overview',
  title: 'نظرة عامة',
  order: 10,
}

const DEFAULT_WORKSPACE_DESCRIPTION = 'مساحة العمل حسب الدور الحالي. لا تُعرض بيانات غير مصرّح بها.'

const DASHBOARD_NAV_ITEM: DashboardNavItem = {
  to: '/workspace',
  label: 'لوحة التحكم',
  end: true,
  icon: 'home',
}

function widgetOrderForRole(role: EmployeeWorkspaceRole, widget: WorkspaceWidgetDefinition): number {
  return WIDGET_ORDER_BY_ROLE[role]?.[widget.id] ?? widget.order
}

function widgetsForCapabilities(
  role: EmployeeWorkspaceRole,
  capabilities: EmployeeCapability[],
): WorkspaceWidgetDefinition[] {
  const allowed = new Set(capabilities)
  const widgets = Object.values(WIDGET_CATALOG).filter((widget) => {
    if (widget.id === 'employee-requests') {
      return role === 'HR'
    }

    if (widget.id === 'content') {
      return role === 'MARKETING_SPECIALIST'
    }

    if (widget.id === 'performance') {
      return role === 'MEDIA_BUYER'
    }

    return widget.requiredCapability !== undefined && allowed.has(widget.requiredCapability)
  })

  return [OVERVIEW_WIDGET, ...widgets].sort(
    (left, right) => widgetOrderForRole(role, left) - widgetOrderForRole(role, right),
  )
}

function navigationForRole(role: EmployeeWorkspaceRole, capabilities: EmployeeCapability[]): DashboardNavItem[] {
  const items: DashboardNavItem[] = [DASHBOARD_NAV_ITEM]

  if (capabilities.includes('tasks.view')) {
    items.push({ to: '/workspace/tasks', label: 'المهام', icon: 'tasks' })
  }

  if (capabilities.includes('projects.view')) {
    items.push({ to: '/workspace/projects', label: 'المشاريع', icon: 'projects' })
  }

  if (capabilities.includes('files.view')) {
    items.push({ to: '/workspace/files', label: 'الملفات', icon: 'files' })
  }

  items.push({ to: '/workspace/notifications', label: 'الإشعارات', icon: 'notifications' })

  if (role === 'ACCOUNT_MANAGER') {
    items.push({ to: '/workspace/orders', label: 'الطلبات', icon: 'orders' })
    items.push({ to: '/workspace/support', label: 'الدعم', icon: 'messages' })
  }

  if (role === 'HR') {
    items.push({ to: '/workspace/directory', label: 'الموظفون', icon: 'employees' })
  }

  if (role === 'PRINTING_SPECIALIST') {
    items.push({ to: '/printing-requests', label: 'طلبات الطباعة', icon: 'printing' })
  }

  return items
}

const WORKSPACE_DESCRIPTIONS: Partial<Record<EmployeeWorkspaceRole, string>> = {
  WEB_DEVELOPER: 'مساحة المطوّر لمتابعة المهام والمشاريع والمواعيد النهائية عند تفعيلها في النظام.',
  GRAPHIC_DESIGNER: 'مساحة عمل مخصصة لإدارة ومتابعة مهام التصميم والمراجعات والملفات.',
  VIDEO_EDITOR: 'مساحة المونتير لمتابعة مهام المونتاج والملفات والتعديلات.',
  MEDIA_BUYER: 'مساحة الميديا باير لمتابعة المهام والحملات عند تفعيل إدارة الإعلانات.',
  ACCOUNT_MANAGER: 'مساحة الأكونت مانجر لإنشاء المهام وتعيينها ومتابعة تقدم الموظفين.',
  HR: 'مساحة الـ HR للاطلاع على دليل الموظفين وحالتهم، دون إدارة حسابات الدخول.',
  MARKETING_SPECIALIST: 'مساحة التسويق لمتابعة المهام والحملات عند تفعيل إدارة الإعلانات.',
  EVENT_SPECIALIST: 'مساحة الفعاليات لمتابعة المهام والفعاليات عند تفعيلها.',
  PRINTING_SPECIALIST: 'مساحة الطباعة لمتابعة طابور الطلبات الحقيقي والمهام المعيّنة.',
}

function descriptionForRole(role: EmployeeWorkspaceRole): string {
  return WORKSPACE_DESCRIPTIONS[role] ?? DEFAULT_WORKSPACE_DESCRIPTION
}

const WORKSPACE_CONFIG: Record<EmployeeWorkspaceRole, EmployeeWorkspaceConfig> = {
  WEB_DEVELOPER: buildConfig('WEB_DEVELOPER'),
  GRAPHIC_DESIGNER: buildConfig('GRAPHIC_DESIGNER'),
  VIDEO_EDITOR: buildConfig('VIDEO_EDITOR'),
  MARKETING_SPECIALIST: buildConfig('MARKETING_SPECIALIST'),
  EVENT_SPECIALIST: buildConfig('EVENT_SPECIALIST'),
  PRINTING_SPECIALIST: buildConfig('PRINTING_SPECIALIST'),
  MEDIA_BUYER: buildConfig('MEDIA_BUYER'),
  ACCOUNT_MANAGER: buildConfig('ACCOUNT_MANAGER'),
  HR: buildConfig('HR'),
}

function buildConfig(role: EmployeeWorkspaceRole): EmployeeWorkspaceConfig {
  const capabilities = CAPABILITIES_BY_ROLE[role]

  return {
    key: ROLE_WORKSPACE[role].key,
    role,
    label: ROLE_WORKSPACE[role].label,
    description: descriptionForRole(role),
    homePath: '/workspace',
    navigation: navigationForRole(role, capabilities),
    capabilities,
    widgets: widgetsForCapabilities(role, capabilities),
  }
}

export function getWorkspaceForRole(role: string | undefined): EmployeeWorkspaceConfig | null {
  if (!isEmployeeWorkspaceRole(role)) {
    return null
  }

  return WORKSPACE_CONFIG[role] ?? null
}

export function authorizedWidgets(
  config: EmployeeWorkspaceConfig,
  allowedWidgetIds?: string[] | null,
  allowedCapabilities?: string[] | null,
): WorkspaceWidgetDefinition[] {
  const capabilitySet = allowedCapabilities ? new Set(allowedCapabilities) : null
  const idSet = allowedWidgetIds ? new Set(allowedWidgetIds) : null

  return config.widgets.filter((widget) => {
    if (idSet && !idSet.has(widget.id)) {
      return false
    }

    if (capabilitySet && widget.requiredCapability && !capabilitySet.has(widget.requiredCapability)) {
      return false
    }

    return true
  })
}

export function roleLabelFor(role: string | undefined): string | null {
  if (!role) {
    return null
  }

  return EMPLOYEE_ROLE_LABELS[role as EmployeeWorkspaceRole] ?? ROLE_WORKSPACE[role]?.label ?? null
}

export function hasCapability(capabilities: readonly string[], capability: EmployeeCapability): boolean {
  return capabilities.includes(capability)
}
