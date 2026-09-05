import type { NotificationListData, PlatformNotification } from '../types/api'
import { apiGet, apiPatch } from './api'

export function getNotifications(query = '') {
  return apiGet<NotificationListData>(`/api/notifications${query}`)
}

export function getUnreadNotificationCount() {
  return apiGet<{ unread_count: number }>('/api/notifications/unread-count')
}

export function markNotificationRead(id: string) {
  return apiPatch<PlatformNotification>(`/api/notifications/${id}/read`)
}

export function markAllNotificationsRead() {
  return apiPatch<{ updated: number; unread_count: number }>('/api/notifications/read-all')
}
