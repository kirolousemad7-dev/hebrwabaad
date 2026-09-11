import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { StatusBadge } from '../ui/StatusBadge'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../workspace/WorkspaceStatus'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { markAllNotificationsRead, markNotificationRead, getNotifications } from '../../services/notifications'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import {
  NOTIFICATION_CATEGORIES,
  NOTIFICATION_COPY,
  isNotificationUnread,
  safeNotificationHref,
  type NotificationCategory,
} from '../../utils/notifications'
import { describeApiError } from '../../utils/errors'
import type { NotificationInboxItem, PlatformNotification } from '../../types/api'

type NotificationInboxProps = {
  fallbackHref: string
  enableCategories?: boolean
}

function isGroup(item: NotificationInboxItem): item is Extract<NotificationInboxItem, { is_group: true }> {
  return item.is_group === true
}

export function NotificationInbox({ fallbackHref, enableCategories = false }: NotificationInboxProps) {
  const toast = useToast()
  const [category, setCategory] = useState<NotificationCategory>('all')
  const [expanded, setExpanded] = useState<Record<string, boolean>>({})

  const query = useMemo(() => {
    const params = new URLSearchParams()
    params.set('per_page', '20')
    params.set('grouped', '1')
    if (enableCategories && category !== 'all') {
      params.set('category', category)
    }
    return `?${params.toString()}`
  }, [category, enableCategories])

  const { state, reload } = useAsyncData(() => getNotifications(query), [query])
  const [marking, setMarking] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  async function onMarkAll() {
    setActionError(null)
    setMarking(true)

    try {
      await markAllNotificationsRead()
      await reload()
      toast.success('تم تعيين كل الإشعارات كمقروءة.')
    } catch (caught) {
      setActionError(describeApiError(caught, NOTIFICATION_COPY.error))
    } finally {
      setMarking(false)
    }
  }

  async function onOpen(id: string) {
    try {
      await markNotificationRead(id)
    } catch {
      // The destination page is still authorized independently.
    }
  }

  function toggleGroup(key: string) {
    setExpanded((current) => ({ ...current, [key]: !current[key] }))
  }

  function renderSingle(notification: PlatformNotification) {
    const unread = isNotificationUnread(notification)
    const href = safeNotificationHref(notification, fallbackHref)

    return (
      <li key={notification.id}>
        <Link
          to={href}
          onClick={() => void onOpen(notification.id)}
          className={`block px-4 py-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 ${
            unread ? 'bg-amber-50' : 'bg-white'
          }`}
        >
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="font-medium">{notification.title}</p>
            <StatusBadge
              status={unread ? 'UNREAD' : 'READ'}
              label={unread ? NOTIFICATION_COPY.unread : NOTIFICATION_COPY.read}
            />
          </div>
          <p className="mt-1 text-sm text-slate-700">{notification.message}</p>
          <p className="mt-1 text-xs text-slate-500">{formatDashboardDateTime(notification.created_at)}</p>
        </Link>
      </li>
    )
  }

  return (
    <section className="space-y-6">
      <header className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">{NOTIFICATION_COPY.title}</h1>
          <p className="mt-1 text-sm text-slate-600">تحديثات الطلبات والرسائل والمهام المرتبطة بحسابك فقط.</p>
        </div>
        <button
          type="button"
          onClick={() => void onMarkAll()}
          disabled={marking || state.status !== 'ready' || state.data.unread_count === 0}
          className="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {NOTIFICATION_COPY.markAll}
        </button>
      </header>

      {enableCategories ? (
        <div className="flex flex-wrap gap-2" role="tablist" aria-label="تصفية الإشعارات">
          {NOTIFICATION_CATEGORIES.map((tab) => {
            const active = category === tab.value
            return (
              <button
                key={tab.value}
                type="button"
                role="tab"
                aria-selected={active}
                onClick={() => setCategory(tab.value)}
                className={`rounded-lg px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 ${
                  active ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700'
                }`}
              >
                {tab.label}
              </button>
            )
          })}
        </div>
      ) : null}

      {state.status === 'loading' ? (
        <div className="space-y-2" aria-busy="true" aria-label={NOTIFICATION_COPY.loading}>
          {[0, 1, 2, 3].map((index) => (
            <div key={index} className="h-16 animate-pulse rounded-xl bg-slate-100" />
          ))}
        </div>
      ) : null}

      {state.status === 'error' ? (
        <WorkspaceErrorState message={NOTIFICATION_COPY.error} onRetry={() => void reload()} />
      ) : null}

      {actionError ? <FeedbackBanner kind="error">{actionError}</FeedbackBanner> : null}

      {state.status === 'ready' && state.data.items.length === 0 ? (
        <WorkspaceEmptyState
          title={NOTIFICATION_COPY.empty}
          description="ستظهر هنا تحديثات الطلبات والرسائل والمهام المرتبطة بحسابك فقط."
        />
      ) : null}

      {state.status === 'ready' && state.data.items.length > 0 ? (
        <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
          {state.data.items.map((item) => {
            if (!isGroup(item)) {
              return renderSingle(item)
            }

            const open = expanded[item.key] === true
            return (
              <li key={item.key} className="bg-white">
                <button
                  type="button"
                  onClick={() => toggleGroup(item.key)}
                  className="flex w-full items-start justify-between gap-3 px-4 py-4 text-right focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  <div>
                    <p className="font-medium">{item.title}</p>
                    <p className="mt-1 text-sm text-slate-600">{item.count} إشعارات مشابهة</p>
                    <p className="mt-1 text-xs text-slate-500">{formatDashboardDateTime(item.latest_at)}</p>
                  </div>
                  <span className="text-sm text-slate-500">{open ? 'إخفاء' : 'عرض'}</span>
                </button>
                {open ? (
                  <ul className="divide-y divide-slate-100 border-t border-slate-100 bg-slate-50">
                    {item.items.map((child) => renderSingle(child))}
                  </ul>
                ) : null}
              </li>
            )
          })}
        </ul>
      ) : null}
    </section>
  )
}
