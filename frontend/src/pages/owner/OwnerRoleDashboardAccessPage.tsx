import { useEffect, useMemo, useState } from 'react'
import {
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  getRoleDashboardAccessMatrix,
  updateRoleDashboardAccess,
  type DashboardModuleCatalogItem,
  type RoleDashboardAccessRow,
} from '../../services/roleDashboardAccess'
import { describeApiError } from '../../utils/errors'

const ROLE_LABELS: Record<string, string> = {
  ADMIN_MANAGER: 'مدير النظام',
  ACCOUNT_MANAGER: 'مدير حسابات',
  HR: 'الموارد البشرية',
  SALES_MANAGER: 'مدير مبيعات',
  SALES_REPRESENTATIVE: 'مندوب مبيعات',
  WEB_DEVELOPER: 'مطوّر ويب',
  GRAPHIC_DESIGNER: 'مصمم جرافيك',
  VIDEO_EDITOR: 'مونتير',
  MARKETING_SPECIALIST: 'أخصائي تسويق',
  EVENT_SPECIALIST: 'أخصائي فعاليات',
  PRINTING_SPECIALIST: 'أخصائي طباعة',
  MEDIA_BUYER: 'ميديا باير',
}

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function OwnerRoleDashboardAccessPage() {
  const [catalog, setCatalog] = useState<DashboardModuleCatalogItem[]>([])
  const [roles, setRoles] = useState<RoleDashboardAccessRow[]>([])
  const [selectedRole, setSelectedRole] = useState('')
  const [draft, setDraft] = useState<Record<string, boolean>>({})
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)

    try {
      const response = await getRoleDashboardAccessMatrix()
      setCatalog(response.data.catalog)
      setRoles(response.data.roles)
      setSelectedRole((current) => {
        if (current && response.data.roles.some((row) => row.role === current)) {
          return current
        }

        return response.data.roles[0]?.role ?? ''
      })
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل صلاحيات لوحة التحكم.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  useEffect(() => {
    const row = roles.find((item) => item.role === selectedRole)
    if (row) {
      setDraft({ ...row.modules })
      setNotice(null)
    }
  }, [selectedRole, roles])

  const ownerModules = useMemo(
    () => catalog.filter((item) => item.shell === 'owner'),
    [catalog],
  )
  const workspaceModules = useMemo(
    () => catalog.filter((item) => item.shell === 'workspace'),
    [catalog],
  )

  async function handleSave() {
    if (!selectedRole) {
      return
    }

    setSaving(true)
    setError(null)
    setNotice(null)

    try {
      const response = await updateRoleDashboardAccess(selectedRole, draft)
      setRoles((prev) =>
        prev.map((row) =>
          row.role === selectedRole ? { ...row, modules: response.data.modules } : row,
        ),
      )
      setDraft({ ...response.data.modules })
      setNotice('تم حفظ صلاحيات لوحة التحكم بنجاح.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ صلاحيات لوحة التحكم.'))
    } finally {
      setSaving(false)
    }
  }

  function toggleModule(key: string) {
    setDraft((prev) => ({ ...prev, [key]: !prev[key] }))
    setNotice(null)
  }

  function renderModuleGroup(title: string, items: DashboardModuleCatalogItem[]) {
    if (items.length === 0) {
      return null
    }

    return (
      <div className="space-y-3">
        <h3 className="text-sm font-semibold text-slate-800">{title}</h3>
        <ul className="grid gap-2 sm:grid-cols-2">
          {items.map((item) => (
            <li key={item.key}>
              <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:border-slate-300">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={draft[item.key] === true}
                  onChange={() => toggleModule(item.key)}
                  disabled={saving || loading}
                />
                <span>
                  <span className="block font-medium text-slate-900">{item.label}</span>
                  {item.description ? (
                    <span className="mt-0.5 block text-xs text-slate-500">{item.description}</span>
                  ) : null}
                </span>
              </label>
            </li>
          ))}
        </ul>
      </div>
    )
  }

  if (loading) {
    return <DashboardPanelSkeleton label="جاري تحميل صلاحيات لوحة التحكم..." />
  }

  if (error && roles.length === 0) {
    return <DashboardErrorState message={error} onRetry={() => void load()} />
  }

  return (
    <div className="space-y-6" dir="rtl">
      <DashboardSection
        title="صلاحيات لوحة التحكم"
        description="حدد الوحدات التي يظهرها كل دور في لوحة التحكم. الموظفون يرثون الإعداد من دورهم مباشرة."
      >
        {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

        <div className="max-w-md space-y-2">
          <label htmlFor="role-dashboard-role" className="block text-sm font-medium text-slate-700">
            الدور
          </label>
          <select
            id="role-dashboard-role"
            className={fieldClass}
            value={selectedRole}
            onChange={(event) => setSelectedRole(event.target.value)}
            disabled={saving}
          >
            {roles.map((row) => (
              <option key={row.role} value={row.role}>
                {ROLE_LABELS[row.role] ?? row.role}
              </option>
            ))}
          </select>
        </div>

        <div className="space-y-6 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
          <p className="text-sm text-slate-600">
            اختر ما يمكن لهذا الدور رؤيته في لوحة التحكم. صلاحيات الإجراءات التفصيلية تبقى كما هي.
          </p>
          {renderModuleGroup('وحدات المالك / الإدارة', ownerModules)}
          {renderModuleGroup('وحدات مساحة الموظف', workspaceModules)}
        </div>

        <div className="flex flex-wrap gap-3">
          <button
            type="button"
            onClick={() => void handleSave()}
            disabled={saving || !selectedRole}
            className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
          >
            {saving ? 'جاري الحفظ...' : 'حفظ التغييرات'}
          </button>
        </div>
      </DashboardSection>
    </div>
  )
}
