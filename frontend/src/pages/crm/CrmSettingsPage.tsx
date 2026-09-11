import { FormEvent, useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import {
  createCrmAssignmentRule,
  createCrmLostReason,
  createCrmSource,
  createCrmStage,
  createCrmTag,
  deleteCrmAssignmentRule,
  getCrmAssignmentRules,
  getCrmSettings,
  getCrmTeam,
  updateCrmSettingsConfig,
  type CrmAssignmentRule,
  type CrmConfig,
  type CrmLostReason,
  type CrmSettingsData,
  type CrmSource,
  type CrmStage,
  type CrmTag,
  type CrmTeamMember,
} from '../../services/crm'
import { CRM_ASSIGNMENT_MODE_LABELS } from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function CrmSettingsPage() {
  const toast = useToast()
  const [data, setData] = useState<CrmSettingsData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [config, setConfig] = useState<Partial<CrmConfig>>({})
  const [savingConfig, setSavingConfig] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [rules, setRules] = useState<CrmAssignmentRule[]>([])
  const [team, setTeam] = useState<CrmTeamMember[]>([])
  const [savingRule, setSavingRule] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmSettings()
      setData(response.data)
      setConfig(response.data.config ?? {})
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الإعدادات.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    void getCrmAssignmentRules()
      .then((response) => setRules(response.data.items))
      .catch(() => setRules([]))
    void getCrmTeam()
      .then((response) => setTeam(response.data.items))
      .catch(() => setTeam([]))
  }, [])

  async function saveRule(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setSavingRule(true)
    setFormError(null)
    try {
      await createCrmAssignmentRule({
        name: String(form.get('name') || '').trim(),
        match_type: String(form.get('match_type') || 'source') as 'source' | 'service',
        match_value: String(form.get('match_value') || '').trim(),
        assign_to_user_id: Number(form.get('assign_to_user_id')),
        is_active: true,
      })
      event.currentTarget.reset()
      toast.success('تمت إضافة قاعدة التعيين.')
      const response = await getCrmAssignmentRules()
      setRules(response.data.items)
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر حفظ قاعدة التعيين.'))
    } finally {
      setSavingRule(false)
    }
  }

  async function saveConfig(event: FormEvent) {
    event.preventDefault()
    setSavingConfig(true)
    setFormError(null)
    try {
      const response = await updateCrmSettingsConfig({
        discount_max_percent: config.discount_max_percent != null ? Number(config.discount_max_percent) : undefined,
        stale_lead_days: config.stale_lead_days != null ? Number(config.stale_lead_days) : undefined,
        new_lead_sla_minutes: config.new_lead_sla_minutes != null ? Number(config.new_lead_sla_minutes) : undefined,
        assignment_mode: config.assignment_mode,
      })
      setConfig(response.data.config)
      toast.success('تم حفظ إعدادات النظام.')
      await load()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر حفظ الإعدادات.'))
    } finally {
      setSavingConfig(false)
    }
  }

  async function addNamed(
    kind: 'source' | 'stage' | 'lost' | 'tag',
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const name = String(form.get('name') || '').trim()
    if (!name) return
    try {
      if (kind === 'source') await createCrmSource({ name })
      if (kind === 'stage') {
        await createCrmStage({
          name,
          probability: form.get('probability') ? Number(form.get('probability')) : 0,
        })
      }
      if (kind === 'lost') await createCrmLostReason({ name })
      if (kind === 'tag') await createCrmTag({ name, color: String(form.get('color') || '') || undefined })
      event.currentTarget.reset()
      toast.success('تمت الإضافة.')
      await load()
    } catch (caught) {
      toast.error(describeApiError(caught, 'تعذر الإضافة.'))
    }
  }

  if (loading) return <DashboardPanelSkeleton label="جاري تحميل الإعدادات..." />
  if (error) return <DashboardErrorState message={error} onRetry={() => void load()} />
  if (!data) return <DashboardEmptyState title="لا إعدادات." description="" />

  return (
    <div className="space-y-6">
      <DashboardSection title="إعدادات النظام" description="حدود الخصم، تأخير العملاء، SLA، ووضع التعيين.">
        {formError ? <FeedbackBanner kind="error">{formError}</FeedbackBanner> : null}
        <form onSubmit={(event) => void saveConfig(event)} className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2">
          <label className="space-y-1 text-sm">
            <span>أقصى خصم %</span>
            <input
              type="number"
              min="0"
              max="100"
              step="0.1"
              className={fieldClass}
              value={config.discount_max_percent ?? ''}
              onChange={(event) => setConfig((current) => ({ ...current, discount_max_percent: Number(event.target.value) }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span>أيام اعتبار العميل متأخراً</span>
            <input
              type="number"
              min="1"
              className={fieldClass}
              value={config.stale_lead_days ?? ''}
              onChange={(event) => setConfig((current) => ({ ...current, stale_lead_days: Number(event.target.value) }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span>SLA أول تواصل (دقائق)</span>
            <input
              type="number"
              min="1"
              className={fieldClass}
              value={config.new_lead_sla_minutes ?? ''}
              onChange={(event) => setConfig((current) => ({ ...current, new_lead_sla_minutes: Number(event.target.value) }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span>وضع التعيين</span>
            <select
              className={fieldClass}
              value={String(config.assignment_mode ?? 'manual')}
              onChange={(event) => setConfig((current) => ({ ...current, assignment_mode: event.target.value }))}
            >
              {Object.entries(CRM_ASSIGNMENT_MODE_LABELS).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>
          <div className="sm:col-span-2">
            <button type="submit" disabled={savingConfig} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
              {savingConfig ? 'جاري الحفظ...' : 'حفظ الإعدادات'}
            </button>
          </div>
        </form>
      </DashboardSection>

      <DashboardSection title="قواعد التعيين" description="عند اختيار التعيين حسب المصدر أو الخدمة تُطبَّق هذه القواعد تلقائياً.">
        {formError ? <FeedbackBanner kind="error">{formError}</FeedbackBanner> : null}
        <ul className="mb-4 space-y-2">
          {rules.length === 0 ? <li className="text-sm text-slate-500">لا قواعد بعد.</li> : null}
          {rules.map((rule) => (
            <li key={rule.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
              <span>
                {rule.name} · {rule.match_type}={rule.match_value} → {rule.assignee?.name ?? rule.assign_to_user_id}
                {rule.is_active ? '' : ' (متوقفة)'}
              </span>
              <button
                type="button"
                className="rounded-lg border border-red-200 px-3 py-1 text-red-700"
                onClick={() => {
                  void deleteCrmAssignmentRule(rule.id)
                    .then(async () => {
                      toast.success('تم حذف القاعدة.')
                      setRules((await getCrmAssignmentRules()).data.items)
                    })
                    .catch((caught) => setFormError(describeApiError(caught, 'تعذر الحذف.')))
                }}
              >
                حذف
              </button>
            </li>
          ))}
        </ul>
        <form onSubmit={(event) => void saveRule(event)} className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
          <input required name="name" placeholder="اسم القاعدة" className={fieldClass} />
          <select name="match_type" className={fieldClass} defaultValue="source">
            <option value="source">حسب المصدر (slug)</option>
            <option value="service">حسب الخدمة (id أو slug)</option>
          </select>
          <input required name="match_value" placeholder="قيمة المطابقة (مثال: website-contact)" className={fieldClass} />
          <select required name="assign_to_user_id" className={fieldClass} defaultValue="">
            <option value="" disabled>المندوب المعيّن</option>
            {team.map((member) => (
              <option key={member.id} value={member.id}>{member.name}</option>
            ))}
          </select>
          <div className="sm:col-span-2">
            <button type="submit" disabled={savingRule} className="min-h-11 rounded-xl bg-brand-primary px-4 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-60">
              {savingRule ? 'جاري الحفظ...' : 'إضافة قاعدة'}
            </button>
          </div>
        </form>
      </DashboardSection>

      <div className="grid gap-6 lg:grid-cols-2">
        <EditableList
          title="مصادر العملاء"
          empty="لا مصادر."
          items={data.sources.map((item: CrmSource) => ({
            id: item.id,
            label: item.name,
            meta: item.is_active === false ? 'غير نشط' : item.slug,
          }))}
          onAdd={(event) => void addNamed('source', event)}
          fields={<input required name="name" placeholder="اسم المصدر" className={fieldClass} />}
        />
        <EditableList
          title="مراحل خط الأنابيب"
          empty="لا مراحل."
          items={data.stages.map((item: CrmStage) => ({
            id: item.id,
            label: item.name,
            meta: `${item.probability}%${item.is_won ? ' · رابح' : ''}${item.is_lost ? ' · خسارة' : ''}`,
          }))}
          onAdd={(event) => void addNamed('stage', event)}
          fields={
            <>
              <input required name="name" placeholder="اسم المرحلة" className={fieldClass} />
              <input name="probability" type="number" min="0" max="100" placeholder="الاحتمال %" className={fieldClass} />
            </>
          }
        />
        <EditableList
          title="أسباب الخسارة"
          empty="لا أسباب."
          items={data.lost_reasons.map((item: CrmLostReason) => ({
            id: item.id,
            label: item.name,
            meta: item.slug,
          }))}
          onAdd={(event) => void addNamed('lost', event)}
          fields={<input required name="name" placeholder="سبب الخسارة" className={fieldClass} />}
        />
        <EditableList
          title="الوسوم"
          empty="لا وسوم."
          items={data.tags.map((item: CrmTag) => ({
            id: item.id,
            label: item.name,
            meta: item.color || item.slug,
          }))}
          onAdd={(event) => void addNamed('tag', event)}
          fields={
            <>
              <input required name="name" placeholder="اسم الوسم" className={fieldClass} />
              <input name="color" placeholder="لون (اختياري)" className={fieldClass} />
            </>
          }
        />
      </div>
    </div>
  )
}

function EditableList({
  title,
  empty,
  items,
  onAdd,
  fields,
}: {
  title: string
  empty: string
  items: Array<{ id: number; label: string; meta?: string | null }>
  onAdd: (event: FormEvent<HTMLFormElement>) => void
  fields: React.ReactNode
}) {
  return (
    <section className="rounded-2xl border bg-white p-4">
      <h2 className="mb-3 font-semibold">{title}</h2>
      {items.length === 0 ? (
        <DashboardEmptyState title={empty} description="أضف عنصراً من النموذج أدناه." />
      ) : (
        <ul className="mb-4 divide-y divide-slate-100 text-sm">
          {items.map((item) => (
            <li key={item.id} className="flex items-center justify-between gap-3 py-2">
              <span className="font-medium">{item.label}</span>
              {item.meta ? <span className="text-xs text-slate-500">{item.meta}</span> : null}
            </li>
          ))}
        </ul>
      )}
      <form onSubmit={onAdd} className="grid gap-2 sm:grid-cols-2">
        {fields}
        <button type="submit" className="min-h-11 rounded-xl border px-4 text-sm sm:col-span-2">
          إضافة
        </button>
      </form>
    </section>
  )
}
