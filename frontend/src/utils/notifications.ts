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

const LIVE_HREF_PREFIXES = [
  '/dashboard/orders/',
  '/dashboard/messages/',
  '/workspace/tasks/',
  '/workspace/support/',
  '/owner/support/',
  '/owner/payments/',
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

  if (!href || !href.startsWith('/')) {
    return fallback
  }

  return LIVE_HREF_PREFIXES.some((prefix) => href.startsWith(prefix)) ? href : fallback
}

export function notificationAriaLabel(unreadCount: number): string {
  if (unreadCount === 0) {
    return 'الإشعارات'
  }

  return `الإشعارات، ${unreadCount} غير مقروءة`
}
