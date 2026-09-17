import { useEffect, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import {
  disableTaskGoogleSync,
  enableTaskGoogleSync,
  getGoogleCalendarStatus,
  getTaskGoogleSync,
  syncTaskGoogleCalendar,
  type GoogleCalendarStatus,
  type TaskGoogleSyncPayload,
} from '../../services/googleCalendar'
import { describeApiError } from '../../utils/errors'

type Props = {
  taskId: number
  onChanged?: () => void
}

export function TaskGoogleCalendarPanel({ taskId, onChanged }: Props) {
  const [connection, setConnection] = useState<GoogleCalendarStatus | null>(null)
  const [sync, setSync] = useState<TaskGoogleSyncPayload | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function load() {
    setError(null)
    try {
      const [status, taskSync] = await Promise.all([getGoogleCalendarStatus(), getTaskGoogleSync(taskId)])
      setConnection(status.data)
      setSync(taskSync.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل حالة Google Calendar.'))
    }
  }

  useEffect(() => {
    void load()
  }, [taskId])

  async function run(action: () => Promise<unknown>, message: string) {
    if (busy) return
    setBusy(true)
    setError(null)
    setNotice(null)
    try {
      await action()
      setNotice(message)
      await load()
      onChanged?.()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنفيذ العملية.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
      <h2 className="text-sm font-semibold text-[#111318]">Google Calendar</h2>
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      {!connection?.configured ? (
        <p className="text-sm text-slate-600">تكامل Google غير مُعدّ على الخادم حالياً.</p>
      ) : !connection.connected ? (
        <p className="text-sm text-slate-600">
          اربط حساب Google من{' '}
          <a href="/workspace/settings/google-calendar" className="text-[#315CFF] underline">
            إعدادات التقويم
          </a>{' '}
          أولاً.
        </p>
      ) : (
        <>
          <dl className="grid gap-2 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-xs text-slate-500">الحساب</dt>
              <dd>{connection.google_email || '—'}</dd>
            </div>
            <div>
              <dt className="text-xs text-slate-500">حالة المزامنة</dt>
              <dd>{sync?.google_sync_status_label_ar || sync?.google_sync_status || '—'}</dd>
            </div>
            {sync?.google_sync_error ? (
              <div className="sm:col-span-2">
                <dt className="text-xs text-slate-500">خطأ</dt>
                <dd className="text-rose-700">{sync.google_sync_error}</dd>
              </div>
            ) : null}
          </dl>

          <div className="flex flex-wrap gap-2">
            {!sync?.google_sync_enabled ? (
              <button
                type="button"
                disabled={busy}
                className="rounded-lg bg-[#315CFF] px-3 py-2 text-sm text-white disabled:opacity-50"
                onClick={() =>
                  void run(
                    () =>
                      enableTaskGoogleSync(taskId, {
                        reminders: ['MINUTES_15', 'MINUTES_30', 'HOUR_1', 'DAY_1'],
                        google_meet_enabled: Boolean(connection.meet_enabled),
                      }),
                    'تمت مزامنة المهمة مع Google Calendar.',
                  )
                }
              >
                مزامنة مع Google Calendar
              </button>
            ) : (
              <>
                <button
                  type="button"
                  disabled={busy}
                  className="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-50"
                  onClick={() => void run(() => syncTaskGoogleCalendar(taskId), 'تم تحديث الحدث.')}
                >
                  تحديث المزامنة
                </button>
                {sync.google_html_link ? (
                  <a
                    href={sync.google_html_link}
                    target="_blank"
                    rel="noreferrer"
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  >
                    فتح في Google Calendar
                  </a>
                ) : null}
                <button
                  type="button"
                  disabled={busy}
                  className="rounded-lg border border-rose-300 px-3 py-2 text-sm text-rose-700 disabled:opacity-50"
                  onClick={() =>
                    void run(() => disableTaskGoogleSync(taskId, true), 'تم إيقاف المزامنة وحذف الحدث.')
                  }
                >
                  إيقاف المزامنة
                </button>
              </>
            )}
          </div>
        </>
      )}
    </div>
  )
}
