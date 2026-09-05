import { TASK_STATUS_LABELS } from '../../utils/workspaceTasks'
import type { EmployeeListMeta } from '../../types/api'

export function WorkspacePagination({ meta, onPage }: { meta: EmployeeListMeta; onPage: (page: number) => void }) {
  if (meta.last_page <= 1) {
    return null
  }

  return (
    <div className="flex items-center gap-3 text-sm">
      <button
        type="button"
        disabled={meta.current_page <= 1}
        onClick={() => onPage(meta.current_page - 1)}
        className="rounded-lg border border-slate-300 px-3 py-2 disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        السابق
      </button>
      <p>
        صفحة {meta.current_page.toLocaleString('ar-SA')} من {meta.last_page.toLocaleString('ar-SA')}
      </p>
      <button
        type="button"
        disabled={meta.current_page >= meta.last_page}
        onClick={() => onPage(meta.current_page + 1)}
        className="rounded-lg border border-slate-300 px-3 py-2 disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        التالي
      </button>
    </div>
  )
}

export function TaskStatusSelect({
  value,
  onChange,
  label,
  disabled = false,
}: {
  value: string
  onChange: (status: string) => void
  label: string
  disabled?: boolean
}) {
  return (
    <select
      aria-label={label}
      disabled={disabled}
      className="mt-1 w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:opacity-60"
      value={value}
      onChange={(event) => onChange(event.target.value)}
    >
      {Object.entries(TASK_STATUS_LABELS).map(([status, text]) => (
        <option key={status} value={status}>
          {text}
        </option>
      ))}
    </select>
  )
}
