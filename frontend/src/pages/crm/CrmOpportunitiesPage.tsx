import { FormEvent, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useToast } from '../../context/ToastContext'
import {
  createCrmOpportunity,
  getCrmLeads,
  getCrmOpportunities,
  getCrmPipelineStages,
  moveCrmOpportunityStage,
  type CrmLead,
  type CrmOpportunity,
  type CrmStage,
} from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function CrmOpportunitiesPage() {
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [items, setItems] = useState<CrmOpportunity[]>([])
  const [weighted, setWeighted] = useState(0)
  const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(null)
  const [leads, setLeads] = useState<CrmLead[]>([])
  const [stages, setStages] = useState<CrmStage[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmOpportunities({ page, per_page: 20 })
      setItems(response.data.items)
      setMeta(response.data.meta)
      setWeighted(response.data.weighted_revenue ?? 0)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الفرص.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void getCrmLeads({ per_page: 50 })
      .then((response) => setLeads(response.data.items))
      .catch(() => setLeads([]))
    void getCrmPipelineStages()
      .then((response) => setStages(response.data.items))
      .catch(() => setStages([]))
  }, [])

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page])

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (saving) return
    const form = new FormData(event.currentTarget)
    setSaving(true)
    setFormError(null)
    try {
      await createCrmOpportunity({
        lead_id: Number(form.get('lead_id')),
        name: String(form.get('name') || '').trim() || undefined,
        deal_value: form.get('deal_value') ? Number(form.get('deal_value')) : undefined,
        stage_id: form.get('stage_id') ? Number(form.get('stage_id')) : undefined,
        expected_close_at: String(form.get('expected_close_at') || '') || undefined,
        notes: String(form.get('notes') || '').trim() || undefined,
      })
      setCreating(false)
      toast.success('تم إنشاء الفرصة.')
      await load()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر إنشاء الفرصة.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleStage(id: number, stageId: number) {
    try {
      await moveCrmOpportunityStage(id, stageId)
      toast.success('تم تحديث مرحلة الفرصة.')
      await load()
    } catch (caught) {
      toast.error(describeApiError(caught, 'تعذر تحديث المرحلة.'))
    }
  }

  return (
    <DashboardSection
      title="الفرص"
      description={`إيراد مرجّح: ${formatMoney(weighted)}`}
      action={
        <button type="button" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white" onClick={() => setCreating(true)}>
          فرصة جديدة
        </button>
      }
    >
      {creating ? (
        <form onSubmit={(event) => void handleCreate(event)} className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2">
          {formError ? (
            <div className="sm:col-span-2">
              <FeedbackBanner kind="error">{formError}</FeedbackBanner>
            </div>
          ) : null}
          <select required name="lead_id" className={fieldClass}>
            <option value="">العميل المحتمل *</option>
            {leads.map((lead) => (
              <option key={lead.id} value={lead.id}>
                {lead.full_name} ({lead.reference})
              </option>
            ))}
          </select>
          <input name="name" placeholder="اسم الفرصة" className={fieldClass} />
          <input name="deal_value" type="number" min="0" step="0.01" placeholder="قيمة الصفقة" className={fieldClass} />
          <select name="stage_id" className={fieldClass}>
            <option value="">المرحلة</option>
            {stages.map((stage) => (
              <option key={stage.id} value={stage.id}>
                {stage.name}
              </option>
            ))}
          </select>
          <input name="expected_close_at" type="date" className={fieldClass} />
          <textarea name="notes" rows={2} placeholder="ملاحظات" className={`${fieldClass} sm:col-span-2`} />
          <div className="flex gap-2 sm:col-span-2">
            <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
              حفظ
            </button>
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setCreating(false)}>
              إلغاء
            </button>
          </div>
        </form>
      ) : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل الفرص..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا فرص." description="أنشئ فرصة من عميل محتمل مؤهل." />
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <>
          <div className="overflow-x-auto rounded-2xl border bg-white">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-right">
                <tr>
                  <th className="px-3 py-2">المرجع</th>
                  <th className="px-3 py-2">الاسم</th>
                  <th className="px-3 py-2">العميل</th>
                  <th className="px-3 py-2">القيمة</th>
                  <th className="px-3 py-2">الاحتمال</th>
                  <th className="px-3 py-2">المرحلة</th>
                </tr>
              </thead>
              <tbody>
                {items.map((opportunity) => (
                  <tr key={opportunity.id} className="border-t">
                    <td className="px-3 py-2 font-mono text-xs" dir="ltr">
                      {opportunity.reference}
                    </td>
                    <td className="px-3 py-2 font-medium">{opportunity.name || '—'}</td>
                    <td className="px-3 py-2">
                      {opportunity.lead ? (
                        <Link to={`/crm/leads/${opportunity.lead.id}`} className="hover:underline">
                          {opportunity.lead.full_name}
                        </Link>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="px-3 py-2">{opportunity.deal_value != null ? formatMoney(opportunity.deal_value) : '—'}</td>
                    <td className="px-3 py-2">
                      {opportunity.probability != null ? `${opportunity.probability}%` : '—'}
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex flex-wrap items-center gap-2">
                        {opportunity.stage ? (
                          <StatusBadge status="stage" label={opportunity.stage.name} tone="progress" />
                        ) : (
                          '—'
                        )}
                        <select
                          className="rounded-lg border border-slate-300 px-2 py-1 text-xs"
                          value={opportunity.stage_id ?? ''}
                          onChange={(event) => void handleStage(opportunity.id, Number(event.target.value))}
                        >
                          <option value="" disabled>
                            نقل
                          </option>
                          {stages.map((stage) => (
                            <option key={stage.id} value={stage.id}>
                              {stage.name}
                            </option>
                          ))}
                        </select>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {meta && meta.last_page > 1 ? (
            <div className="flex items-center justify-between gap-3 text-sm">
              <p>
                صفحة {meta.current_page.toLocaleString('ar-SA')} من {meta.last_page.toLocaleString('ar-SA')}
              </p>
              <div className="flex gap-2">
                <button type="button" disabled={meta.current_page <= 1} className="rounded-lg border px-3 py-1 disabled:opacity-50" onClick={() => setPage((p) => p - 1)}>
                  السابق
                </button>
                <button type="button" disabled={meta.current_page >= meta.last_page} className="rounded-lg border px-3 py-1 disabled:opacity-50" onClick={() => setPage((p) => p + 1)}>
                  التالي
                </button>
              </div>
            </div>
          ) : null}
        </>
      ) : null}
    </DashboardSection>
  )
}
