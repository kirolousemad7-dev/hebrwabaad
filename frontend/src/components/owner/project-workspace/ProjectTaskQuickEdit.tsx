import type { CalendarAssignee } from '../../../services/calendar'
import type {
  ProjectMilestone,
  ProjectPhase,
  ProjectWorkspaceTask,
} from '../../../services/operations'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type Props = {
  task: ProjectWorkspaceTask
  assignees: CalendarAssignee[]
  phases: ProjectPhase[]
  milestones: ProjectMilestone[]
  saving: boolean
  canEdit: boolean
  onClose: () => void
  onSave: (payload: {
    title: string
    description: string | null
    assigned_to: number
    priority: string
    status: string
    deadline: string | null
    start_at: string | null
    due_at: string | null
    phase_id: number | null
    milestone_id: number | null
    is_client_visible: boolean
    link_to_calendar: boolean
  }) => void
  onLinkCalendar: () => void
}

export function ProjectTaskQuickEdit({
  task,
  assignees,
  phases,
  milestones,
  saving,
  canEdit,
  onClose,
  onSave,
  onLinkCalendar,
}: Props) {
  const startValue = (task.start_at ?? '').slice(0, 10)
  const deadlineValue = task.deadline ?? (task.due_at ?? '').slice(0, 10)

  return (
    <div className="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-3 sm:items-center">
      <div
        role="dialog"
        aria-modal="true"
        className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl"
      >
        <div className="mb-3 flex items-start justify-between gap-3">
          <div>
            <p className="text-xs text-slate-500">مهمة #{task.id}</p>
            <h3 className="text-lg font-semibold text-slate-900">{task.title}</h3>
            {!canEdit ? (
              <p className="mt-1 text-xs text-amber-800">عرض فقط — لا صلاحية تعديل.</p>
            ) : null}
          </div>
          <button type="button" onClick={onClose} className="rounded border border-slate-200 px-2 py-1 text-xs">
            إغلاق
          </button>
        </div>

        <fieldset disabled={!canEdit || saving} className="grid gap-2 sm:grid-cols-2">
          <label className="space-y-1 text-sm sm:col-span-2">
            <span className="text-slate-600">العنوان</span>
            <input
              id="qe-title"
              className={fieldClass}
              defaultValue={task.title}
              key={`title-${task.id}`}
            />
          </label>
          <label className="space-y-1 text-sm sm:col-span-2">
            <span className="text-slate-600">الوصف</span>
            <textarea
              id="qe-description"
              className={fieldClass}
              rows={2}
              defaultValue={task.description ?? ''}
              key={`desc-${task.id}`}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="text-slate-600">الحالة</span>
            <select id="qe-status" className={fieldClass} defaultValue={task.status} key={`status-${task.id}`}>
              <option value="TODO">معلّق</option>
              <option value="IN_PROGRESS">قيد التنفيذ</option>
              <option value="REVIEW">مراجعة</option>
              <option value="REVISION">تعديل</option>
              <option value="COMPLETED">مكتمل</option>
            </select>
          </label>
          <label className="space-y-1 text-sm">
            <span className="text-slate-600">الأولوية</span>
            <select
              id="qe-priority"
              className={fieldClass}
              defaultValue={task.priority}
              key={`priority-${task.id}`}
            >
              <option value="LOW">منخفضة</option>
              <option value="MEDIUM">متوسطة</option>
              <option value="HIGH">عالية</option>
              <option value="URGENT">عاجلة</option>
            </select>
          </label>
          <label className="space-y-1 text-sm">
            <span className="text-slate-600">المسؤول</span>
            <select
              id="qe-assignee"
              className={fieldClass}
              defaultValue={String(task.assigned_to)}
              key={`assignee-${task.id}`}
            >
              {assignees.map((person) => (
                <option key={person.id} value={person.id}>
                  {person.name}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1 text-sm">
            <span className="text-slate-600">المرحلة</span>
            <select
              id="qe-phase"
              className={fieldClass}
              defaultValue={task.phase_id ? String(task.phase_id) : ''}
              key={`phase-${task.id}`}
            >
              <option value="">بلا مرحلة</option>
              {phases.map((phase) => (
                <option key={phase.id} value={phase.id}>
                  {phase.title}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1 text-sm">
            <span className="text-slate-600">المعلم</span>
            <select
              id="qe-milestone"
              className={fieldClass}
              defaultValue={task.milestone_id ? String(task.milestone_id) : ''}
              key={`milestone-${task.id}`}
            >
              <option value="">بلا معلم</option>
              {milestones.map((milestone) => (
                <option key={milestone.id} value={milestone.id}>
                  {milestone.title}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1 text-sm">
            <span className="text-slate-600">البداية</span>
            <input id="qe-start" type="date" className={fieldClass} defaultValue={startValue} key={`start-${task.id}`} />
          </label>
          <label className="space-y-1 text-sm">
            <span className="text-slate-600">الاستحقاق</span>
            <input
              id="qe-deadline"
              type="date"
              className={fieldClass}
              defaultValue={deadlineValue}
              key={`deadline-${task.id}`}
            />
          </label>
          <label className="flex items-center gap-2 text-xs text-slate-600 sm:col-span-2">
            <input id="qe-client" type="checkbox" defaultChecked={Boolean(task.is_client_visible)} />
            ظاهر للعميل
          </label>
          <label className="flex items-center gap-2 text-xs text-slate-600 sm:col-span-2">
            <input id="qe-link-cal" type="checkbox" defaultChecked={false} />
            ربط بالتقويم إن لم يكن مربوطاً
          </label>
        </fieldset>

        <div className="mt-4 flex flex-wrap gap-2">
          {canEdit ? (
            <button
              type="button"
              disabled={saving}
              className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
              onClick={() => {
                const title = (document.getElementById('qe-title') as HTMLInputElement | null)?.value.trim() ?? ''
                const description =
                  (document.getElementById('qe-description') as HTMLTextAreaElement | null)?.value.trim() || null
                const status = (document.getElementById('qe-status') as HTMLSelectElement | null)?.value ?? task.status
                const priority =
                  (document.getElementById('qe-priority') as HTMLSelectElement | null)?.value ?? task.priority
                const assignee = Number(
                  (document.getElementById('qe-assignee') as HTMLSelectElement | null)?.value ?? task.assigned_to,
                )
                const phaseRaw = (document.getElementById('qe-phase') as HTMLSelectElement | null)?.value ?? ''
                const milestoneRaw =
                  (document.getElementById('qe-milestone') as HTMLSelectElement | null)?.value ?? ''
                const startAt = (document.getElementById('qe-start') as HTMLInputElement | null)?.value || null
                const deadline = (document.getElementById('qe-deadline') as HTMLInputElement | null)?.value || null
                if (startAt && deadline && deadline < startAt) {
                  return
                }
                onSave({
                  title: title || task.title,
                  description,
                  assigned_to: assignee,
                  priority,
                  status,
                  deadline,
                  start_at: startAt,
                  due_at: deadline,
                  phase_id: phaseRaw ? Number(phaseRaw) : null,
                  milestone_id: milestoneRaw ? Number(milestoneRaw) : null,
                  is_client_visible: Boolean(
                    (document.getElementById('qe-client') as HTMLInputElement | null)?.checked,
                  ),
                  link_to_calendar: Boolean(
                    (document.getElementById('qe-link-cal') as HTMLInputElement | null)?.checked,
                  ),
                })
              }}
            >
              حفظ
            </button>
          ) : null}
          {canEdit && !task.calendar_item_id ? (
            <button
              type="button"
              disabled={saving}
              onClick={onLinkCalendar}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-50"
            >
              ربط بالتقويم
            </button>
          ) : null}
          {task.calendar_item_id ? (
            <span className="self-center text-xs text-slate-500">مربوط بالتقويم #{task.calendar_item_id}</span>
          ) : null}
        </div>
      </div>
    </div>
  )
}
