import { useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { getCalendarAssignees, type CalendarAssignee } from '../../services/calendar'
import { getEmployees } from '../../services/employees'
import {
  assignEmployeeToDepartment,
  createDepartment,
  deleteDepartment,
  getDepartments,
  updateDepartment,
  type Department,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type FormState = {
  name: string
  description: string
  manager_id: string
}

const emptyForm = (): FormState => ({ name: '', description: '', manager_id: '' })

export function OwnerDepartmentsPage() {
  const [items, setItems] = useState<Department[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [form, setForm] = useState<FormState | null>(null)
  const [editing, setEditing] = useState<Department | null>(null)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [managers, setManagers] = useState<CalendarAssignee[]>([])
  const [employees, setEmployees] = useState<Array<{ id: number; name: string }>>([])
  const [assignUserId, setAssignUserId] = useState('')
  const [assignDepartmentId, setAssignDepartmentId] = useState('')
  const [assigning, setAssigning] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getDepartments()
      setItems(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الأقسام.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    void getCalendarAssignees()
      .then((response) => setManagers(response.data.items ?? []))
      .catch(() => setManagers([]))
    void getEmployees({ is_active: 'true', page: 1 })
      .then((response) =>
        setEmployees((response.data.items ?? []).map((row) => ({ id: row.id, name: row.name }))),
      )
      .catch(() => setEmployees([]))
  }, [])

  function openCreate() {
    setEditing(null)
    setFormError(null)
    setNotice(null)
    setForm(emptyForm())
  }

  function openEdit(department: Department) {
    setEditing(department)
    setFormError(null)
    setNotice(null)
    setForm({
      name: department.name,
      description: department.description ?? '',
      manager_id: department.manager_id ? String(department.manager_id) : '',
    })
  }

  async function handleSave() {
    if (!form || saving) return
    if (!form.name.trim()) {
      setFormError('اسم القسم مطلوب.')
      return
    }

    setSaving(true)
    setFormError(null)
    try {
      const payload = {
        name: form.name.trim(),
        description: form.description.trim() || null,
        manager_id: form.manager_id ? Number(form.manager_id) : null,
      }
      if (editing) {
        await updateDepartment(editing.id, payload)
        setNotice('تم تحديث القسم.')
      } else {
        await createDepartment(payload)
        setNotice('تم إنشاء القسم.')
      }
      setForm(null)
      setEditing(null)
      await load()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر حفظ القسم.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleArchive(department: Department) {
    if (!window.confirm(`أرشفة القسم «${department.name}»؟`)) return
    try {
      await deleteDepartment(department.id)
      setNotice('تم أرشفة القسم.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر أرشفة القسم.'))
    }
  }

  async function handleAssign() {
    if (!assignUserId || assigning) return
    setAssigning(true)
    setError(null)
    try {
      await assignEmployeeToDepartment(
        Number(assignUserId),
        assignDepartmentId ? Number(assignDepartmentId) : null,
      )
      setNotice('تم تعيين الموظف للقسم.')
      setAssignUserId('')
      setAssignDepartmentId('')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تعيين الموظف.'))
    } finally {
      setAssigning(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">الأقسام</h1>
          <p className="mt-1 text-sm text-slate-600">إدارة أقسام الفريق وتعيين الموظفين.</p>
        </div>
        <button
          type="button"
          onClick={openCreate}
          className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm font-medium text-white"
        >
          قسم جديد
        </button>
      </header>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <DashboardSection title="تعيين موظف" description="ربط موظف بقسم نشط أو إزالة التعيين.">
        <div className="grid gap-2 sm:grid-cols-3">
          <select
            aria-label="الموظف"
            value={assignUserId}
            onChange={(event) => setAssignUserId(event.target.value)}
            className={fieldClass}
          >
            <option value="">اختر موظفاً</option>
            {(employees.length > 0 ? employees : managers).map((row) => (
              <option key={row.id} value={row.id}>
                {row.name}
              </option>
            ))}
          </select>
          <select
            aria-label="القسم"
            value={assignDepartmentId}
            onChange={(event) => setAssignDepartmentId(event.target.value)}
            className={fieldClass}
          >
            <option value="">بدون قسم</option>
            {items.filter((row) => row.is_active).map((row) => (
              <option key={row.id} value={row.id}>
                {row.name}
              </option>
            ))}
          </select>
          <button
            type="button"
            disabled={!assignUserId || assigning}
            onClick={() => void handleAssign()}
            className="min-h-10 rounded-lg border border-slate-300 px-3 text-sm disabled:opacity-50"
          >
            تعيين
          </button>
        </div>
      </DashboardSection>

      {loading ? <DashboardPanelSkeleton label="جاري تحميل الأقسام..." /> : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}
      {!loading && items.length === 0 && !error ? (
        <DashboardEmptyState title="لا توجد أقسام بعد." description="أنشئ قسماً لبدء تنظيم الفريق." />
      ) : null}

      {!loading && items.length > 0 ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {items.map((department) => (
            <article
              key={department.id}
              className={`rounded-2xl border p-4 ${
                department.is_active ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50 opacity-80'
              }`}
            >
              <div className="flex items-start justify-between gap-2">
                <div>
                  <h2 className="font-semibold text-slate-900">{department.name}</h2>
                  <p className="mt-1 text-xs text-slate-500">
                    {department.manager?.name ?? 'بدون مدير'} ·{' '}
                    {department.employees_count.toLocaleString('ar-SA')} موظف
                  </p>
                </div>
                {!department.is_active ? (
                  <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">مؤرشف</span>
                ) : null}
              </div>
              {department.description ? (
                <p className="mt-2 line-clamp-3 text-sm text-slate-600">{department.description}</p>
              ) : null}
              <div className="mt-3 flex flex-wrap gap-2">
                <button
                  type="button"
                  className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                  onClick={() => openEdit(department)}
                >
                  تعديل
                </button>
                {department.is_active ? (
                  <button
                    type="button"
                    className="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-800"
                    onClick={() => void handleArchive(department)}
                  >
                    أرشفة
                  </button>
                ) : null}
              </div>
            </article>
          ))}
        </div>
      ) : null}

      {form ? (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center">
          <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
            <h2 className="text-lg font-semibold">{editing ? 'تعديل القسم' : 'قسم جديد'}</h2>
            {formError ? <div className="mt-3"><FeedbackBanner kind="error">{formError}</FeedbackBanner></div> : null}
            <div className="mt-4 space-y-3">
              <label className="block text-sm">
                الاسم
                <input
                  value={form.name}
                  onChange={(event) => setForm({ ...form, name: event.target.value })}
                  className={`mt-1 ${fieldClass}`}
                />
              </label>
              <label className="block text-sm">
                الوصف
                <textarea
                  value={form.description}
                  onChange={(event) => setForm({ ...form, description: event.target.value })}
                  rows={3}
                  className={`mt-1 ${fieldClass}`}
                />
              </label>
              <label className="block text-sm">
                المدير
                <select
                  value={form.manager_id}
                  onChange={(event) => setForm({ ...form, manager_id: event.target.value })}
                  className={`mt-1 ${fieldClass}`}
                >
                  <option value="">بدون مدير</option>
                  {managers.map((row) => (
                    <option key={row.id} value={row.id}>
                      {row.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm"
                onClick={() => {
                  setForm(null)
                  setEditing(null)
                }}
              >
                إلغاء
              </button>
              <button
                type="button"
                disabled={saving}
                className="min-h-10 rounded-lg bg-slate-900 px-4 text-sm text-white disabled:opacity-50"
                onClick={() => void handleSave()}
              >
                حفظ
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
