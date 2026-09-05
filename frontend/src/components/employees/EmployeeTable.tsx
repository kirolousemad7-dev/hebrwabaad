import type { Employee } from '../../types/api'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import { EmployeeRoleBadge, EmployeeStatusBadge } from './EmployeeBadges'

type EmployeeTableProps = {
  employees: Employee[]
  busyId: number | null
  onView: (employee: Employee) => void
  onEdit: (employee: Employee) => void
  onToggleActive: (employee: Employee) => void
}

export function EmployeeTable({ employees, busyId, onView, onEdit, onToggleActive }: EmployeeTableProps) {
  return (
    <>
      <ul className="space-y-3 lg:hidden">
        {employees.map((employee) => (
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
              <span className="text-xs text-slate-500">{formatDashboardDateTime(employee.created_at)}</span>
            </div>
            <div className="flex flex-wrap gap-3 text-sm">
              <button type="button" onClick={() => onView(employee)} className="underline">
                تفاصيل
              </button>
              <button type="button" onClick={() => onEdit(employee)} className="underline">
                تعديل
              </button>
              <button
                type="button"
                disabled={busyId === employee.id}
                onClick={() => onToggleActive(employee)}
                className="underline disabled:opacity-60"
              >
                {employee.is_active ? 'تعطيل' : 'تنشيط'}
              </button>
            </div>
          </li>
        ))}
      </ul>

      <div className="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white lg:block">
        <table className="min-w-full text-sm">
          <thead className="bg-slate-50 text-start text-slate-600">
            <tr>
              <th className="px-4 py-3 font-medium">الموظف</th>
              <th className="px-4 py-3 font-medium">الدور</th>
              <th className="px-4 py-3 font-medium">الحالة</th>
              <th className="px-4 py-3 font-medium">أُنشئ</th>
              <th className="px-4 py-3 font-medium">آخر نشاط</th>
              <th className="px-4 py-3 font-medium">إجراءات</th>
            </tr>
          </thead>
          <tbody>
            {employees.map((employee) => (
              <tr key={employee.id} className="border-t border-slate-100">
                <td className="px-4 py-3">
                  <p className="font-medium">{employee.name}</p>
                  <p className="text-xs text-slate-500" dir="ltr">
                    {employee.email}
                  </p>
                </td>
                <td className="px-4 py-3">
                  <EmployeeRoleBadge role={employee.role} />
                </td>
                <td className="px-4 py-3">
                  <EmployeeStatusBadge active={employee.is_active} />
                </td>
                <td className="whitespace-nowrap px-4 py-3">{formatDashboardDateTime(employee.created_at)}</td>
                <td className="whitespace-nowrap px-4 py-3">
                  {employee.last_seen_at ? formatDashboardDateTime(employee.last_seen_at) : '—'}
                </td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap gap-3">
                    <button type="button" onClick={() => onView(employee)} className="underline">
                      تفاصيل
                    </button>
                    <button type="button" onClick={() => onEdit(employee)} className="underline">
                      تعديل
                    </button>
                    <button
                      type="button"
                      disabled={busyId === employee.id}
                      onClick={() => onToggleActive(employee)}
                      className="underline disabled:opacity-60"
                    >
                      {employee.is_active ? 'تعطيل' : 'تنشيط'}
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  )
}
