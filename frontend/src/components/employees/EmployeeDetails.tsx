import type { Employee } from '../../types/api'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import { ROLE_WORKSPACE } from '../../utils/staff'
import { EmployeeRoleBadge, EmployeeStatusBadge } from './EmployeeBadges'

type EmployeeDetailsProps = {
  employee: Employee
  onClose: () => void
  onEdit: () => void
}

export function EmployeeDetails({ employee, onClose, onEdit }: EmployeeDetailsProps) {
  return (
    <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold">{employee.name}</h2>
          <p className="text-sm text-slate-600" dir="ltr">
            {employee.email}
          </p>
        </div>
        <button type="button" onClick={onClose} className="text-sm underline">
          إغلاق
        </button>
      </div>
      <div className="flex flex-wrap gap-2">
        <EmployeeRoleBadge role={employee.role} />
        <EmployeeStatusBadge active={employee.is_active} />
      </div>
      <dl className="grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-slate-500">مساحة العمل</dt>
          <dd>{ROLE_WORKSPACE[employee.role]?.label ?? employee.workspace ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">تاريخ الإنشاء</dt>
          <dd>{formatDashboardDateTime(employee.created_at)}</dd>
        </div>
        <div>
          <dt className="text-slate-500">آخر نشاط</dt>
          <dd>{employee.last_seen_at ? formatDashboardDateTime(employee.last_seen_at) : 'لا يوجد بعد'}</dd>
        </div>
      </dl>
      <button
        type="button"
        onClick={onEdit}
        className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm text-white"
      >
        تعديل
      </button>
    </div>
  )
}
