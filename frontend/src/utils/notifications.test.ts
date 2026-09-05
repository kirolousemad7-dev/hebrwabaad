import { describe, expect, it } from 'vitest'
import {
  NOTIFICATION_COPY,
  isNotificationUnread,
  notificationAriaLabel,
  notificationsPathForRole,
  safeNotificationHref,
} from './notifications'
import type { PlatformNotification } from '../types/api'

const sample: PlatformNotification = {
  id: 'n-1',
  type: 'order_status_updated',
  title: 'تحديث على طلبك',
  message: 'طلب HEBR-ORD-000021 أصبح الآن قيد التنفيذ.',
  href: '/dashboard/orders/21',
  read_at: null,
  created_at: '2026-09-03T00:00:00+00:00',
  data: { order_id: 21, order_reference: 'HEBR-ORD-000021' },
}

describe('notifications', () => {
  it('distinguishes unread from read and keeps live hrefs only', () => {
    expect(isNotificationUnread(sample)).toBe(true)
    expect(isNotificationUnread({ ...sample, read_at: '2026-09-03T01:00:00+00:00' })).toBe(false)
    expect(safeNotificationHref(sample, '/dashboard/notifications')).toBe('/dashboard/orders/21')
    expect(safeNotificationHref({ ...sample, href: '/owner/secret' }, '/dashboard/notifications')).toBe(
      '/dashboard/notifications',
    )
    expect(safeNotificationHref({ ...sample, href: '/owner/payments/4' }, '/owner/notifications')).toBe(
      '/owner/payments/4',
    )
    expect(NOTIFICATION_COPY.empty).toBe('لا توجد إشعارات جديدة.')
    expect(NOTIFICATION_COPY.error).toBe('تعذر تحميل الإشعارات.')
  })

  it('routes view-all by role and exposes unread count in the accessible label', () => {
    expect(notificationsPathForRole('CUSTOMER')).toBe('/dashboard/notifications')
    expect(notificationsPathForRole('OWNER')).toBe('/owner/notifications')
    expect(notificationsPathForRole('ACCOUNT_MANAGER')).toBe('/workspace/notifications')
    expect(notificationAriaLabel(0)).toBe('الإشعارات')
    expect(notificationAriaLabel(3)).toContain('3')
    expect(notificationAriaLabel(3)).toContain('غير مقروءة')
  })
})
