import { useEffect, useRef, useState } from 'react'
import { EmployeeRoleBadge, EmployeeStatusBadge } from '../../components/employees/EmployeeBadges'
import { EmployeeFilters } from '../../components/employees/EmployeeFilters'
import { WorkspaceEmptyState, WorkspaceErrorState, WorkspaceSkeleton } from '../../components/workspace/WorkspaceStatus'
import { useAsyncData } from '../../hooks/useAsyncData'
import { employeeListQuery, type EmployeeListFilters } from '../../services/employees'
import { getHrEmployees } from '../../services/workspaceTasks'
import { formatTaskAssignedDate } from '../../utils/workspaceTasks'

const INITIAL_FILTERS: EmployeeListFilters = {
  q: '',
  role: '',
  is_active: '',
  sort: 'created_at',
  direction: 'desc',
  page: 1,
}

export function HrDirectoryPage() {
  const [filters, setFilters] = useState<EmployeeListFilters>(INITIAL_FILTERS)
  const query = employeeListQuery({ ...filters, page: filters.page && filters.page > 1 ? filters.page : undefined })
  const suffix = query === '' ? '?per_page=15' : `${query}&per_page=15`
  const { state, reload } = useAsyncData(() => getHrEmployees(suffix))
  const skipReload = useRef(true)

  useEffect(() => {
    if (skipReload.current) {
      skipReload.current = false
      return
    }
    void reload()
  }, [filters, reload])

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل دليل الموظفين..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold">دليل الموظفين</h1>
        <p className="text-sm text-slate-600">عرض للقراءة فقط. إدارة الحسابات والأدوار والتفعيل تتم من لوحة المالك.</p>
      </header>
      {state.data.summary ? (
        <p className="text-sm text-slate-600">
          الإجمالي {state.data.summary.total.toLocaleString('ar-SA')} · نشط {state.data.summary.active.toLocaleString('ar-SA')} · غير نشط{' '}
          {state.data.summary.inactive.toLocaleString('ar-SA')}
        </p>
      ) : null}
      <EmployeeFilters filters={filters} onChange={setFilters} />
      {state.data.items.length === 0 ? (
        <WorkspaceEmptyState title="لا يوجد موظفون." description="سيظهر الدليل بعد إضافة الموظفين بواسطة المالك." />
      ) : (
        <>
          <ul className="space-y-3 lg:hidden">
            {state.data.items.map((employee) => (
              <li key={employee.id} className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="font-medium">{employee.name}</p>
                    <p className="truncate text-sm text-slate-600" dir="ltr">
                      {employee.email}
                    </p>
                  </div>
                  <EmployeeStatusBadge active={employee.is_active} />
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <EmployeeRoleBadge role={employee.role} />
                  <span className="text-xs text-slate-500">أُنشئ {formatTaskAssignedDate(employee.created_at)}</span>
                </div>
                <p className="text-xs text-slate-500">آخر نشاط {formatTaskAssignedDate(employee.last_seen_at)}</p>
              </li>
            ))}
          </ul>
          <div className="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
            <table className="min-w-full text-right text-sm">
              <thead className="bg-slate-50 text-slate-600">
                <tr>
                  <th className="px-4 py-3 font-medium">الاسم</th>
                  <th className="px-4 py-3 font-medium">البريد</th>
                  <th className="px-4 py-3 font-medium">الدور</th>
                  <th className="px-4 py-3 font-medium">الحالة</th>
                  <th className="px-4 py-3 font-medium">تاريخ الإنشاء</th>
                  <th className="px-4 py-3 font-medium">آخر نشاط</th>
                </tr>
              </thead>
              <tbody>
                {state.data.items.map((employee) => (
                  <tr key={employee.id} className="border-t border-slate-200">
                    <td className="px-4 py-3 font-medium">{employee.name}</td>
                    <td className="px-4 py-3" dir="ltr">
                      {employee.email}
                    </td>
                    <td className="px-4 py-3">
                      <EmployeeRoleBadge role={employee.role} />
                    </td>
                    <td className="px-4 py-3">
                      <EmployeeStatusBadge active={employee.is_active} />
                    </td>
                    <td className="whitespace-nowrap px-4 py-3">{formatTaskAssignedDate(employee.created_at)}</td>
                    <td className="whitespace-nowrap px-4 py-3">{formatTaskAssignedDate(employee.last_seen_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
      {state.data.meta.last_page > 1 ? (
        <div className="flex flex-wrap items-center gap-3 text-sm">
          <button
            type="button"
            disabled={state.data.meta.current_page <= 1}
            onClick={() => setFilters((current) => ({ ...current, page: (current.page ?? 1) - 1 }))}
            className="min-h-11 rounded-lg border border-slate-300 px-4 disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            السابق
          </button>
          <p>
            صفحة {state.data.meta.current_page.toLocaleString('ar-SA')} من {state.data.meta.last_page.toLocaleString('ar-SA')}
          </p>
          <button
            type="button"
            disabled={state.data.meta.current_page >= state.data.meta.last_page}
            onClick={() => setFilters((current) => ({ ...current, page: (current.page ?? 1) + 1 }))}
            className="min-h-11 rounded-lg border border-slate-300 px-4 disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            التالي
          </button>
        </div>
      ) : null}
    </section>
  )
}
