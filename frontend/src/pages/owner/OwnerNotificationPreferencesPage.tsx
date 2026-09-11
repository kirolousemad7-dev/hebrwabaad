import { Link } from 'react-router-dom'
import { useEffect, useState } from 'react'
import {
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  getNotificationPreferences,
  updateNotificationPreferences,
  type NotificationPreferences,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const PREFERENCE_LABELS: Array<{
  key: keyof Pick<
    NotificationPreferences,
    | 'calendar_assignments'
    | 'task_reminders'
    | 'overdue_alerts'
    | 'mentions'
    | 'automation_notifications'
    | 'project_alerts'
    | 'daily_digest'
    | 'approval_requests'
    | 'printing_alerts'
    | 'crm_alerts'
  >
  label: string
  hint: string
}> = [
  { key: 'calendar_assignments', label: 'تعيينات التقويم', hint: 'عند تعيينك على عنصر تقويم' },
  { key: 'task_reminders', label: 'تذكيرات المهام', hint: 'تذكيرات قبل موعد المهمة' },
  { key: 'overdue_alerts', label: 'تنبيهات التأخير', hint: 'عند تأخر المهام' },
  { key: 'mentions', label: 'الإشارات', hint: 'عند ذكر اسمك في التعليقات' },
  { key: 'automation_notifications', label: 'إشعارات الأتمتة', hint: 'نتائج القواعد التلقائية' },
  { key: 'project_alerts', label: 'تنبيهات المشاريع', hint: 'صحة المشاريع والمواعيد' },
  { key: 'daily_digest', label: 'الملخص اليومي', hint: 'ملخص يومي للنشاط' },
  { key: 'approval_requests', label: 'طلبات الموافقة', hint: 'عند وصول موافقة تحتاج قرارك' },
  { key: 'printing_alerts', label: 'تنبيهات الطباعة', hint: 'تصعيدات وطلبات الطباعة' },
  { key: 'crm_alerts', label: 'تنبيهات CRM', hint: 'العملاء المحتملون والمتابعات' },
]

export function OwnerNotificationPreferencesPage() {
  const [prefs, setPrefs] = useState<NotificationPreferences | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getNotificationPreferences()
      setPrefs(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل تفضيلات الإشعارات.'))
      setPrefs(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function toggle(key: (typeof PREFERENCE_LABELS)[number]['key']) {
    if (!prefs || saving) return
    const next = { ...prefs, [key]: !prefs[key] }
    setPrefs(next)
    setSaving(true)
    setError(null)
    try {
      const response = await updateNotificationPreferences({ [key]: next[key] })
      setPrefs(response.data)
      setNotice('تم حفظ التفضيل.')
    } catch (caught) {
      setPrefs(prefs)
      setError(describeApiError(caught, 'تعذر حفظ التفضيل.'))
    } finally {
      setSaving(false)
    }
  }

  async function saveQuietHours(patch: Partial<NotificationPreferences>) {
    if (!prefs || saving) return
    const next = { ...prefs, ...patch }
    setPrefs(next)
    setSaving(true)
    setError(null)
    try {
      const response = await updateNotificationPreferences(patch)
      setPrefs(response.data)
      setNotice('تم حفظ ساعات الهدوء.')
    } catch (caught) {
      setPrefs(prefs)
      setError(describeApiError(caught, 'تعذر حفظ ساعات الهدوء.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="space-y-1">
        <Link to="/owner/notifications" className="text-sm text-slate-600 underline">
          العودة للإشعارات
        </Link>
        <h1 className="text-2xl font-semibold text-slate-900">تفضيلات الإشعارات</h1>
        <p className="text-sm text-slate-600">اختر أنواع التنبيهات التشغيلية التي تريد استلامها.</p>
      </header>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {loading ? <DashboardPanelSkeleton label="جاري تحميل التفضيلات..." /> : null}
      {!loading && error && !prefs ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}

      {!loading && prefs ? (
        <>
          <DashboardSection title="القنوات التشغيلية">
            <ul className="space-y-3">
              {PREFERENCE_LABELS.map((entry) => (
                <li
                  key={entry.key}
                  className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 px-4 py-3"
                >
                  <div>
                    <p className="font-medium text-slate-900">{entry.label}</p>
                    <p className="text-xs text-slate-500">{entry.hint}</p>
                  </div>
                  <button
                    type="button"
                    role="switch"
                    aria-checked={prefs[entry.key]}
                    disabled={saving}
                    onClick={() => void toggle(entry.key)}
                    className={`relative h-7 w-12 rounded-full transition ${
                      prefs[entry.key] ? 'bg-amber-500' : 'bg-slate-300'
                    } disabled:opacity-50`}
                  >
                    <span
                      className={`absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition ${
                        prefs[entry.key] ? 'start-5' : 'start-0.5'
                      }`}
                    />
                  </button>
                </li>
              ))}
            </ul>
          </DashboardSection>

          <DashboardSection title="ساعات الهدوء" description="تأجيل الإشعارات غير العاجلة خلال فترة محددة.">
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 px-4 py-3">
              <div>
                <p className="font-medium text-slate-900">تفعيل ساعات الهدوء</p>
                <p className="text-xs text-slate-500">إيقاف التنبيهات خلال الفترة المحددة</p>
              </div>
              <button
                type="button"
                role="switch"
                aria-checked={prefs.quiet_hours_enabled}
                disabled={saving}
                onClick={() => void saveQuietHours({ quiet_hours_enabled: !prefs.quiet_hours_enabled })}
                className={`relative h-7 w-12 rounded-full transition ${
                  prefs.quiet_hours_enabled ? 'bg-amber-500' : 'bg-slate-300'
                } disabled:opacity-50`}
              >
                <span
                  className={`absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition ${
                    prefs.quiet_hours_enabled ? 'start-5' : 'start-0.5'
                  }`}
                />
              </button>
            </div>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              <label className="text-sm">
                من
                <input
                  type="time"
                  value={prefs.quiet_hours_start ?? ''}
                  disabled={saving || !prefs.quiet_hours_enabled}
                  onChange={(event) =>
                    void saveQuietHours({ quiet_hours_start: event.target.value || null })
                  }
                  className={`mt-1 ${fieldClass}`}
                />
              </label>
              <label className="text-sm">
                إلى
                <input
                  type="time"
                  value={prefs.quiet_hours_end ?? ''}
                  disabled={saving || !prefs.quiet_hours_enabled}
                  onChange={(event) =>
                    void saveQuietHours({ quiet_hours_end: event.target.value || null })
                  }
                  className={`mt-1 ${fieldClass}`}
                />
              </label>
            </div>
          </DashboardSection>
        </>
      ) : null}
    </section>
  )
}
