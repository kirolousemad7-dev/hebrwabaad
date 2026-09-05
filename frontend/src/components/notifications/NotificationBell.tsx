import { useEffect, useId, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { getNotifications, getUnreadNotificationCount, markNotificationRead } from '../../services/notifications'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import {
  NOTIFICATION_COPY,
  isNotificationUnread,
  notificationAriaLabel,
  notificationsPathForRole,
  safeNotificationHref,
} from '../../utils/notifications'
import type { PlatformNotification } from '../../types/api'

export function NotificationBell() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const viewAll = notificationsPathForRole(user?.role)
  const menuId = useId()
  const rootRef = useRef<HTMLDivElement>(null)
  const [open, setOpen] = useState(false)
  const [unread, setUnread] = useState(0)
  const [items, setItems] = useState<PlatformNotification[] | null>(null)
  const [status, setStatus] = useState<'idle' | 'loading' | 'error'>('idle')

  useEffect(() => {
    let cancelled = false

    void getUnreadNotificationCount()
      .then((response) => {
        if (!cancelled) {
          setUnread(response.data.unread_count)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setUnread(0)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    function onPointerDown(event: PointerEvent) {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setOpen(false)
      }
    }

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [])

  async function toggleOpen() {
    const next = !open
    setOpen(next)

    if (!next || items !== null) {
      return
    }

    setStatus('loading')
    try {
      const response = await getNotifications('?per_page=6')
      setItems(response.data.items)
      setUnread(response.data.unread_count)
      setStatus('idle')
    } catch {
      setStatus('error')
    }
  }

  async function openNotification(notification: PlatformNotification) {
    if (isNotificationUnread(notification)) {
      try {
        await markNotificationRead(notification.id)
        setUnread((count) => Math.max(0, count - 1))
        setItems((current) =>
          current?.map((item) =>
            item.id === notification.id ? { ...item, read_at: new Date().toISOString() } : item,
          ) ?? null,
        )
      } catch {
        // Navigation still proceeds; the list page can refresh read state.
      }
    }

    setOpen(false)
    navigate(safeNotificationHref(notification, viewAll))
  }

  return (
    <div className="relative" ref={rootRef}>
      <button
        type="button"
        aria-expanded={open}
        aria-controls={menuId}
        aria-label={notificationAriaLabel(unread)}
        onClick={() => void toggleOpen()}
        className="relative rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        الإشعارات
        {unread > 0 ? (
          <span className="absolute -top-1.5 -start-1.5 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-600 px-1 text-[11px] font-medium text-white">
            {unread.toLocaleString('ar-SA')}
            <span className="sr-only"> غير مقروءة</span>
          </span>
        ) : null}
      </button>

      {open ? (
        <div
          id={menuId}
          role="menu"
          className="absolute end-0 z-40 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        >
          {status === 'loading' ? (
            <p className="p-4 text-sm text-slate-500" aria-live="polite">
              {NOTIFICATION_COPY.loading}
            </p>
          ) : null}
          {status === 'error' ? (
            <p className="p-4 text-sm text-red-700" role="alert">
              {NOTIFICATION_COPY.error}
            </p>
          ) : null}
          {status === 'idle' && items?.length === 0 ? (
            <p className="p-4 text-sm text-slate-600">{NOTIFICATION_COPY.empty}</p>
          ) : null}
          {status === 'idle' && items && items.length > 0 ? (
            <ul className="max-h-80 divide-y divide-slate-100 overflow-y-auto">
              {items.map((notification) => {
                const unreadItem = isNotificationUnread(notification)

                return (
                  <li key={notification.id}>
                    <button
                      type="button"
                      role="menuitem"
                      onClick={() => void openNotification(notification)}
                      className={`block w-full px-4 py-3 text-start focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 ${
                        unreadItem ? 'bg-amber-50' : 'bg-white'
                      }`}
                    >
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-medium">{notification.title}</p>
                        <span className="text-xs text-slate-500">
                          {unreadItem ? NOTIFICATION_COPY.unread : NOTIFICATION_COPY.read}
                        </span>
                      </div>
                      <p className="mt-1 text-sm text-slate-700">{notification.message}</p>
                      <p className="mt-1 text-xs text-slate-500">{formatDashboardDateTime(notification.created_at)}</p>
                    </button>
                  </li>
                )
              })}
            </ul>
          ) : null}
          <Link
            to={viewAll}
            className="block border-t border-slate-100 px-4 py-3 text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            onClick={() => setOpen(false)}
          >
            {NOTIFICATION_COPY.viewAll}
          </Link>
        </div>
      ) : null}
    </div>
  )
}
