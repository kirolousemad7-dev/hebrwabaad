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
import { useToast } from '../../context/ToastContext'
import {
  bulkCrmLeads,
  createCrmLead,
  createCrmSavedFilter,
  deleteCrmSavedFilter,
  exportCrmEntity,
  getCrmLeads,
  getCrmPipelineStages,
  getCrmSavedFilters,
  getCrmTeam,
  importCrmLeads,
  type CrmLead,
  type CrmLeadListData,
  type CrmSavedFilter,
  type CrmStage,
  type CrmTeamMember,
} from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import {
  CRM_PRIORITY_LABELS,
  CRM_STATUS_LABELS,
  crmPriorityLabel,
  crmStatusLabel,
} from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function CrmLeadsPage() {
  const toast = useToast()
  const [searchParams] = useSearchParams()
  const [filters, setFilters] = useState({
    q: '',
    status: '',
    stage_id: '',
    assigned_to: searchParams.get('assigned') === 'unassigned' ? 'unassigned' : '',
    priority: '',
    stale: searchParams.get('stale') === '1',
    page: 1,
  })
  const [list, setList] = useState<CrmLeadListData | null>(null)
  const [stages, setStages] = useState<CrmStage[]>([])
  const [team, setTeam] = useState<CrmTeamMember[]>([])
  const [savedFilters, setSavedFilters] = useState<CrmSavedFilter[]>([])
  const [selected, setSelected] = useState<number[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [bulkAction, setBulkAction] = useState('')
  const [bulkValue, setBulkValue] = useState('')
  const [busyBulk, setBusyBulk] = useState(false)

  async function loadSaved() {
    try {
      const response = await getCrmSavedFilters()
      setSavedFilters(response.data.items.filter((item) => !item.entity || item.entity === 'leads'))
    } catch {
      setSavedFilters([])
    }
  }

  async function load() {
    setLoading(true)
    setError(null)

    try {
      const response = await getCrmLeads({
        q: filters.q || undefined,
        status: filters.status || undefined,
        stage_id: filters.stage_id || undefined,
        assigned_to: filters.assigned_to && filters.assigned_to !== 'unassigned' ? filters.assigned_to : undefined,
        unassigned: filters.assigned_to === 'unassigned' ? true : undefined,
        needs_attention: filters.stale ? true : undefined,
        priority: filters.priority || undefined,
        page: filters.page,
        per_page: 15,
      })
      setList(response.data)
      setSelected([])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل العملاء المحتملين.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void getCrmPipelineStages()
      .then((response) => setStages(response.data.items))
      .catch(() => setStages([]))
    void getCrmTeam()
      .then((response) => setTeam(response.data.items))
      .catch(() => setTeam([]))
    void loadSaved()
  }, [])

  useEffect(() => {
    const handle = window.setTimeout(() => {
      void load()
    }, filters.q ? 250 : 0)

    return () => window.clearTimeout(handle)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.q, filters.status, filters.stage_id, filters.assigned_to, filters.priority, filters.stale, filters.page])

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (saving) return

    const form = new FormData(event.currentTarget)
    setSaving(true)
    setFormError(null)

    try {
      await createCrmLead({
        full_name: String(form.get('full_name') || '').trim(),
        company_name: String(form.get('company_name') || '').trim() || undefined,
        phone: String(form.get('phone') || '').trim() || undefined,
        whatsapp: String(form.get('whatsapp') || '').trim() || undefined,
        email: String(form.get('email') || '').trim() || undefined,
        city: String(form.get('city') || '').trim() || undefined,
        priority: String(form.get('priority') || '') || undefined,
        stage_id: form.get('stage_id') ? Number(form.get('stage_id')) : undefined,
        assigned_to: form.get('assigned_to') ? Number(form.get('assigned_to')) : undefined,
        notes: String(form.get('notes') || '').trim() || undefined,
        estimated_budget: form.get('estimated_budget') ? Number(form.get('estimated_budget')) : undefined,
      })
      setCreating(false)
      toast.success('تم إنشاء العميل المحتمل.')
      await load()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر إنشاء العميل المحتمل.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleImport(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const fileInput = form.elements.namedItem('file') as HTMLInputElement
    const file = fileInput.files?.[0]
    if (!file) return
    setSaving(true)
    try {
      const response = await importCrmLeads(file)
      toast.success(`تم استيراد ${response.data.created} عميل · تكرار: ${response.data.duplicates}`)
      form.reset()
      await load()
    } catch (caught) {
      toast.error(describeApiError(caught, 'تعذر الاستيراد.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleSaveFilter() {
    const name = window.prompt('اسم الفلتر المحفوظ')
    if (!name?.trim()) return
    try {
      await createCrmSavedFilter({
        name: name.trim(),
        entity: 'leads',
        filters: {
          q: filters.q,
          status: filters.status,
          stage_id: filters.stage_id,
          assigned_to: filters.assigned_to,
          priority: filters.priority,
          stale: filters.stale,
        },
      })
      toast.success('تم حفظ الفلتر.')
      await loadSaved()
    } catch (caught) {
      toast.error(describeApiError(caught, 'تعذر حفظ الفلتر.'))
    }
  }

  function applySaved(filter: CrmSavedFilter) {
    const f = filter.filters as Record<string, unknown>
    setFilters({
      q: String(f.q ?? ''),
      status: String(f.status ?? ''),
      stage_id: String(f.stage_id ?? ''),
      assigned_to: String(f.assigned_to ?? ''),
      priority: String(f.priority ?? ''),
      stale: Boolean(f.stale),
      page: 1,
    })
  }

  async function runBulk() {
    if (selected.length === 0 || !bulkAction) return
    setBusyBulk(true)
    try {
      const payload: Parameters<typeof bulkCrmLeads>[0] = {
        lead_ids: selected,
        action: bulkAction as 'assign' | 'stage' | 'priority' | 'archive',
      }
      if (bulkAction === 'assign') payload.assigned_to = Number(bulkValue)
      if (bulkAction === 'stage') payload.stage_id = Number(bulkValue)
      if (bulkAction === 'priority') payload.priority = bulkValue
      await bulkCrmLeads(payload)
      toast.success('تم تنفيذ الإجراء الجماعي.')
      await load()
    } catch (caught) {
      toast.error(describeApiError(caught, 'تعذر تنفيذ الإجراء الجماعي.'))
    } finally {
      setBusyBulk(false)
    }
  }

  const items = list?.items ?? []
  const meta = list?.meta
  const allSelected = items.length > 0 && items.every((item) => selected.includes(item.id))

  return (
    <DashboardSection
      title="العملاء المحتملون"
      description="بحث وتصفية واستيراد وتصدير وإجراءات جماعية."
      action={
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            className="min-h-11 rounded-xl border px-4 text-sm"
            onClick={() => void exportCrmEntity('leads', 'csv').catch((caught) => toast.error(describeApiError(caught, 'تعذر التصدير.')))}
          >
            تصدير CSV
          </button>
          <button
            type="button"
            className="min-h-11 rounded-xl border px-4 text-sm"
            onClick={() => void exportCrmEntity('leads', 'xlsx').catch((caught) => toast.error(describeApiError(caught, 'تعذر التصدير.')))}
          >
            تصدير Excel
          </button>
          <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => void handleSaveFilter()}>
            حفظ الفلتر
          </button>
          <button
            type="button"
            className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white"
            onClick={() => {
              setFormError(null)
              setCreating(true)
            }}
          >
            عميل محتمل جديد
          </button>
        </div>
      }
    >
      <div className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-6">
        <input
          value={filters.q}
          onChange={(event) => setFilters((current) => ({ ...current, q: event.target.value, page: 1 }))}
          placeholder="بحث بالاسم / الهاتف / البريد"
          className={fieldClass}
        />
        <select
          value={filters.status}
          onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value, page: 1 }))}
          className={fieldClass}
        >
          <option value="">كل الحالات</option>
          {Object.entries(CRM_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
        <select
          value={filters.stage_id}
          onChange={(event) => setFilters((current) => ({ ...current, stage_id: event.target.value, page: 1 }))}
          className={fieldClass}
        >
          <option value="">كل المراحل</option>
          {stages.map((stage) => (
            <option key={stage.id} value={stage.id}>
              {stage.name}
            </option>
          ))}
        </select>
        <select
          value={filters.priority}
          onChange={(event) => setFilters((current) => ({ ...current, priority: event.target.value, page: 1 }))}
          className={fieldClass}
        >
          <option value="">كل الأولويات</option>
          {Object.entries(CRM_PRIORITY_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
        <select
          value={filters.assigned_to}
          onChange={(event) => setFilters((current) => ({ ...current, assigned_to: event.target.value, page: 1 }))}
          className={fieldClass}
        >
          <option value="">كل المكلّفين</option>
          <option value="unassigned">غير معيّن</option>
          {team.map((member) => (
            <option key={member.id} value={member.id}>
              {member.name}
            </option>
          ))}
        </select>
        <label className="flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm">
          <input
            type="checkbox"
            checked={filters.stale}
            onChange={(event) => setFilters((current) => ({ ...current, stale: event.target.checked, page: 1 }))}
          />
          متأخرون / يحتاجون انتباه
        </label>
      </div>

      {savedFilters.length > 0 ? (
        <div className="flex flex-wrap gap-2">
          {savedFilters.map((filter) => (
            <div key={filter.id} className="flex items-center gap-1 rounded-full border bg-white px-2 py-1 text-xs">
              <button type="button" className="hover:underline" onClick={() => applySaved(filter)}>
                {filter.name}
              </button>
              <button
                type="button"
                className="text-red-600"
                onClick={() =>
                  void deleteCrmSavedFilter(filter.id)
                    .then(() => loadSaved())
                    .catch((caught) => toast.error(describeApiError(caught, 'تعذر الحذف.')))
                }
              >
                ×
              </button>
            </div>
          ))}
        </div>
      ) : null}

      <form onSubmit={(event) => void handleImport(event)} className="flex flex-wrap items-center gap-2 rounded-2xl border bg-white p-3 text-sm">
        <span className="font-medium">استيراد:</span>
        <input name="file" type="file" accept=".csv,.txt,.xlsx,.xls" className="text-sm" />
        <button type="submit" disabled={saving} className="min-h-10 rounded-xl border px-3 disabled:opacity-60">
          رفع الملف
        </button>
      </form>

      {selected.length > 0 ? (
        <div className="flex flex-wrap items-end gap-2 rounded-2xl border border-amber-200 bg-amber-50/50 p-3">
          <p className="w-full text-sm font-medium">محدد: {selected.length.toLocaleString('ar-SA')}</p>
          <select value={bulkAction} onChange={(event) => { setBulkAction(event.target.value); setBulkValue('') }} className={fieldClass}>
            <option value="">إجراء جماعي</option>
            <option value="assign">تعيين</option>
            <option value="stage">تغيير مرحلة</option>
            <option value="priority">أولوية</option>
            <option value="archive">أرشفة</option>
          </select>
          {bulkAction === 'assign' ? (
            <select value={bulkValue} onChange={(event) => setBulkValue(event.target.value)} className={fieldClass}>
              <option value="">المندوب</option>
              {team.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.name}
                </option>
              ))}
            </select>
          ) : null}
          {bulkAction === 'stage' ? (
            <select value={bulkValue} onChange={(event) => setBulkValue(event.target.value)} className={fieldClass}>
              <option value="">المرحلة</option>
              {stages.map((stage) => (
                <option key={stage.id} value={stage.id}>
                  {stage.name}
                </option>
              ))}
            </select>
          ) : null}
          {bulkAction === 'priority' ? (
            <select value={bulkValue} onChange={(event) => setBulkValue(event.target.value)} className={fieldClass}>
              <option value="">الأولوية</option>
              {Object.entries(CRM_PRIORITY_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          ) : null}
          <button
            type="button"
            disabled={busyBulk || !bulkAction || (bulkAction !== 'archive' && !bulkValue)}
            className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60"
            onClick={() => void runBulk()}
          >
            تنفيذ
          </button>
        </div>
      ) : null}

      {creating ? (
        <form onSubmit={(event) => void handleCreate(event)} className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2">
          {formError ? (
            <div className="sm:col-span-2">
              <FeedbackBanner kind="error">{formError}</FeedbackBanner>
            </div>
          ) : null}
          <input required name="full_name" placeholder="الاسم الكامل *" className={fieldClass} />
          <input name="company_name" placeholder="الشركة" className={fieldClass} />
          <input name="phone" placeholder="الهاتف" dir="ltr" className={fieldClass} />
          <input name="whatsapp" placeholder="واتساب" dir="ltr" className={fieldClass} />
          <input name="email" type="email" placeholder="البريد" dir="ltr" className={fieldClass} />
          <input name="city" placeholder="المدينة" className={fieldClass} />
          <input name="estimated_budget" type="number" min="0" step="0.01" placeholder="الميزانية التقديرية" className={fieldClass} />
          <select name="priority" defaultValue="MEDIUM" className={fieldClass}>
            {Object.entries(CRM_PRIORITY_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
          <select name="stage_id" className={fieldClass}>
            <option value="">المرحلة الافتراضية</option>
            {stages.map((stage) => (
              <option key={stage.id} value={stage.id}>
                {stage.name}
              </option>
            ))}
          </select>
          <select name="assigned_to" className={fieldClass}>
            <option value="">تعيين لاحقاً</option>
            {team.map((member) => (
              <option key={member.id} value={member.id}>
                {member.name}
              </option>
            ))}
          </select>
          <textarea name="notes" placeholder="ملاحظات" rows={3} className={`${fieldClass} sm:col-span-2`} />
          <div className="flex gap-2 sm:col-span-2">
            <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
              {saving ? 'جاري الحفظ...' : 'إنشاء'}
            </button>
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setCreating(false)}>
              إلغاء
            </button>
          </div>
        </form>
      ) : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل العملاء المحتملين..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState
          title="لا يوجد عملاء محتملون."
          description="أضف أول عميل محتمل لبدء خط المبيعات."
          action={
            <button type="button" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white" onClick={() => setCreating(true)}>
              إضافة عميل
            </button>
          }
        />
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <>
          <div className="overflow-x-auto rounded-2xl border bg-white">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-right">
                <tr>
                  <th className="px-3 py-2">
                    <input
                      type="checkbox"
                      checked={allSelected}
                      onChange={(event) => {
                        if (event.target.checked) setSelected(items.map((item) => item.id))
                        else setSelected([])
                      }}
                    />
                  </th>
                  <th className="px-3 py-2">المرجع</th>
                  <th className="px-3 py-2">الاسم</th>
                  <th className="px-3 py-2">الحالة</th>
                  <th className="px-3 py-2">الأولوية</th>
                  <th className="px-3 py-2">المرحلة</th>
                  <th className="px-3 py-2">العمر</th>
                  <th className="px-3 py-2">المكلّف</th>
                  <th className="px-3 py-2">القيمة</th>
                  <th className="px-3 py-2">إجراء</th>
                </tr>
              </thead>
              <tbody>
                {items.map((lead: CrmLead) => (
                  <tr key={lead.id} className={`border-t ${lead.needs_attention ? 'bg-amber-50/40' : ''}`}>
                    <td className="px-3 py-2">
                      <input
                        type="checkbox"
                        checked={selected.includes(lead.id)}
                        onChange={(event) => {
                          if (event.target.checked) setSelected((current) => [...current, lead.id])
                          else setSelected((current) => current.filter((id) => id !== lead.id))
                        }}
                      />
                    </td>
                    <td className="px-3 py-2 font-mono text-xs" dir="ltr">
                      {lead.reference}
                    </td>
                    <td className="px-3 py-2">
                      <div className="font-medium">{lead.full_name}</div>
                      <div className="text-xs text-slate-500">{lead.company_name || lead.phone || '—'}</div>
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge status={lead.status} label={crmStatusLabel(lead.status)} />
                    </td>
                    <td className="px-3 py-2">{crmPriorityLabel(lead.priority)}</td>
                    <td className="px-3 py-2">{lead.stage?.name ?? '—'}</td>
                    <td className="px-3 py-2 text-xs">
                      {lead.age_days != null ? `${lead.age_days}ي` : '—'}
                      {lead.needs_attention ? <span className="mr-1 text-amber-700">!</span> : null}
                    </td>
                    <td className="px-3 py-2">{lead.assignee?.name ?? '—'}</td>
                    <td className="px-3 py-2">
                      {lead.deal_value != null
                        ? formatMoney(lead.deal_value)
                        : lead.estimated_budget != null
                          ? formatMoney(lead.estimated_budget)
                          : '—'}
                    </td>
                    <td className="px-3 py-2">
                      <Link className="rounded-lg border px-2 py-1" to={`/crm/leads/${lead.id}`}>
                        فتح
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {meta && meta.last_page > 1 ? (
            <div className="flex items-center justify-between gap-3 text-sm">
              <p>
                صفحة {meta.current_page.toLocaleString('ar-SA')} من {meta.last_page.toLocaleString('ar-SA')} ·{' '}
                {meta.total.toLocaleString('ar-SA')}
              </p>
              <div className="flex gap-2">
                <button
                  type="button"
                  disabled={meta.current_page <= 1}
                  className="rounded-lg border px-3 py-1 disabled:opacity-50"
                  onClick={() => setFilters((current) => ({ ...current, page: current.page - 1 }))}
                >
                  السابق
                </button>
                <button
                  type="button"
                  disabled={meta.current_page >= meta.last_page}
                  className="rounded-lg border px-3 py-1 disabled:opacity-50"
                  onClick={() => setFilters((current) => ({ ...current, page: current.page + 1 }))}
                >
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
