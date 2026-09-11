import { useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  createInboundWebhook,
  deleteInboundWebhook,
  getInboundWebhookReceipts,
  getInboundWebhooks,
  updateInboundWebhook,
  type InboundWebhookIntegration,
  type InboundWebhookReceipt,
} from '../../services/printingQuotations'
import { API_BASE_URL } from '../../services/api'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function OwnerInboundWebhooksPage() {
  const [items, setItems] = useState<InboundWebhookIntegration[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [name, setName] = useState('')
  const [saving, setSaving] = useState(false)
  const [createdSecret, setCreatedSecret] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [receiptsFor, setReceiptsFor] = useState<InboundWebhookIntegration | null>(null)
  const [receipts, setReceipts] = useState<InboundWebhookReceipt[]>([])
  const [receiptsLoading, setReceiptsLoading] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getInboundWebhooks()
      setItems(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل inbound webhooks.'))
      setItems([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function handleCreate() {
    if (!name.trim()) {
      setError('الاسم مطلوب.')
      return
    }
    setSaving(true)
    setError(null)
    setCreatedSecret(null)
    try {
      const response = await createInboundWebhook({
        name: name.trim(),
        integration_type: 'generic_hmac',
        is_active: true,
      })
      setCreatedSecret(response.data.secret ?? null)
      setName('')
      setShowForm(false)
      setNotice('تم إنشاء التكامل. انسخ السر الآن — يظهر مرة واحدة فقط.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إنشاء التكامل.'))
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(item: InboundWebhookIntegration) {
    setBusyId(item.id)
    setError(null)
    try {
      await updateInboundWebhook(item.id, { is_active: !item.is_active })
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث التكامل.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleDelete(item: InboundWebhookIntegration) {
    if (!window.confirm(`حذف «${item.name}»؟`)) return
    setBusyId(item.id)
    setError(null)
    try {
      await deleteInboundWebhook(item.id)
      setNotice('تم الحذف.')
      if (receiptsFor?.id === item.id) {
        setReceiptsFor(null)
        setReceipts([])
      }
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر الحذف.'))
    } finally {
      setBusyId(null)
    }
  }

  async function loadReceipts(item: InboundWebhookIntegration) {
    setReceiptsFor(item)
    setReceiptsLoading(true)
    try {
      const response = await getInboundWebhookReceipts(item.id)
      setReceipts(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الإيصالات.'))
      setReceipts([])
    } finally {
      setReceiptsLoading(false)
    }
  }

  function endpointUrl(item: InboundWebhookIntegration): string {
    const path = item.endpoint || `/api/webhooks/inbound/${item.id}`
    if (path.startsWith('http')) return path
    return `${API_BASE_URL}${path.startsWith('/') ? path : `/${path}`}`
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Inbound Webhooks</h1>
          <p className="mt-1 text-sm text-slate-600">
            تكاملات موقعة (HMAC) لاستقبال أحداث مثل تأكيد الدفع. PayTabs يبقى على مساره المنفصل.
          </p>
        </div>
        <button
          type="button"
          className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white"
          onClick={() => setShowForm((value) => !value)}
        >
          {showForm ? 'إلغاء' : 'تكامل جديد'}
        </button>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      {createdSecret ? (
        <div className="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm">
          <p className="font-medium text-amber-950">السر (مرة واحدة):</p>
          <p className="mt-1 break-all font-mono text-xs" dir="ltr">
            {createdSecret}
          </p>
        </div>
      ) : null}

      {showForm ? (
        <DashboardSection title="إنشاء تكامل">
          <div className="grid max-w-lg gap-3">
            <label className="space-y-1 text-sm">
              <span>الاسم</span>
              <input className={fieldClass} value={name} onChange={(event) => setName(event.target.value)} />
            </label>
            <p className="text-xs text-slate-500">النوع: generic_hmac (الأحداث: ping، payment.confirm)</p>
            <button
              type="button"
              disabled={saving}
              className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
              onClick={() => void handleCreate()}
            >
              إنشاء
            </button>
          </div>
        </DashboardSection>
      ) : null}

      {loading ? <DashboardPanelSkeleton label="جاري التحميل..." /> : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا تكاملات بعد" description="أنشئ تكاملاً لاستقبال webhooks موقعة." />
      ) : null}

      {!loading && items.length > 0 ? (
        <DashboardSection title="التكاملات">
          <ul className="space-y-3">
            {items.map((item) => (
              <li key={item.id} className="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="font-medium text-slate-900">{item.name}</p>
                    <p className="text-xs text-slate-500">
                      {item.integration_type} · {item.is_active ? 'نشط' : 'متوقف'} · hint …{item.secret_hint}
                    </p>
                    <p className="mt-1 break-all font-mono text-[11px] text-slate-600" dir="ltr">
                      {endpointUrl(item)}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <button
                      type="button"
                      disabled={busyId === item.id}
                      className="rounded border px-2 py-1 text-xs disabled:opacity-50"
                      onClick={() => void toggleActive(item)}
                    >
                      {item.is_active ? 'إيقاف' : 'تفعيل'}
                    </button>
                    <button
                      type="button"
                      className="rounded border px-2 py-1 text-xs"
                      onClick={() => void loadReceipts(item)}
                    >
                      الإيصالات
                    </button>
                    <button
                      type="button"
                      disabled={busyId === item.id}
                      className="rounded border border-red-300 px-2 py-1 text-xs text-red-800 disabled:opacity-50"
                      onClick={() => void handleDelete(item)}
                    >
                      حذف
                    </button>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </DashboardSection>
      ) : null}

      {receiptsFor ? (
        <DashboardSection title={`إيصالات: ${receiptsFor.name}`}>
          {receiptsLoading ? <p className="text-sm text-slate-500">جاري التحميل...</p> : null}
          {!receiptsLoading && receipts.length === 0 ? (
            <p className="text-sm text-slate-500">لا إيصالات.</p>
          ) : null}
          <ul className="space-y-2">
            {receipts.map((row) => (
              <li key={row.id} className="rounded-lg border border-slate-100 px-3 py-2 text-sm">
                <span className="font-medium">{row.event || '—'}</span>
                <span className="text-slate-500"> · {row.status || '—'}</span>
                {row.delivery_id ? (
                  <span className="ms-2 font-mono text-xs text-slate-400" dir="ltr">
                    {row.delivery_id}
                  </span>
                ) : null}
              </li>
            ))}
          </ul>
        </DashboardSection>
      ) : null}
    </section>
  )
}
