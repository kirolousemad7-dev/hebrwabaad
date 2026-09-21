import type { PlatformNotification } from '../types/api'

export const NOTIFICATION_COPY = {
  title: 'الإشعارات',
  empty: 'لا توجد إشعارات جديدة.',
  error: 'تعذر تحميل الإشعارات.',
  loading: 'جاري تحميل الإشعارات...',
  markAll: 'تعيين الكل كمقروء',
  viewAll: 'عرض كل الإشعارات',
  unread: 'غير مقروء',
  read: 'مقروء',
} as const

export type NotificationCategory =
  | 'all'
  | 'tasks'
  | 'calendar'
  | 'projects'
  | 'printing'
  | 'approvals'
  | 'automation'
  | 'crm'
  | 'alerts'

export const NOTIFICATION_CATEGORIES: Array<{ value: NotificationCategory; label: string }> = [
  { value: 'all', label: 'الكل' },
  { value: 'tasks', label: 'المهام' },
  { value: 'calendar', label: 'التقويم' },
  { value: 'projects', label: 'المشاريع' },
  { value: 'printing', label: 'الطباعة' },
  { value: 'approvals', label: 'الموافقات' },
  { value: 'automation', label: 'الأتمتة' },
  { value: 'crm', label: 'إدارة العملاء' },
  { value: 'alerts', label: 'تنبيهات' },
]

const LIVE_HREF_PREFIXES = [
  '/dashboard',
  '/workspace',
  '/owner',
  '/crm',
  '/supplier',
  '/operations',
  '/printing-requests',
] as const

export function notificationsPathForRole(role: string | undefined): string {
  if (role === 'CUSTOMER') {
    return '/dashboard/notifications'
  }

  if (role === 'OWNER') {
    return '/owner/notifications'
  }

  return '/workspace/notifications'
}

export function isNotificationUnread(notification: PlatformNotification): boolean {
  return notification.read_at === null
}

export function safeNotificationHref(notification: PlatformNotification, fallback: string): string {
  const href = notification.href

  if (!href || !href.startsWith('/') || href.startsWith('//')) {
    return fallback
  }

  const path = href.split('?')[0] ?? href
  return LIVE_HREF_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`))
    ? href
    : fallback
}

export function notificationAriaLabel(unreadCount: number): string {
  if (unreadCount === 0) {
    return 'الإشعارات'
  }

  return `الإشعارات، ${unreadCount} غير مقروءة`
}
