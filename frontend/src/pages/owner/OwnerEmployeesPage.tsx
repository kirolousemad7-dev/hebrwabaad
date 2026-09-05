import { useEffect, useState } from 'react'
import { EmployeeDetails } from '../../components/employees/EmployeeDetails'
import { EmployeeFilters } from '../../components/employees/EmployeeFilters'
import { EmployeeForm } from '../../components/employees/EmployeeForm'
import { emptyEmployeeForm, formFromEmployee, type EmployeeFormState } from '../../components/employees/employeeFormState'
import { EmployeeTable } from '../../components/employees/EmployeeTable'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  createEmployee,
  getEmployees,
  setEmployeeActive,
  updateEmployee,
  type EmployeeListFilters,
  type EmployeeWritePayload,
} from '../../services/employees'
import type { Employee, EmployeeListData } from '../../types/api'
import { describeApiError } from '../../utils/errors'

export function OwnerEmployeesPage() {
  const [filters, setFilters] = useState<EmployeeListFilters>({
    q: '',
    role: '',
    is_active: '',
    sort: 'created_at',
    direction: 'desc',
    page: 1,
  })
  const [list, setList] = useState<EmployeeListData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [form, setForm] = useState<EmployeeFormState | null>(null)
  const [editing, setEditing] = useState<Employee | null>(null)
  const [details, setDetails] = useState<Employee | null>(null)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [pendingDeactivate, setPendingDeactivate] = useState<Employee | null>(null)

  async function load() {
    setLoading(true)
    setError(null)

    try {
      const response = await getEmployees(filters)
      setList(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الموظفين.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    const handle = window.setTimeout(() => {
      void load()
    }, filters.q ? 250 : 0)

    return () => window.clearTimeout(handle)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.q, filters.role, filters.is_active, filters.sort, filters.direction, filters.page])

  function openCreate() {
    setEditing(null)
    setDetails(null)
    setFormError(null)
    setNotice(null)
    setForm(emptyEmployeeForm())
  }

  function openEdit(employee: Employee) {
    setDetails(null)
    setEditing(employee)
    setFormError(null)
    setNotice(null)
    setForm(formFromEmployee(employee))
  }

  async function handleSave(payload: EmployeeWritePayload) {
    if (saving) {
      return
    }

    if (payload.password && payload.password !== payload.password_confirmation) {
      setFormError('تأكيد كلمة المرور غير مطابق.')
      return
    }

    if (editing && payload.role !== editing.role) {
      const confirmed = window.confirm('سيتم تغيير صلاحيات هذا الموظف. هل تريد المتابعة؟')
      if (!confirmed) {
        return
      }
    }

    setSaving(true)
    setFormError(null)

    try {
      if (editing) {
        const response = await updateEmployee(editing.id, payload)
        setNotice('تم تحديث بيانات الموظف.')
        setDetails(response.data)
      } else {
        await createEmployee(payload)
        setNotice('تم إنشاء حساب الموظف.')
      }
      setForm(null)
      setEditing(null)
      await load()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر حفظ الموظف.'))
    } finally {
      setSaving(false)
    }
  }

  async function applyStatus(employee: Employee, isActive: boolean) {
    setBusyId(employee.id)
    setNotice(null)

    try {
      const response = await setEmployeeActive(employee.id, isActive)
      setNotice(isActive ? 'تم تنشيط الحساب.' : 'تم تعطيل الحساب.')
      if (details?.id === employee.id) {
        setDetails(response.data)
      }
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث حالة الحساب.'))
    } finally {
      setBusyId(null)
      setPendingDeactivate(null)
    }
  }

  function handleToggleActive(employee: Employee) {
    if (employee.is_active) {
      setPendingDeactivate(employee)
      return
    }

    void applyStatus(employee, true)
  }

  const hasFilters = Boolean(filters.q?.trim() || filters.role || filters.is_active)
  const meta = list?.meta

  return (
    <section className="space-y-6">
      <header className="flex min-w-0 flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold">إدارة الموظفين</h1>
          <p className="text-sm text-slate-600">إنشاء حسابات الفريق وتعيين الأدوار دون المساس بحساب المالك.</p>
        </div>
        <button
          type="button"
          onClick={openCreate}
          className="min-h-11 rounded-lg bg-slate-900 px-4 text-sm text-white"
        >
          إضافة موظف
        </button>
      </header>

      <EmployeeFilters filters={filters} onChange={setFilters} />

      {notice ? <FeedbackBanner kind="warning">{notice}</FeedbackBanner> : null}

      {form ? (
        <EmployeeForm
          title={editing ? 'تعديل موظف' : 'إضافة موظف'}
          form={form}
          isCreate={editing === null}
          saving={saving}
          error={formError}
          onChange={setForm}
          onSubmit={(payload) => void handleSave(payload)}
          onCancel={() => {
            setForm(null)
            setEditing(null)
            setFormError(null)
          }}
        />
      ) : null}

      {details && !form ? (
        <EmployeeDetails employee={details} onClose={() => setDetails(null)} onEdit={() => openEdit(details)} />
      ) : null}

      {pendingDeactivate ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-900">
          <p className="font-medium">تعطيل {pendingDeactivate.name}؟</p>
          <p className="mt-1">لن يتمكن من تسجيل الدخول أو استخدام واجهات الموظفين حتى يُعاد تنشيط الحساب.</p>
          <div className="mt-4 flex flex-wrap gap-3">
            <button
              type="button"
              disabled={busyId === pendingDeactivate.id}
              onClick={() => void applyStatus(pendingDeactivate, false)}
              className="rounded-lg bg-red-800 px-4 py-2 text-white disabled:opacity-60"
            >
              تأكيد التعطيل
            </button>
            <button type="button" onClick={() => setPendingDeactivate(null)} className="rounded-lg border border-red-300 px-4 py-2">
              إلغاء
            </button>
          </div>
        </div>
      ) : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل الموظفين..." /> : null}

      {error && !loading ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && list && list.items.length === 0 ? (
        <div className="space-y-3">
          <DashboardEmptyState
            title={hasFilters ? 'لا يوجد موظفون مطابقون للتصفية.' : 'لا يوجد موظفون بعد.'}
            description={
              hasFilters
                ? 'غيّر البحث أو الدور أو الحالة ثم أعد المحاولة.'
                : 'أضف أول موظف لتعيين دور ومساحة عمل واضحة.'
            }
          />
          {!hasFilters ? (
            <div className="text-center">
              <button
                type="button"
                onClick={openCreate}
                className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm text-white"
              >
                إضافة موظف
              </button>
            </div>
          ) : null}
        </div>
      ) : null}

      {!loading && !error && list && list.items.length > 0 ? (
        <EmployeeTable
          employees={list.items}
          busyId={busyId}
          onView={setDetails}
          onEdit={openEdit}
          onToggleActive={handleToggleActive}
        />
      ) : null}

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between gap-3 text-sm">
          <p className="text-slate-600">
            صفحة {meta.current_page.toLocaleString('ar-SA')} من {meta.last_page.toLocaleString('ar-SA')} ·{' '}
            {meta.total.toLocaleString('ar-SA')} موظف
          </p>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={meta.current_page <= 1}
              onClick={() => setFilters((current) => ({ ...current, page: Math.max(1, (current.page ?? 1) - 1) }))}
              className="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-50"
            >
              السابق
            </button>
            <button
              type="button"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setFilters((current) => ({ ...current, page: (current.page ?? 1) + 1 }))}
              className="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-50"
            >
              التالي
            </button>
          </div>
        </div>
      ) : null}
    </section>
  )
}
