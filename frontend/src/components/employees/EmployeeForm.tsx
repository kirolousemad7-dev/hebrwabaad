import { FormEvent } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { EMPLOYEE_ROLES, EMPLOYEE_ROLE_LABELS } from '../../utils/staff'
import type { EmployeeWritePayload } from '../../services/employees'
import type { EmployeeFormState } from './employeeFormState'

const fieldClass =
  'w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type EmployeeFormProps = {
  title: string
  form: EmployeeFormState
  isCreate: boolean
  saving: boolean
  error: string | null
  onChange: (form: EmployeeFormState) => void
  onSubmit: (payload: EmployeeWritePayload) => void
  onCancel: () => void
}

export function EmployeeForm({
  title,
  form,
  isCreate,
  saving,
  error,
  onChange,
  onSubmit,
  onCancel,
}: EmployeeFormProps) {
  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    onSubmit({
      name: form.name.trim(),
      email: form.email.trim(),
      role: form.role,
      password: isCreate ? form.password : undefined,
      password_confirmation: isCreate ? form.password_confirmation : undefined,
      is_active: isCreate ? form.is_active : undefined,
    })
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="text-lg font-semibold">{title}</h2>
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <div className="grid gap-4 sm:grid-cols-2">
        <label className="block space-y-1 text-sm">
          <span>الاسم</span>
          <input
            required
            maxLength={255}
            value={form.name}
            onChange={(event) => onChange({ ...form, name: event.target.value })}
            className={fieldClass}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>البريد الإلكتروني</span>
          <input
            type="email"
            required
            maxLength={255}
            dir="ltr"
            value={form.email}
            onChange={(event) => onChange({ ...form, email: event.target.value })}
            className={fieldClass}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>الدور</span>
          <select
            required
            value={form.role}
            onChange={(event) => onChange({ ...form, role: event.target.value })}
            className={fieldClass}
          >
            {EMPLOYEE_ROLES.map((role) => (
              <option key={role} value={role}>
                {EMPLOYEE_ROLE_LABELS[role]}
              </option>
            ))}
          </select>
        </label>
        {isCreate ? (
          <label className="flex items-center gap-2 self-end text-sm">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(event) => onChange({ ...form, is_active: event.target.checked })}
            />
            <span>الحساب نشط</span>
          </label>
        ) : null}
      </div>

      {isCreate ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block space-y-1 text-sm">
            <span>كلمة المرور</span>
            <input
              type="password"
              required
              minLength={8}
              value={form.password}
              onChange={(event) => onChange({ ...form, password: event.target.value })}
              className={fieldClass}
            />
          </label>
          <label className="block space-y-1 text-sm">
            <span>تأكيد كلمة المرور</span>
            <input
              type="password"
              required
              minLength={8}
              value={form.password_confirmation}
              onChange={(event) => onChange({ ...form, password_confirmation: event.target.value })}
              className={fieldClass}
            />
          </label>
        </div>
      ) : null}

      <div className="flex flex-wrap gap-3">
        <button
          type="submit"
          disabled={saving}
          className="min-h-11 rounded-lg bg-slate-900 px-4 text-sm text-white disabled:opacity-60"
        >
          {saving ? 'جاري الحفظ...' : 'حفظ'}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="min-h-11 rounded-lg border border-slate-300 px-4 text-sm"
        >
          إلغاء
        </button>
      </div>
    </form>
  )
}
