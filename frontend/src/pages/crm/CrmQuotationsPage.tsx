import { FormEvent, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import {
  approveCrmQuotation,
  createCrmQuotation,
  downloadCrmQuotationPdf,
  getCrmLeads,
  getCrmQuotation,
  getCrmQuotations,
  rejectCrmQuotation,
  sendCrmQuotation,
  type CrmLead,
  type CrmQuotation,
  type CrmQuotationListData,
} from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { CRM_QUOTATION_STATUS_LABELS, isCrmManager } from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function CrmQuotationsPage() {
  const toast = useToast()
  const { user } = useAuth()
  const manager = isCrmManager(user?.role)
  const [searchParams, setSearchParams] = useSearchParams()
  const selectedId = searchParams.get('id') ? Number(searchParams.get('id')) : null
  const [list, setList] = useState<CrmQuotationListData | null>(null)
  const [detail, setDetail] = useState<CrmQuotation | null>(null)
  const [leads, setLeads] = useState<CrmLead[]>([])
  const [loading, setLoading] = useState(true)
  const [detailLoading, setDetailLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [page, setPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState('')
  const [busyAction, setBusyAction] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmQuotations({
        page,
        per_page: 15,
        status: statusFilter || undefined,
      })
      setList(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل عروض الأسعار.'))
    } finally {
      setLoading(false)
    }
  }

  async function loadDetail(id: number) {
    setDetailLoading(true)
    try {
      const response = await getCrmQuotation(id)
      setDetail(response.data)
    } catch (caught) {
      toast.error(describeApiError(caught, 'تعذر تحميل العرض.'))
      setDetail(null)
    } finally {
      setDetailLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, statusFilter])

  useEffect(() => {
    void getCrmLeads({ per_page: 50 })
      .then((response) => setLeads(response.data.items))
      .catch(() => setLeads([]))
  }, [])

  useEffect(() => {
    if (selectedId && Number.isFinite(selectedId)) void loadDetail(selectedId)
    else setDetail(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedId])

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (saving) return
    const form = new FormData(event.currentTarget)
    setSaving(true)
    setFormError(null)
    try {
      const response = await createCrmQuotation({
        lead_id: Number(form.get('lead_id')),
        notes: String(form.get('notes') || '').trim() || undefined,
        valid_until: String(form.get('valid_until') || '') || undefined,
        discount_amount: form.get('discount_amount') ? Number(form.get('discount_amount')) : undefined,
        items: [{
          description: String(form.get('description') || '').trim(),
          quantity: Number(form.get('quantity') || 1),
          unit_price: Number(form.get('unit_price') || 0),
        }],
      })
      setCreating(false)
      toast.success(
        response.data.status === 'PENDING_APPROVAL'
          ? 'تم إنشاء العرض وبانتظار موافقة الخصم.'
          : 'تم إنشاء عرض السعر.',
      )
      setSearchParams({ id: String(response.data.id) })
      await load()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر إنشاء عرض السعر.'))
    } finally {
      setSaving(false)
    }
  }

  async function runQuotationAction(action: () => Promise<void>, message: string) {
    if (!detail || busyAction) return
    setBusyAction(true)
    try {
      await action()
      toast.success(message)
      await Promise.all([load(), loadDetail(detail.id)])
    } catch (caught) {
      toast.error(describeApiError(caught, 'تعذر تنفيذ الإجراء.'))
    } finally {
      setBusyAction(false)
    }
  }

  const items = list?.items ?? []
  const meta = list?.meta

  return (
    <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
      <DashboardSection
        title="عروض الأسعار"
        description="قائمة العروض مع الموافقة والإرسال وتحميل PDF."
        action={
          <button
            type="button"
            className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white"
            onClick={() => {
              setFormError(null)
              setCreating(true)
            }}
          >
            عرض جديد
          </button>
        }
      >
        <select value={statusFilter} onChange={(event) => { setStatusFilter(event.target.value); setPage(1) }} className={`${fieldClass} max-w-xs`}>
          <option value="">كل الحالات</option>
          {Object.entries(CRM_QUOTATION_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>{label}</option>
          ))}
        </select>

        {creating ? (
          <form onSubmit={(event) => void handleCreate(event)} className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2">
            {formError ? (
              <div className="sm:col-span-2">
                <FeedbackBanner kind="error">{formError}</FeedbackBanner>
              </div>
            ) : null}
            <select required name="lead_id" className={fieldClass}>
              <option value="">اختر العميل المحتمل</option>
              {leads.map((lead) => (
                <option key={lead.id} value={lead.id}>{lead.full_name} ({lead.reference})</option>
              ))}
            </select>
            <input name="valid_until" type="date" className={fieldClass} />
            <input required name="description" placeholder="وصف البند *" className={`${fieldClass} sm:col-span-2`} />
            <input required name="quantity" type="number" min="0.01" step="0.01" defaultValue="1" className={fieldClass} />
            <input required name="unit_price" type="number" min="0" step="0.01" placeholder="سعر الوحدة *" className={fieldClass} />
            <input name="discount_amount" type="number" min="0" step="0.01" placeholder="خصم (قد يحتاج موافقة)" className={fieldClass} />
            <textarea name="notes" rows={2} placeholder="ملاحظات" className={`${fieldClass} sm:col-span-2`} />
            <div className="flex gap-2 sm:col-span-2">
              <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
                {saving ? 'جاري الحفظ...' : 'إنشاء'}
              </button>
              <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setCreating(false)}>إلغاء</button>
            </div>
          </form>
        ) : null}

        {loading ? <DashboardPanelSkeleton label="جاري تحميل عروض الأسعار..." /> : null}
        {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
        {!loading && !error && items.length === 0 ? (
          <DashboardEmptyState title="لا توجد عروض أسعار." description="أنشئ عرضاً من هنا أو من صفحة العميل المحتمل." />
        ) : null}

        {!loading && !error && items.length > 0 ? (
          <>
            <div className="overflow-x-auto rounded-2xl border bg-white">
              <table className="min-w-full text-sm">
                <thead className="bg-slate-50 text-right">
                  <tr>
                    <th className="px-3 py-2">الرقم</th>
                    <th className="px-3 py-2">العميل</th>
                    <th className="px-3 py-2">الحالة</th>
                    <th className="px-3 py-2">الإجمالي</th>
                    <th className="px-3 py-2">إجراء</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((quotation: CrmQuotation) => (
                    <tr key={quotation.id} className={`border-t ${selectedId === quotation.id ? 'bg-amber-50/50' : ''}`}>
                      <td className="px-3 py-2 font-mono text-xs" dir="ltr">{quotation.number}</td>
                      <td className="px-3 py-2">
                        {quotation.lead ? (
                          <Link to={`/crm/leads/${quotation.lead.id}`} className="hover:underline">{quotation.lead.full_name}</Link>
                        ) : '—'}
                      </td>
                      <td className="px-3 py-2">
                        <StatusBadge
                          status={quotation.status}
                          label={CRM_QUOTATION_STATUS_LABELS[quotation.status] ?? quotation.status}
                          tone={quotation.status === 'PENDING_APPROVAL' ? 'warning' : undefined}
                        />
                      </td>
                      <td className="px-3 py-2">{formatMoney(quotation.total, quotation.currency || 'SAR')}</td>
                      <td className="px-3 py-2">
                        <button type="button" className="rounded-lg border px-2 py-1" onClick={() => setSearchParams({ id: String(quotation.id) })}>
                          تفاصيل
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {meta && meta.last_page > 1 ? (
              <div className="flex items-center justify-between gap-3 text-sm">
                <p>صفحة {meta.current_page.toLocaleString('ar-SA')} من {meta.last_page.toLocaleString('ar-SA')}</p>
                <div className="flex gap-2">
                  <button type="button" disabled={meta.current_page <= 1} className="rounded-lg border px-3 py-1 disabled:opacity-50" onClick={() => setPage((p) => p - 1)}>السابق</button>
                  <button type="button" disabled={meta.current_page >= meta.last_page} className="rounded-lg border px-3 py-1 disabled:opacity-50" onClick={() => setPage((p) => p + 1)}>التالي</button>
                </div>
              </div>
            ) : null}
          </>
        ) : null}
      </DashboardSection>

      <aside className="space-y-4">
        <DashboardSection title="تفاصيل العرض">
          {!selectedId ? <p className="text-sm text-slate-600">اختر عرضاً من القائمة.</p> : null}
          {detailLoading ? <DashboardPanelSkeleton label="جاري التحميل..." /> : null}
          {detail && !detailLoading ? (
            <div className="space-y-4 text-sm">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-mono" dir="ltr">{detail.number}</span>
                <StatusBadge
                  status={detail.status}
                  label={CRM_QUOTATION_STATUS_LABELS[detail.status] ?? detail.status}
                  tone={detail.status === 'PENDING_APPROVAL' ? 'warning' : undefined}
                />
              </div>
              {detail.status === 'PENDING_APPROVAL' ? (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-amber-950">
                  بانتظار موافقة الخصم من المدير.
                </div>
              ) : null}
              <p>الإجمالي: <strong>{formatMoney(detail.total, detail.currency || 'SAR')}</strong></p>
              <p>الخصم: {formatMoney(detail.discount_amount, detail.currency || 'SAR')}</p>
              <p>الضريبة: {formatMoney(detail.tax_amount, detail.currency || 'SAR')}</p>
              {detail.lead ? (
                <p>
                  العميل:{' '}
                  <Link to={`/crm/leads/${detail.lead.id}`} className="underline">{detail.lead.full_name}</Link>
                </p>
              ) : null}
              {detail.public_token ? (
                <p className="text-xs text-slate-500" dir="ltr">
                  رابط عام: /crm/q/{detail.public_token}
                </p>
              ) : null}

              <ul className="divide-y rounded-xl border bg-white">
                {(detail.items ?? []).map((item, index) => (
                  <li key={item.id ?? index} className="px-3 py-2">
                    <p className="font-medium">{item.description}</p>
                    <p className="text-xs text-slate-500">
                      {Number(item.quantity).toLocaleString('ar-SA')} × {formatMoney(item.unit_price, detail.currency || 'SAR')}
                    </p>
                  </li>
                ))}
              </ul>

              <div className="flex flex-wrap gap-2">
                <button
                  type="button"
                  disabled={busyAction}
                  className="min-h-11 rounded-xl border px-4 text-sm disabled:opacity-60"
                  onClick={() =>
                    void downloadCrmQuotationPdf(detail.id, detail.number).catch((caught) =>
                      toast.error(describeApiError(caught, 'تعذر تحميل PDF.')),
                    )
                  }
                >
                  PDF
                </button>
                {manager && detail.status === 'PENDING_APPROVAL' ? (
                  <>
                    <button
                      type="button"
                      disabled={busyAction}
                      className="min-h-11 rounded-xl bg-emerald-700 px-4 text-sm text-white disabled:opacity-60"
                      onClick={() => void runQuotationAction(async () => { await approveCrmQuotation(detail.id) }, 'تمت الموافقة.')}
                    >
                      موافقة
                    </button>
                    <button
                      type="button"
                      disabled={busyAction}
                      className="min-h-11 rounded-xl border border-red-300 px-4 text-sm text-red-800 disabled:opacity-60"
                      onClick={() => {
                        const notes = window.prompt('سبب الرفض (اختياري)') ?? undefined
                        void runQuotationAction(async () => { await rejectCrmQuotation(detail.id, notes || undefined) }, 'تم الرفض.')
                      }}
                    >
                      رفض
                    </button>
                  </>
                ) : null}
                {(detail.status === 'DRAFT' || detail.status === 'APPROVED' || detail.status === 'SENT') ? (
                  <button
                    type="button"
                    disabled={busyAction}
                    className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60"
                    onClick={() => void runQuotationAction(async () => { await sendCrmQuotation(detail.id) }, 'تم إرسال العرض.')}
                  >
                    {detail.status === 'SENT' ? 'إعادة إرسال' : 'إرسال للعميل'}
                  </button>
                ) : null}
              </div>
            </div>
          ) : null}
        </DashboardSection>
      </aside>
    </div>
  )
}
