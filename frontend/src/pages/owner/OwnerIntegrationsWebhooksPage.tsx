import { useEffect, useMemo, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  createWebhook,
  deleteWebhook,
  getWebhookDeliveries,
  getWebhooks,
  testWebhook,
  updateWebhook,
  type OutboundWebhook,
  type WebhookDelivery,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const WEBHOOK_EVENT_OPTIONS = [
  { value: 'order.created', label: 'إنشاء طلب' },
  { value: 'order.status_changed', label: 'تغيير حالة طلب' },
  { value: 'crm.opportunity.won', label: 'ربح فرصة' },
  { value: 'crm.lead.created', label: 'عميل محتمل جديد' },
  { value: 'calendar.task.overdue', label: 'مهمة تقويم متأخرة' },
  { value: 'calendar.task.created', label: 'إنشاء مهمة تقويم' },
  { value: 'calendar.task.completed', label: 'إكمال مهمة تقويم' },
  { value: 'project.deadline_approaching', label: 'اقتراب موعد مشروع' },
  { value: 'project.status_changed', label: 'تغيير حالة مشروع' },
  { value: 'crm.quotation.expiring', label: 'انتهاء عرض سعر' },
  { value: 'printing.required_date_approaching', label: 'اقتراب موعد طباعة' },
  { value: 'printing.status_changed', label: 'تغيير حالة طباعة' },
  { value: 'printing.assigned', label: 'تعيين طباعة' },
  { value: 'printing.completed', label: 'إكمال طباعة' },
  { value: 'printing.overdue', label: 'طباعة متأخرة' },
  { value: 'approval.approved', label: 'اعتماد موافقة' },
  { value: 'approval.rejected', label: 'رفض موافقة' },
] as const

function deliveryStatusLabel(status: string): string {
  if (status === 'delivered' || status === 'success') return 'تم التسليم'
  if (status === 'failed') return 'فشل'
  if (status === 'pending') return 'قيد الانتظار'
  return status
}

export function OwnerIntegrationsWebhooksPage() {
  const [items, setItems] = useState<OutboundWebhook[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [name, setName] = useState('')
  const [url, setUrl] = useState('')
  const [events, setEvents] = useState<string[]>([WEBHOOK_EVENT_OPTIONS[0].value])
  const [saving, setSaving] = useState(false)
  const [createdSecret, setCreatedSecret] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [deliveriesFor, setDeliveriesFor] = useState<OutboundWebhook | null>(null)
  const [deliveries, setDeliveries] = useState<WebhookDelivery[]>([])
  const [deliveriesLoading, setDeliveriesLoading] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getWebhooks()
      setItems(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الـ webhooks.'))
      setItems([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  const eventLabels = useMemo(
    () => Object.fromEntries(WEBHOOK_EVENT_OPTIONS.map((row) => [row.value, row.label])),
    [],
  )

  function toggleEvent(value: string) {
    setEvents((current) =>
      current.includes(value) ? current.filter((entry) => entry !== value) : [...current, value],
    )
  }

  async function handleCreate() {
    if (!name.trim() || !url.trim() || events.length === 0) {
      setError('الاسم والرابط وحدث واحد على الأقل مطلوبة.')
      return
    }
    setSaving(true)
    setError(null)
    try {
      const response = await createWebhook({
        name: name.trim(),
        url: url.trim(),
        events,
        is_active: true,
      })
      setCreatedSecret(response.data.secret ?? null)
      setNotice('تم إنشاء الـ webhook.')
      setShowForm(false)
      setName('')
      setUrl('')
      setEvents([WEBHOOK_EVENT_OPTIONS[0].value])
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إنشاء الـ webhook.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleToggleActive(item: OutboundWebhook) {
    setBusyId(item.id)
    setError(null)
    try {
      await updateWebhook(item.id, { is_active: !item.is_active })
      setNotice(item.is_active ? 'تم إيقاف الـ webhook.' : 'تم تفعيل الـ webhook.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث الحالة.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleTest(item: OutboundWebhook) {
    setBusyId(item.id)
    setError(null)
    try {
      const response = await testWebhook(item.id)
      const delivery = response.data.delivery
      setNotice(`اختبار: ${deliveryStatusLabel(delivery.status)} (محاولات ${delivery.attempt_count})`)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر اختبار الـ webhook.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleDelete(item: OutboundWebhook) {
    if (!window.confirm(`حذف ${item.name}؟`)) return
    setBusyId(item.id)
    try {
      await deleteWebhook(item.id)
      setNotice('تم الحذف.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر الحذف.'))
    } finally {
      setBusyId(null)
    }
  }

  async function openDeliveries(item: OutboundWebhook) {
    setDeliveriesFor(item)
    setDeliveriesLoading(true)
    try {
      const response = await getWebhookDeliveries(item.id)
      setDeliveries(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل التسليمات.'))
      setDeliveries([])
    } finally {
      setDeliveriesLoading(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">التكاملات · Webhooks</h1>
          <p className="mt-1 text-sm text-slate-600">إرسال أحداث التشغيل إلى أنظمة خارجية.</p>
        </div>
        <button
          type="button"
          onClick={() => {
            setShowForm((current) => !current)
            setCreatedSecret(null)
          }}
          className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white"
        >
          {showForm ? 'إخفاء النموذج' : 'Webhook جديد'}
        </button>
      </header>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {createdSecret ? (
        <DashboardSection title="المفتاح السري (يُعرض مرة واحدة)">
          <p className="break-all rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 font-mono text-sm text-amber-950">
            {createdSecret}
          </p>
          <p className="mt-2 text-xs text-slate-500">احفظه الآن — لن يظهر مرة أخرى.</p>
          <button type="button" className="mt-2 text-sm underline" onClick={() => setCreatedSecret(null)}>
            تم الحفظ
          </button>
        </DashboardSection>
      ) : null}

      {showForm ? (
        <DashboardSection title="إنشاء Webhook">
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="text-xs text-slate-500 sm:col-span-1">
              الاسم
              <input value={name} onChange={(event) => setName(event.target.value)} className={`mt-1 ${fieldClass}`} />
            </label>
            <label className="text-xs text-slate-500 sm:col-span-1">
              الرابط (URL)
              <input
                value={url}
                onChange={(event) => setUrl(event.target.value)}
                placeholder="https://..."
                className={`mt-1 ${fieldClass}`}
              />
            </label>
          </div>
          <div className="mt-3">
            <p className="mb-2 text-xs text-slate-500">الأحداث</p>
            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
              {WEBHOOK_EVENT_OPTIONS.map((option) => (
                <label key={option.value} className="flex items-center gap-2 text-sm text-slate-700">
                  <input
                    type="checkbox"
                    checked={events.includes(option.value)}
                    onChange={() => toggleEvent(option.value)}
                  />
                  {option.label}
                </label>
              ))}
            </div>
          </div>
          <button
            type="button"
            disabled={saving}
            onClick={() => void handleCreate()}
            className="mt-4 rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
          >
            إنشاء
          </button>
        </DashboardSection>
      ) : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل التكاملات..." /> : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا webhooks بعد." description="أنشئ تكاملاً لإرسال الأحداث." />
      ) : null}

      {!loading && items.length > 0 ? (
        <ul className="space-y-3">
          {items.map((item) => (
            <li key={item.id} className="rounded-2xl border border-slate-200 bg-white p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-semibold text-slate-900">{item.name}</h2>
                    <span
                      className={`rounded-full px-2 py-0.5 text-[11px] ${
                        item.is_active
                          ? 'border border-emerald-200 bg-emerald-50 text-emerald-800'
                          : 'border border-slate-200 bg-slate-50 text-slate-600'
                      }`}
                    >
                      {item.is_active ? 'نشط' : 'متوقف'}
                    </span>
                  </div>
                  <p className="mt-1 break-all text-xs text-slate-500">{item.url}</p>
                  <p className="mt-1 text-xs text-slate-600">
                    {(item.events ?? []).map((event) => eventLabels[event] ?? event).join(' · ') || '—'}
                  </p>
                  {item.secret_hint ? (
                    <p className="mt-1 text-[11px] text-slate-400">سر: …{item.secret_hint}</p>
                  ) : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    disabled={busyId === item.id}
                    onClick={() => void handleTest(item)}
                    className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs disabled:opacity-50"
                  >
                    اختبار
                  </button>
                  <button
                    type="button"
                    onClick={() => void openDeliveries(item)}
                    className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                  >
                    التسليمات
                  </button>
                  <button
                    type="button"
                    disabled={busyId === item.id}
                    onClick={() => void handleToggleActive(item)}
                    className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs disabled:opacity-50"
                  >
                    {item.is_active ? 'إيقاف' : 'تفعيل'}
                  </button>
                  <button
                    type="button"
                    disabled={busyId === item.id}
                    onClick={() => void handleDelete(item)}
                    className="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-800 disabled:opacity-50"
                  >
                    حذف
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      ) : null}

      {deliveriesFor ? (
        <div className="fixed inset-0 z-40 flex justify-end bg-slate-900/40" role="dialog">
          <aside className="h-full w-full max-w-md overflow-y-auto border-s border-slate-200 bg-white p-4 shadow-xl">
            <div className="flex items-start justify-between gap-2">
              <div>
                <h2 className="text-lg font-semibold text-slate-900">التسليمات</h2>
                <p className="text-sm text-slate-600">{deliveriesFor.name}</p>
              </div>
              <button type="button" className="text-sm underline" onClick={() => setDeliveriesFor(null)}>
                إغلاق
              </button>
            </div>
            {deliveriesLoading ? <p className="mt-4 text-sm text-slate-500">جاري التحميل...</p> : null}
            {!deliveriesLoading && deliveries.length === 0 ? (
              <p className="mt-4 text-sm text-slate-500">لا تسليمات.</p>
            ) : (
              <ul className="mt-4 space-y-2">
                {deliveries.map((row) => (
                  <li key={row.id} className="rounded-xl border border-slate-100 px-3 py-2 text-sm">
                    <p className="font-medium text-slate-800">
                      {row.event} · {deliveryStatusLabel(row.status)}
                    </p>
                    <p className="text-xs text-slate-500">
                      محاولات {row.attempt_count}
                      {row.response_status != null ? ` · HTTP ${row.response_status}` : ''}
                    </p>
                    {row.response_summary ? (
                      <p className="mt-1 line-clamp-3 text-xs text-slate-600">{row.response_summary}</p>
                    ) : null}
                    <p className="mt-1 text-[11px] text-slate-400">
                      {row.delivered_at ?? row.failed_at ?? row.created_at ?? ''}
                    </p>
                  </li>
                ))}
              </ul>
            )}
          </aside>
        </div>
      ) : null}
    </section>
  )
}
