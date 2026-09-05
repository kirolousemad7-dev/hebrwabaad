import { EMPLOYEE_ROLES, EMPLOYEE_ROLE_LABELS } from '../../utils/staff'
import type { EmployeeListFilters } from '../../services/employees'

const fieldClass =
  'w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type EmployeeFiltersProps = {
  filters: EmployeeListFilters
  onChange: (filters: EmployeeListFilters) => void
}

export function EmployeeFilters({ filters, onChange }: EmployeeFiltersProps) {
  return (
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <label className="block space-y-1 text-sm">
        <span>بحث</span>
        <input
          value={filters.q ?? ''}
          onChange={(event) => onChange({ ...filters, q: event.target.value, page: 1 })}
          placeholder="الاسم أو البريد"
          className={fieldClass}
        />
      </label>
      <label className="block space-y-1 text-sm">
        <span>الدور</span>
        <select
          value={filters.role ?? ''}
          onChange={(event) => onChange({ ...filters, role: event.target.value, page: 1 })}
          className={fieldClass}
        >
          <option value="">كل الأدوار</option>
          {EMPLOYEE_ROLES.map((role) => (
            <option key={role} value={role}>
              {EMPLOYEE_ROLE_LABELS[role]}
            </option>
          ))}
        </select>
      </label>
      <label className="block space-y-1 text-sm">
        <span>الحالة</span>
        <select
          value={filters.is_active ?? ''}
          onChange={(event) =>
            onChange({ ...filters, is_active: event.target.value as EmployeeListFilters['is_active'], page: 1 })
          }
          className={fieldClass}
        >
          <option value="">الكل</option>
          <option value="true">نشط</option>
          <option value="false">معطّل</option>
        </select>
      </label>
      <label className="block space-y-1 text-sm">
        <span>الترتيب</span>
        <select
          value={`${filters.sort ?? 'created_at'}:${filters.direction ?? 'desc'}`}
          onChange={(event) => {
            const [sort, direction] = event.target.value.split(':') as [
              EmployeeListFilters['sort'],
              EmployeeListFilters['direction'],
            ]
            onChange({ ...filters, sort, direction, page: 1 })
          }}
          className={fieldClass}
        >
          <option value="created_at:desc">الأحدث</option>
          <option value="created_at:asc">الأقدم</option>
          <option value="name:asc">الاسم أ-ي</option>
          <option value="name:desc">الاسم ي-أ</option>
          <option value="is_active:desc">النشط أولاً</option>
        </select>
      </label>
    </div>
  )
}
