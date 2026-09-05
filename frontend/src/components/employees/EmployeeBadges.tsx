import { EMPLOYEE_ROLE_LABELS, type EmployeeRole } from '../../utils/staff'

import { StatusBadge } from '../ui/StatusBadge'

export function EmployeeStatusBadge({ active }: { active: boolean }) {
  return <StatusBadge status={active ? 'ACTIVE' : 'INACTIVE'} label={active ? 'نشط' : 'معطّل'} />
}

export function EmployeeRoleBadge({ role }: { role: string }) {
  return (
    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
      {EMPLOYEE_ROLE_LABELS[role as EmployeeRole] ?? role}
    </span>
  )
}
