import { Link } from 'react-router-dom'
import { NotificationInbox } from '../../components/notifications/NotificationInbox'

export function OwnerNotificationsPage() {
  return (
    <section className="space-y-4">
      <div className="flex justify-end">
        <Link
          to="/owner/notification-preferences"
          className="text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          تفضيلات الإشعارات
        </Link>
      </div>
      <NotificationInbox fallbackHref="/owner" enableCategories />
    </section>
  )
}
