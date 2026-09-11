import { useEffect, useMemo, useState } from 'react'
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
  getAllOpenCrmLeads,
  getCrmPipelineStages,
  getCrmQuotations,
  moveCrmLeadStage,
  type CrmLead,
  type CrmQuotation,
  type CrmStage,
} from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { crmPriorityLabel } from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

function leadValue(lead: CrmLead): number {
  return Number(lead.deal_value ?? lead.estimated_budget ?? 0)
}

export function CrmPipelinePage() {
  const toast = useToast()
  const [stages, setStages] = useState<CrmStage[]>([])
  const [leads, setLeads] = useState<CrmLead[]>([])
  const [quotations, setQuotations] = useState<CrmQuotation[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [draggingId, setDraggingId] = useState<number | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)

    try {
      const [stagesResponse, openLeads, quotesResponse] = await Promise.all([
        getCrmPipelineStages(),
        getAllOpenCrmLeads(),
        getCrmQuotations({ per_page: 50 }),
      ])
      setStages(stagesResponse.data.items.filter((stage) => !stage.is_won && !stage.is_lost))
      setLeads(openLeads)
      setQuotations(quotesResponse.data.items)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل خط الأنابيب.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  const byStage = useMemo(() => {
    const map = new Map<number, CrmLead[]>()
    for (const stage of stages) map.set(stage.id, [])

    for (const lead of leads) {
      const stageId = lead.stage?.id
      if (stageId && map.has(stageId)) map.get(stageId)!.push(lead)
      else if (stages[0]) map.get(stages[0].id)!.push(lead)
    }

    return map
  }, [leads, stages])

  const quotesByLead = useMemo(() => {
    const map = new Map<number, CrmQuotation[]>()
    for (const quote of quotations) {
      const list = map.get(quote.lead_id) ?? []
      list.push(quote)
      map.set(quote.lead_id, list)
    }
    return map
  }, [quotations])

  function warningsFor(lead: CrmLead): string[] {
    const warnings: string[] = []
    const slug = lead.stage?.slug?.toLowerCase() ?? ''
    const stageName = lead.stage?.name ?? ''
    const isProposal =
      lead.status === 'PROPOSAL_SENT' || slug.includes('proposal') || stageName.includes('عرض')
    const quotes = quotesByLead.get(lead.id) ?? []
    if (isProposal && quotes.length === 0) warnings.push('عرض بدون عرض سعر')
    if (lead.needs_attention) warnings.push('يحتاج انتباه')
    if (!lead.assignee) warnings.push('غير معيّن')
    if ((lead.days_in_stage ?? 0) >= 14) warnings.push('طويل في المرحلة')
    return warnings
  }

  async function moveLead(leadId: number, stageId: number) {
    const lead = leads.find((item) => item.id === leadId)
    const target = stages.find((stage) => stage.id === stageId)
    if (!lead || lead.stage?.id === stageId) return

    if (target?.is_lost || target?.slug?.includes('lost')) {
      toast.error('لنقل إلى خسارة استخدم صفحة العميل مع سبب الخسارة.')
      return
    }
    if (target?.is_won || target?.slug?.includes('won')) {
      toast.error('للإغلاق الرابح استخدم زر التحويل من صفحة العميل.')
      return
    }

    setBusyId(leadId)
    setActionError(null)
    const previous = leads

    setLeads((current) =>
      current.map((item) =>
        item.id === leadId ? { ...item, stage: stages.find((stage) => stage.id === stageId) ?? item.stage } : item,
      ),
    )

    try {
      await moveCrmLeadStage(leadId, stageId)
      toast.success('تم نقل العميل إلى المرحلة الجديدة.')
    } catch (caught) {
      setLeads(previous)
      setActionError(describeApiError(caught, 'تعذر نقل العميل.'))
    } finally {
      setBusyId(null)
      setDraggingId(null)
    }
  }

  return (
    <DashboardSection title="خط الأنابيب" description="اسحب البطاقة أو اختر المرحلة. الإجماليات والتحذيرات ظاهرة لكل عمود.">
      {loading ? <DashboardPanelSkeleton label="جاري تحميل خط الأنابيب..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {actionError ? <FeedbackBanner kind="error">{actionError}</FeedbackBanner> : null}

      {!loading && !error && stages.length === 0 ? (
        <DashboardEmptyState title="لا توجد مراحل." description="أضف مراحل من إعدادات CRM أولاً." />
      ) : null}

      {!loading && !error && stages.length > 0 ? (
        <div className="flex gap-3 overflow-x-auto pb-2">
          {stages.map((stage) => {
            const columnLeads = byStage.get(stage.id) ?? []
            const total = columnLeads.reduce((sum, lead) => sum + leadValue(lead), 0)

            return (
              <div
                key={stage.id}
                className="w-80 shrink-0 rounded-2xl border border-slate-200 bg-slate-50"
                onDragOver={(event) => event.preventDefault()}
                onDrop={() => {
                  if (draggingId != null) void moveLead(draggingId, stage.id)
                }}
              >
                <div className="border-b border-slate-200 px-3 py-3">
                  <p className="font-medium">{stage.name}</p>
                  <p className="text-xs text-slate-500">
                    {columnLeads.length.toLocaleString('ar-SA')} · {formatMoney(total)} · احتمال {stage.probability}%
                  </p>
                </div>
                <ul className="space-y-2 p-2">
                  {columnLeads.map((lead) => {
                    const warnings = warningsFor(lead)
                    return (
                      <li
                        key={lead.id}
                        draggable
                        onDragStart={() => setDraggingId(lead.id)}
                        onDragEnd={() => setDraggingId(null)}
                        className={`cursor-grab rounded-xl border bg-white p-3 text-sm shadow-sm active:cursor-grabbing ${
                          busyId === lead.id ? 'opacity-60' : ''
                        } ${warnings.length ? 'border-amber-300' : 'border-slate-200'}`}
                      >
                        <Link to={`/crm/leads/${lead.id}`} className="font-medium hover:underline">
                          {lead.full_name}
                        </Link>
                        <p className="mt-1 text-xs text-slate-500">{lead.company_name || lead.reference}</p>
                        <p className="mt-1 text-xs text-slate-600">
                          {leadValue(lead) > 0 ? formatMoney(leadValue(lead)) : 'بدون قيمة'}
                          {lead.age_days != null ? ` · ${lead.age_days} يوم` : ''}
                        </p>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                          <StatusBadge status={lead.priority} label={crmPriorityLabel(lead.priority)} tone="warning" />
                          <span className="text-xs text-slate-500">{lead.assignee?.name ?? 'غير معيّن'}</span>
                        </div>
                        {warnings.length > 0 ? (
                          <ul className="mt-2 space-y-1">
                            {warnings.map((warning) => (
                              <li key={warning} className="rounded-lg bg-amber-50 px-2 py-1 text-[11px] text-amber-900">
                                {warning}
                              </li>
                            ))}
                          </ul>
                        ) : null}
                        <label className="mt-2 block text-xs text-slate-600">
                          نقل إلى
                          <select
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm"
                            value={lead.stage?.id ?? stage.id}
                            disabled={busyId === lead.id}
                            onChange={(event) => void moveLead(lead.id, Number(event.target.value))}
                          >
                            {stages.map((option) => (
                              <option key={option.id} value={option.id}>
                                {option.name}
                              </option>
                            ))}
                          </select>
                        </label>
                      </li>
                    )
                  })}
                  {columnLeads.length === 0 ? (
                    <li className="rounded-xl border border-dashed border-slate-300 px-3 py-6 text-center text-xs text-slate-500">
                      فارغ
                    </li>
                  ) : null}
                </ul>
              </div>
            )
          })}
        </div>
      ) : null}
    </DashboardSection>
  )
}
