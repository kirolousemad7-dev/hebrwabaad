import { FormEvent, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
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
  assignCrmLead,
  convertCrmLead,
  createCrmActivity,
  createCrmFollowUp,
  createCrmQuotation,
  getCrmActivities,
  getCrmLead,
  getCrmLeadDuplicates,
  getCrmPipelineStages,
  getCrmQuotations,
  getCrmSettings,
  getCrmTeam,
  loseCrmLead,
  mergeCrmLeads,
  moveCrmLeadStage,
  whatsappUrl,
  type CrmActivity,
  type CrmLead,
  type CrmLostReason,
  type CrmQuotation,
  type CrmStage,
  type CrmTeamMember,
} from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { createCalendarItem } from '../../services/calendar'
import {
  CRM_ACTIVITY_TYPE_LABELS,
  CRM_FOLLOW_UP_TYPE_LABELS,
  CRM_PRIORITY_LABELS,
  CRM_QUOTATION_STATUS_LABELS,
  crmPriorityLabel,
  crmStatusLabel,
} from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type Panel = 'note' | 'followup' | 'assign' | 'stage' | 'quote' | 'convert' | 'lose' | 'merge' | 'log' | null

export function CrmLeadDetailPage() {
  const { id } = useParams()
  const leadId = Number(id)
  const toast = useToast()
  const [lead, setLead] = useState<CrmLead | null>(null)
  const [activities, setActivities] = useState<CrmActivity[]>([])
  const [quotations, setQuotations] = useState<CrmQuotation[]>([])
  const [duplicates, setDuplicates] = useState<CrmLead[]>([])
  const [stages, setStages] = useState<CrmStage[]>([])
  const [team, setTeam] = useState<CrmTeamMember[]>([])
  const [lostReasons, setLostReasons] = useState<CrmLostReason[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [panel, setPanel] = useState<Panel>(null)
  const [logType, setLogType] = useState('CALL')
  const [actionError, setActionError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function load() {
    if (!Number.isFinite(leadId) || leadId <= 0) {
      setError('معرّف غير صالح.')
      setLoading(false)
      return
    }

    setLoading(true)
    setError(null)

    try {
      const [leadResponse, activityResponse, quotationResponse] = await Promise.all([
        getCrmLead(leadId),
        getCrmActivities({ lead_id: leadId, per_page: 50 }),
        getCrmQuotations({ per_page: 50 }),
      ])
      setLead(leadResponse.data)
      setActivities(activityResponse.data.items)
      setQuotations(quotationResponse.data.items.filter((item) => item.lead_id === leadId))

      const dup = await getCrmLeadDuplicates({
        phone: leadResponse.data.phone ?? undefined,
        email: leadResponse.data.email ?? undefined,
        whatsapp: leadResponse.data.whatsapp ?? undefined,
        except_id: leadId,
      })
      setDuplicates(dup.data.items)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل العميل المحتمل.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    void getCrmPipelineStages()
      .then((response) => setStages(response.data.items))
      .catch(() => setStages([]))
    void getCrmTeam()
      .then((response) => setTeam(response.data.items))
      .catch(() => setTeam([]))
    void getCrmSettings()
      .then((response) => setLostReasons(response.data.lost_reasons))
      .catch(() => setLostReasons([]))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [leadId])

  async function runAction(action: () => Promise<void>, successMessage: string) {
    if (busy) return
    setBusy(true)
    setActionError(null)
    try {
      await action()
      setPanel(null)
      toast.success(successMessage)
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر تنفيذ الإجراء.'))
    } finally {
      setBusy(false)
    }
  }

  async function quickLog(type: string, notes?: string, openWa = false) {
    if (!lead) return
    if (openWa) {
      const wa = whatsappUrl(lead.whatsapp || lead.phone)
      if (wa) window.open(wa, '_blank', 'noopener')
    }
    await runAction(
      async () => {
        await createCrmActivity({
          lead_id: lead.id,
          type,
          notes: notes || `تسجيل سريع: ${CRM_ACTIVITY_TYPE_LABELS[type] ?? type}`,
        })
      },
      'تم تسجيل النشاط.',
    )
  }

  async function scheduleCalendar(type: 'FOLLOW_UP' | 'MEETING') {
    if (!lead) return
    const starts = new Date()
    starts.setHours(starts.getHours() + (type === 'MEETING' ? 2 : 24), 0, 0, 0)
    await runAction(
      async () => {
        await createCalendarItem({
          title: type === 'MEETING' ? `اجتماع: ${lead.full_name}` : `متابعة: ${lead.full_name}`,
          type,
          starts_at: starts.toISOString(),
          source: 'CRM',
          related_type: 'crm_lead',
          related_id: lead.id,
          priority: 'MEDIUM',
          status: 'SCHEDULED',
        })
      },
      type === 'MEETING' ? 'تمت جدولة الاجتماع في التقويم.' : 'تمت جدولة المتابعة في التقويم.',
    )
  }

  if (loading) return <DashboardPanelSkeleton label="جاري تحميل تفاصيل العميل..." />
  if (error) return <DashboardErrorState message={error} onRetry={() => void load()} />
  if (!lead) return <DashboardEmptyState title="غير موجود" description="لم يتم العثور على هذا العميل المحتمل." />

  const wa = whatsappUrl(lead.whatsapp || lead.phone)
  const phone = lead.phone?.replace(/\s/g, '') || null

  return (
    <section className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-2">
          <Link to="/crm/leads" className="text-sm text-slate-600 hover:underline">
            ← العودة للقائمة
          </Link>
          <h1 className="text-2xl font-semibold">{lead.full_name}</h1>
          <p className="text-sm text-slate-600" dir="ltr">
            {lead.reference}
            {lead.company_name ? ` · ${lead.company_name}` : ''}
          </p>
          <div className="flex flex-wrap gap-2">
            <StatusBadge status={lead.status} label={crmStatusLabel(lead.status)} />
            <StatusBadge status={lead.priority} label={crmPriorityLabel(lead.priority)} tone="warning" />
            {lead.stage ? <StatusBadge status="stage" label={lead.stage.name} tone="progress" /> : null}
            {lead.needs_attention ? <StatusBadge status="attention" label="يحتاج انتباه" tone="warning" /> : null}
            {lead.age_days != null ? <StatusBadge status="age" label={`عمر ${lead.age_days} يوم`} /> : null}
            {lead.days_in_stage != null ? <StatusBadge status="stage-days" label={`${lead.days_in_stage} يوم في المرحلة`} tone="progress" /> : null}
            {lead.days_since_contact != null ? (
              <StatusBadge status="contact" label={`${lead.days_since_contact} يوم بلا تواصل`} tone="warning" />
            ) : null}
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => void quickLog('CALL', 'اتصال هاتفي')}>
            اتصال
          </button>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => void quickLog('WHATSAPP', 'تواصل واتساب', true)}>
            واتساب
          </button>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => void quickLog('EMAIL', 'بريد إلكتروني')}>
            بريد
          </button>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => void quickLog('MEETING', 'اجتماع')}>
            اجتماع
          </button>
          <button
            type="button"
            className="min-h-10 rounded-xl border border-amber-300 px-3 text-sm text-amber-950"
            disabled={busy}
            onClick={() => void scheduleCalendar('FOLLOW_UP')}
          >
            جدولة متابعة
          </button>
          <button
            type="button"
            className="min-h-10 rounded-xl border border-sky-300 px-3 text-sm text-sky-950"
            disabled={busy}
            onClick={() => void scheduleCalendar('MEETING')}
          >
            جدولة اجتماع
          </button>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => { setLogType('NOTE'); setPanel('log') }}>
            ملاحظة
          </button>
          {phone ? (
            <a href={`tel:${phone}`} className="min-h-10 rounded-xl border px-3 text-sm leading-10">
              طلب اتصال
            </a>
          ) : null}
          {wa ? (
            <a href={wa} target="_blank" rel="noopener noreferrer" className="min-h-10 rounded-xl border px-3 text-sm leading-10">
              فتح واتساب
            </a>
          ) : null}
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => setPanel('assign')}>تعيين</button>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => setPanel('stage')}>المرحلة</button>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => setPanel('followup')}>متابعة</button>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={() => setPanel('quote')}>عرض سعر</button>
          {duplicates.length > 0 ? (
            <button type="button" className="min-h-10 rounded-xl border border-amber-300 px-3 text-sm text-amber-900" onClick={() => setPanel('merge')}>
              دمج ({duplicates.length})
            </button>
          ) : null}
          {lead.customer_id ? (
            <Link to={`/crm/customers/${lead.customer_id}`} className="min-h-10 rounded-xl border px-3 text-sm leading-10">
              ملف 360
            </Link>
          ) : null}
          {lead.company_id || lead.company?.id ? (
            <Link to={`/crm/companies/${lead.company_id ?? lead.company?.id}`} className="min-h-10 rounded-xl border px-3 text-sm leading-10">
              الشركة
            </Link>
          ) : null}
          {lead.status !== 'WON' && lead.status !== 'LOST' ? (
            <>
              <button type="button" className="min-h-10 rounded-xl bg-emerald-700 px-3 text-sm text-white" onClick={() => setPanel('convert')}>
                إغلاق رابح
              </button>
              <button type="button" className="min-h-10 rounded-xl border border-red-300 px-3 text-sm text-red-800" onClick={() => setPanel('lose')}>
                خسارة
              </button>
            </>
          ) : null}
        </div>
      </div>

      {actionError ? <FeedbackBanner kind="error">{actionError}</FeedbackBanner> : null}

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="space-y-3 rounded-2xl border bg-white p-4 text-sm lg:col-span-1">
          <h2 className="font-semibold">الملف</h2>
          <p>الهاتف: <span dir="ltr">{lead.phone || '—'}</span></p>
          <p>واتساب: <span dir="ltr">{lead.whatsapp || '—'}</span></p>
          <p>البريد: <span dir="ltr">{lead.email || '—'}</span></p>
          <p>المدينة: {lead.city || '—'}{lead.country ? ` · ${lead.country}` : ''}</p>
          <p>المصدر: {lead.source?.name || '—'}</p>
          <p>المكلّف: {lead.assignee?.name || '—'}</p>
          <p>
            القيمة:{' '}
            {lead.deal_value != null
              ? formatMoney(lead.deal_value)
              : lead.estimated_budget != null
                ? formatMoney(lead.estimated_budget)
                : '—'}
          </p>
          <p>متابعة قادمة: {lead.next_follow_up_at ? new Date(lead.next_follow_up_at).toLocaleString('ar-SA') : '—'}</p>
          {lead.notes ? <p className="whitespace-pre-wrap text-slate-700">{lead.notes}</p> : null}
        </div>

        <div className="space-y-4 lg:col-span-2">
          {panel === 'log' ? (
            <ActionForm
              title={`تسجيل ${CRM_ACTIVITY_TYPE_LABELS[logType] ?? logType}`}
              onCancel={() => setPanel(null)}
              onSubmit={(event) => {
                event.preventDefault()
                const form = new FormData(event.currentTarget)
                void runAction(
                  async () => {
                    await createCrmActivity({
                      lead_id: lead.id,
                      type: String(form.get('type') || logType),
                      notes: String(form.get('notes') || '').trim() || undefined,
                      call_result: String(form.get('call_result') || '').trim() || undefined,
                      duration_minutes: form.get('duration_minutes') ? Number(form.get('duration_minutes')) : undefined,
                    })
                  },
                  'تم تسجيل النشاط.',
                )
              }}
              busy={busy}
            >
              <select name="type" defaultValue={logType} className={fieldClass}>
                {['CALL', 'WHATSAPP', 'EMAIL', 'MEETING', 'NOTE'].map((type) => (
                  <option key={type} value={type}>
                    {CRM_ACTIVITY_TYPE_LABELS[type]}
                  </option>
                ))}
              </select>
              <input name="duration_minutes" type="number" min="0" placeholder="المدة (دقائق)" className={fieldClass} />
              <input name="call_result" placeholder="نتيجة الاتصال" className={fieldClass} />
              <textarea name="notes" rows={2} placeholder="ملاحظات" className={`${fieldClass} sm:col-span-2`} />
            </ActionForm>
          ) : null}

          {panel === 'followup' ? (
            <ActionForm
              title="جدولة متابعة"
              onCancel={() => setPanel(null)}
              onSubmit={(event) => {
                event.preventDefault()
                const form = new FormData(event.currentTarget)
                void runAction(
                  async () => {
                    await createCrmFollowUp({
                      lead_id: lead.id,
                      type: String(form.get('type') || 'CALL'),
                      scheduled_at: String(form.get('scheduled_at') || ''),
                      priority: String(form.get('priority') || 'MEDIUM'),
                      notes: String(form.get('notes') || '').trim() || undefined,
                    })
                  },
                  'تمت جدولة المتابعة.',
                )
              }}
              busy={busy}
            >
              <select name="type" defaultValue="CALL" className={fieldClass}>
                {Object.entries(CRM_FOLLOW_UP_TYPE_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
              <input required name="scheduled_at" type="datetime-local" className={fieldClass} />
              <select name="priority" defaultValue="MEDIUM" className={fieldClass}>
                {Object.entries(CRM_PRIORITY_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
              <textarea name="notes" rows={2} placeholder="ملاحظات" className={fieldClass} />
            </ActionForm>
          ) : null}

          {panel === 'assign' ? (
            <ActionForm
              title="تعيين مسؤول"
              onCancel={() => setPanel(null)}
              onSubmit={(event) => {
                event.preventDefault()
                const form = new FormData(event.currentTarget)
                void runAction(async () => { await assignCrmLead(lead.id, Number(form.get('assigned_to'))) }, 'تم التعيين.')
              }}
              busy={busy}
            >
              <select required name="assigned_to" defaultValue={lead.assignee?.id ?? ''} className={fieldClass}>
                <option value="" disabled>اختر عضواً</option>
                {team.map((member) => (
                  <option key={member.id} value={member.id}>{member.name}</option>
                ))}
              </select>
            </ActionForm>
          ) : null}

          {panel === 'stage' ? (
            <ActionForm
              title="تغيير المرحلة"
              onCancel={() => setPanel(null)}
              onSubmit={(event) => {
                event.preventDefault()
                const form = new FormData(event.currentTarget)
                void runAction(async () => { await moveCrmLeadStage(lead.id, Number(form.get('stage_id'))) }, 'تم تحديث المرحلة.')
              }}
              busy={busy}
            >
              <select required name="stage_id" defaultValue={lead.stage?.id ?? ''} className={fieldClass}>
                {stages.map((stage) => (
                  <option key={stage.id} value={stage.id}>{stage.name}</option>
                ))}
              </select>
            </ActionForm>
          ) : null}

          {panel === 'quote' ? (
            <ActionForm
              title="إنشاء عرض سعر"
              onCancel={() => setPanel(null)}
              onSubmit={(event) => {
                event.preventDefault()
                const form = new FormData(event.currentTarget)
                void runAction(
                  async () => {
                    await createCrmQuotation({
                      lead_id: lead.id,
                      notes: String(form.get('notes') || '').trim() || undefined,
                      discount_amount: form.get('discount_amount') ? Number(form.get('discount_amount')) : undefined,
                      items: [{
                        description: String(form.get('description') || '').trim(),
                        quantity: Number(form.get('quantity') || 1),
                        unit_price: Number(form.get('unit_price') || 0),
                      }],
                    })
                  },
                  'تم إنشاء عرض السعر.',
                )
              }}
              busy={busy}
            >
              <input required name="description" placeholder="وصف البند" className={fieldClass} />
              <input required name="quantity" type="number" min="0.01" step="0.01" defaultValue="1" className={fieldClass} />
              <input required name="unit_price" type="number" min="0" step="0.01" placeholder="سعر الوحدة" className={fieldClass} />
              <input name="discount_amount" type="number" min="0" step="0.01" placeholder="خصم (قد يتطلب موافقة)" className={fieldClass} />
              <textarea name="notes" rows={2} placeholder="ملاحظات العرض" className={`${fieldClass} sm:col-span-2`} />
            </ActionForm>
          ) : null}

          {panel === 'convert' ? (
            <ActionForm
              title="إغلاق كصفقة رابحة"
              onCancel={() => setPanel(null)}
              onSubmit={(event) => {
                event.preventDefault()
                const form = new FormData(event.currentTarget)
                void runAction(
                  async () => {
                    await convertCrmLead(lead.id, {
                      deal_value: form.get('deal_value') ? Number(form.get('deal_value')) : undefined,
                      create_project: form.get('create_project') === '1',
                      title: String(form.get('title') || '').trim() || undefined,
                    })
                  },
                  'تم إغلاق الصفقة بنجاح.',
                )
              }}
              busy={busy}
            >
              <input name="deal_value" type="number" min="0" step="0.01" placeholder="قيمة الصفقة" defaultValue={lead.deal_value ?? ''} className={fieldClass} />
              <input name="title" placeholder="عنوان الطلب / المشروع" className={fieldClass} />
              <label className="flex items-center gap-2 text-sm sm:col-span-2">
                <input name="create_project" type="checkbox" value="1" defaultChecked />
                إنشاء مشروع مرتبط
              </label>
            </ActionForm>
          ) : null}

          {panel === 'lose' ? (
            <ActionForm
              title="تسجيل خسارة"
              onCancel={() => setPanel(null)}
              onSubmit={(event) => {
                event.preventDefault()
                const form = new FormData(event.currentTarget)
                void runAction(
                  async () => {
                    await loseCrmLead(lead.id, {
                      lost_reason_id: Number(form.get('lost_reason_id')),
                      notes: String(form.get('notes') || '').trim() || undefined,
                      competitor: String(form.get('competitor') || '').trim() || undefined,
                    })
                  },
                  'تم تسجيل الخسارة.',
                )
              }}
              busy={busy}
            >
              <select required name="lost_reason_id" className={fieldClass}>
                <option value="">سبب الخسارة</option>
                {lostReasons.map((reason) => (
                  <option key={reason.id} value={reason.id}>{reason.name}</option>
                ))}
              </select>
              <input name="competitor" placeholder="المنافس (اختياري)" className={fieldClass} />
              <textarea name="notes" rows={2} placeholder="ملاحظات" className={`${fieldClass} sm:col-span-2`} />
            </ActionForm>
          ) : null}

          {panel === 'merge' ? (
            <ActionForm
              title="دمج مع عميل مكرر"
              onCancel={() => setPanel(null)}
              onSubmit={(event) => {
                event.preventDefault()
                const form = new FormData(event.currentTarget)
                void runAction(
                  async () => {
                    await mergeCrmLeads(lead.id, Number(form.get('secondary_id')))
                  },
                  'تم الدمج.',
                )
              }}
              busy={busy}
            >
              <select required name="secondary_id" className={fieldClass}>
                <option value="">اختر السجل الثانوي للدمج فيه</option>
                {duplicates.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.full_name} · {item.reference}
                  </option>
                ))}
              </select>
            </ActionForm>
          ) : null}

          <DashboardSection title="عروض الأسعار">
            {quotations.length === 0 ? (
              <DashboardEmptyState title="لا عروض بعد." description="أنشئ عرض سعر من الزر أعلاه." />
            ) : (
              <ul className="space-y-2">
                {quotations.map((quotation) => (
                  <li key={quotation.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-white px-4 py-3 text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-mono text-xs" dir="ltr">{quotation.number}</span>
                      <StatusBadge status={quotation.status} label={CRM_QUOTATION_STATUS_LABELS[quotation.status] ?? quotation.status} />
                      <span>{formatMoney(quotation.total, quotation.currency || 'SAR')}</span>
                    </div>
                    <Link to={`/crm/quotations?id=${quotation.id}`} className="rounded-lg border px-2 py-1 text-xs">
                      إدارة
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </DashboardSection>

          <DashboardSection title="سجل الأنشطة">
            {activities.length === 0 ? (
              <DashboardEmptyState title="لا أنشطة بعد." description="سجّل اتصالاً أو ملاحظة لبدء السجل." />
            ) : (
              <ul className="space-y-3">
                {activities.map((activity) => (
                  <li key={activity.id} className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <span className="font-medium">{CRM_ACTIVITY_TYPE_LABELS[activity.type] ?? activity.type}</span>
                      <span className="text-xs text-slate-500">
                        {activity.occurred_at ? new Date(activity.occurred_at).toLocaleString('ar-SA') : '—'}
                      </span>
                    </div>
                    {activity.user?.name ? <p className="mt-1 text-xs text-slate-500">{activity.user.name}</p> : null}
                    {activity.call_result ? <p className="mt-1 text-xs text-slate-600">النتيجة: {activity.call_result}</p> : null}
                    {activity.notes ? <p className="mt-2 whitespace-pre-wrap text-slate-700">{activity.notes}</p> : null}
                  </li>
                ))}
              </ul>
            )}
          </DashboardSection>
        </div>
      </div>
    </section>
  )
}

function ActionForm({
  title,
  children,
  onSubmit,
  onCancel,
  busy,
}: {
  title: string
  children: React.ReactNode
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
  onCancel: () => void
  busy: boolean
}) {
  return (
    <form onSubmit={onSubmit} className="space-y-3 rounded-2xl border border-amber-200 bg-amber-50/40 p-4">
      <h3 className="font-semibold">{title}</h3>
      <div className="grid gap-3 sm:grid-cols-2">{children}</div>
      <div className="flex gap-2">
        <button type="submit" disabled={busy} className="min-h-10 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
          {busy ? 'جاري...' : 'حفظ'}
        </button>
        <button type="button" className="min-h-10 rounded-xl border px-4 text-sm" onClick={onCancel}>
          إلغاء
        </button>
      </div>
    </form>
  )
}
