import { FormEvent, useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import {
  createCrmTarget,
  deleteCrmTarget,
  getCrmTargetsProgress,
  getCrmTeam,
  type CrmTargetProgress,
  type CrmTeamMember,
} from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { CRM_TARGET_TYPE_LABELS, crmTargetTypeLabel, isCrmManager } from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function CrmTargetsPage() {
  const { user } = useAuth()
  const manager = isCrmManager(user?.role)
  const toast = useToast()
  const [items, setItems] = useState<CrmTargetProgress[]>([])
  const [team, setTeam] = useState<CrmTeamMember[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmTargetsProgress()
      setItems(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الأهداف.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    if (manager) {
      void getCrmTeam()
        .then((response) => setTeam(response.data.items))
        .catch(() => setTeam([]))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [manager])

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (saving) return
    const form = new FormData(event.currentTarget)
    setSaving(true)
    setFormError(null)
    try {
      await createCrmTarget({
        user_id: form.get('user_id') ? Number(form.get('user_id')) : undefined,
        period_type: String(form.get('period_type') || 'month') || undefined,
        period_start: String(form.get('period_start') || ''),
        period_end: String(form.get('period_end') || ''),
        target_type: String(form.get('target_type') || 'revenue'),
        target_value: Number(form.get('target_value') || 0),
      })
      setCreating(false)
      toast.success('تم إنشاء الهدف.')
      await load()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر إنشاء الهدف.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete(id: number) {
    try {
      await deleteCrmTarget(id)
      toast.success('تم حذف الهدف.')
      await load()
    } catch (caught) {
      toast.error(describeApiError(caught, 'تعذر الحذف.'))
    }
  }

  return (
    <DashboardSection
      title="الأهداف"
      description="متابعة تقدم أهداف المبيعات."
      action={
        manager ? (
          <button type="button" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white" onClick={() => setCreating(true)}>
            هدف جديد
          </button>
        ) : undefined
      }
    >
      {creating && manager ? (
        <form onSubmit={(event) => void handleCreate(event)} className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2">
          {formError ? (
            <div className="sm:col-span-2">
              <FeedbackBanner kind="error">{formError}</FeedbackBanner>
            </div>
          ) : null}
          <select name="user_id" className={fieldClass}>
            <option value="">المندوب (اختياري)</option>
            {team.map((member) => (
              <option key={member.id} value={member.id}>
                {member.name}
              </option>
            ))}
          </select>
          <select name="target_type" defaultValue="revenue" className={fieldClass}>
            {Object.entries(CRM_TARGET_TYPE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
          <select name="period_type" defaultValue="month" className={fieldClass}>
            <option value="month">شهري</option>
            <option value="quarter">ربع سنوي</option>
            <option value="year">سنوي</option>
          </select>
          <input required name="target_value" type="number" min="0" step="0.01" placeholder="قيمة الهدف *" className={fieldClass} />
          <input required name="period_start" type="date" className={fieldClass} />
          <input required name="period_end" type="date" className={fieldClass} />
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

      {loading ? <DashboardPanelSkeleton label="جاري تحميل الأهداف..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا أهداف." description={manager ? 'أنشئ هدفاً لبدء المتابعة.' : 'لم تُعيَّن لك أهداف بعد.'} />
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <ul className="space-y-3">
          {items.map((item) => {
            const percent = Math.min(100, Math.max(0, item.progress_percent ?? 0))
            const isMoney = item.target_type === 'revenue'
            return (
              <li key={item.id} className="rounded-2xl border bg-white p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold">{crmTargetTypeLabel(item.target_type)}</p>
                    <p className="text-sm text-slate-600">
                      {item.user?.name ?? 'فريق'} · {item.period_start} → {item.period_end}
                    </p>
                  </div>
                  <div className="text-left text-sm" dir="ltr">
                    <p className="font-medium">
                      {isMoney ? formatMoney(item.actual_value) : item.actual_value.toLocaleString('ar-SA')} /{' '}
                      {isMoney ? formatMoney(item.target_value) : item.target_value.toLocaleString('ar-SA')}
                    </p>
                    <p className="text-xs text-slate-500">{percent.toLocaleString('ar-SA')}%</p>
                  </div>
                </div>
                <div className="mt-3 h-3 overflow-hidden rounded-full bg-slate-100">
                  <div
                    className={`h-full rounded-full ${percent >= 100 ? 'bg-emerald-600' : percent >= 60 ? 'bg-amber-500' : 'bg-slate-700'}`}
                    style={{ width: `${percent}%` }}
                  />
                </div>
                {manager ? (
                  <button type="button" className="mt-3 text-xs text-red-700 hover:underline" onClick={() => void handleDelete(item.id)}>
                    حذف الهدف
                  </button>
                ) : null}
              </li>
            )
          })}
        </ul>
      ) : null}
    </DashboardSection>
  )
}
