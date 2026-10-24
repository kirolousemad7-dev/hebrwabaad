import type { ProjectWorkspaceTask } from '../../../services/operations'
import { TASK_STATUS_LABELS } from '../../../utils/workspaceProjects'

const PRIORITY_LABELS: Record<string, string> = {
  LOW: 'منخفضة',
  MEDIUM: 'متوسطة',
  HIGH: 'عالية',
  URGENT: 'عاجلة',
}

const COLUMNS: Array<{ key: string; label: string; statuses: string[] }> = [
  { key: 'TODO', label: 'جديدة', statuses: ['TODO'] },
  { key: 'IN_PROGRESS', label: 'قيد التنفيذ', statuses: ['IN_PROGRESS'] },
  { key: 'REVIEW', label: 'تحت المراجعة', statuses: ['REVIEW', 'REVISION'] },
  { key: 'COMPLETED', label: 'مكتملة', statuses: ['COMPLETED'] },
]

function count(value: number): string {
  return value.toLocaleString('en-US')
}

function countLabel(value: number | undefined, singular: string, plural: string): string | null {
  if (!value) return null
  return `${value.toLocaleString('en-US')} ${value === 1 ? singular : plural}`
}

export function ProjectTaskBoard({
  tasks,
  onOpen,
}: {
  tasks: ProjectWorkspaceTask[]
  onOpen: (task: ProjectWorkspaceTask) => void
}) {
  return (
    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      {COLUMNS.map((column) => {
        const rows = tasks.filter((task) => column.statuses.includes(task.status))
        return (
          <section key={column.key} className="min-w-0 rounded-2xl border border-slate-200 bg-[#f4f6f8] p-3">
            <header className="mb-3 flex items-center justify-between gap-2">
              <h3 className="text-sm font-semibold text-slate-900">{column.label}</h3>
              <span className="rounded-full bg-white px-2 py-0.5 text-xs text-slate-500">{count(rows.length)}</span>
            </header>
            {rows.length === 0 ? (
              <p className="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-xs text-slate-500">لا توجد مهام في هذه الحالة</p>
            ) : (
              <ul className="space-y-2">
                {rows.map((task) => {
                  const comments = countLabel(task.comments_count, 'ملاحظة', 'ملاحظات')
                  const files = countLabel(task.attachments_count, 'ملف', 'ملفات')
                  return (
                  <li key={task.id}>
                    <button
                      type="button"
                      onClick={() => onOpen(task)}
                      className={`w-full rounded-xl border bg-white px-3 py-2.5 text-start text-sm shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow ${
                        task.is_overdue ? 'border-red-200' : 'border-slate-200'
                      }`}
                    >
                      <p className="font-medium leading-6 text-slate-900">{task.title}</p>
                      <p className="mt-1 flex flex-wrap items-center gap-1 text-[11px]">
                        <span className="text-xs text-slate-500">{task.assignee?.name ?? 'بدون مسؤول'}</span>
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700">
                          {TASK_STATUS_LABELS[task.status] ?? task.status}
                        </span>
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">
                          {PRIORITY_LABELS[task.priority] ?? task.priority}
                        </span>
                      </p>
                      {task.deadline || task.is_overdue ? (
                        <p className="mt-2 flex flex-wrap gap-1 text-[11px]">
                          {task.deadline ? (
                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">{task.deadline}</span>
                          ) : null}
                          {task.is_overdue ? (
                            <span className="rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-700">متأخرة</span>
                          ) : null}
                        </p>
                      ) : null}
                      {comments || files ? (
                        <p className="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-500">
                          {comments ? <span>{comments}</span> : null}
                          {files ? <span>{files}</span> : null}
                        </p>
                      ) : null}
                    </button>
                  </li>
                  )
                })}
              </ul>
            )}
          </section>
        )
      })}
    </div>
  )
}
