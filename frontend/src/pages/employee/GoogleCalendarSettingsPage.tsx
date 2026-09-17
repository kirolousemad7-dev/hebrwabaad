import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  connectGoogleCalendar,
  disconnectGoogleCalendar,
  getGoogleCalendarStatus,
  updateGoogleCalendarSettings,
  type GoogleCalendarStatus,
} from '../../services/googleCalendar'
import { describeApiError } from '../../utils/errors'

export function GoogleCalendarSettingsPage() {
  const [searchParams] = useSearchParams()
  const [status, setStatus] = useState<GoogleCalendarStatus | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function load() {
    try {
      const response = await getGoogleCalendarStatus()
      setStatus(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل حالة الربط.'))
    }
  }

  useEffect(() => {
    void load()
    if (searchParams.get('connected') === '1') {
      setNotice('تم ربط حساب Google بنجاح.')
    }
  }, [searchParams])

  async function handleConnect() {
    setBusy(true)
    setError(null)
    try {
      const response = await connectGoogleCalendar()
      window.location.assign(response.data.authorize_url)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر بدء ربط Google.'))
      setBusy(false)
    }
  }

  async function handleDisconnect() {
    setBusy(true)
    setError(null)
    try {
      await disconnectGoogleCalendar()
      setNotice('تم فصل حساب Google.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر فصل الحساب.'))
    } finally {
      setBusy(false)
    }
  }

  async function toggleMeet(enabled: boolean) {
    setBusy(true)
    try {
      const response = await updateGoogleCalendarSettings({ meet_enabled: enabled })
      setStatus({ ...response.data, configured: status?.configured })
      setNotice('تم حفظ الإعدادات.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الإعدادات.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="mx-auto max-w-2xl space-y-6">
      <header>
        <Link to="/workspace/tasks" className="text-sm text-[#315CFF] underline">
          ← المهام
        </Link>
        <h1 className="mt-2 text-2xl font-semibold text-[#111318]">Google Calendar</h1>
        <p className="mt-1 text-sm text-slate-600">
          اربط حسابك لمزامنة المهام مع تقويم Google. الرموز تُحفظ مشفّرة على الخادم ولا تُعرض في الواجهة.
        </p>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
        {!status?.configured ? (
          <p className="text-sm text-slate-600">أضف GOOGLE_CLIENT_ID / SECRET / REDIRECT_URI في إعدادات الخادم.</p>
        ) : status.connected ? (
          <>
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-slate-500">البريد</dt>
                <dd className="font-medium">{status.google_email}</dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">آخر مزامنة</dt>
                <dd>{status.last_synced_at ? new Date(status.last_synced_at).toLocaleString('ar') : '—'}</dd>
              </div>
            </dl>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={Boolean(status.meet_enabled)}
                disabled={busy}
                onChange={(event) => void toggleMeet(event.target.checked)}
              />
              تفعيل Google Meet عند إنشاء الأحداث
            </label>
            <button
              type="button"
              disabled={busy}
              className="rounded-lg border border-rose-300 px-4 py-2 text-sm text-rose-700 disabled:opacity-50"
              onClick={() => void handleDisconnect()}
            >
              فصل الحساب
            </button>
          </>
        ) : (
          <button
            type="button"
            disabled={busy}
            className="rounded-lg bg-[#315CFF] px-4 py-2 text-sm text-white disabled:opacity-50"
            onClick={() => void handleConnect()}
          >
            ربط حساب Google
          </button>
        )}
      </div>
    </section>
  )
}
