import { FormEvent, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { TaskStatusSelect } from '../../components/workspace/WorkspaceListControls'
import { WorkspaceErrorState, WorkspaceSkeleton } from '../../components/workspace/WorkspaceStatus'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { ApiRequestError } from '../../services/api'
import { getWorkspaceProjects } from '../../services/workspaceProjects'
import { getTaskAssignees, getWorkspaceTask, updateManagedTask, updateMyTaskStatus } from '../../services/workspaceTasks'
import { formatTaskAssignedDate, formatTaskDeadline, TASK_PRIORITY_LABELS, TASK_STATUS_LABELS } from '../../utils/workspaceTasks'

const fieldClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function WorkspaceTaskDetailPage() {
  const { taskId } = useParams()
  const { user } = useAuth()
  const toast = useToast()
  const isManager = user?.role === 'ACCOUNT_MANAGER'
  const numericId = Number(taskId)
  const { state, reload } = useAsyncData(async () => {
    const [task, assignees, projects] = await Promise.all([
      getWorkspaceTask(numericId),
      isManager ? getTaskAssignees() : Promise.resolve({ data: [] }),
      isManager ? getWorkspaceProjects('?per_page=50') : Promise.resolve({ data: { items: [] } }),
    ])
    return { data: { task: task.data, assignees: assignees.data, projects: projects.data.items } }
  })

  if (!Number.isInteger(numericId) || numericId <= 0) {
    return <WorkspaceErrorState message="المهمة غير صالحة." onRetry={() => undefined} />
  }

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل المهمة..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
  }

  const task = state.data.task

  return (
    <section className="space-y-6">
      <p>
        <Link to="/workspace/tasks" className="text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
          كل المهام
        </Link>
      </p>
      <header>
        <h1 className="text-2xl font-semibold">{task.title}</h1>
        {task.description ? <p className="mt-1 text-sm text-slate-600">{task.description}</p> : null}
      </header>

      <dl className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm sm:grid-cols-2">
        <div>
          <dt className="text-slate-500">المشروع</dt>
          <dd>
            {task.project ? (
              <Link
                to={`/workspace/projects/${task.project.id}`}
                className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
              >
                {task.project.title}
              </Link>
            ) : (
              '—'
            )}
          </dd>
        </div>
        <div>
          <dt className="text-slate-500">الموظف</dt>
          <dd>{task.assignee?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">الأولوية</dt>
          <dd>{TASK_PRIORITY_LABELS[task.priority] ?? task.priority}</dd>
        </div>
        <div>
          <dt className="text-slate-500">الموعد</dt>
          <dd>
            {formatTaskDeadline(task.deadline)}
            {task.is_overdue ? <span className="ms-2 text-red-700">متأخرة</span> : null}
          </dd>
        </div>
        <div>
          <dt className="text-slate-500">تاريخ التعيين</dt>
          <dd>{formatTaskAssignedDate(task.created_at)}</dd>
        </div>
        <div>
          <dt className="text-slate-500">الحالة</dt>
          <dd>
            <StatusBadge status={task.status} label={TASK_STATUS_LABELS[task.status] ?? task.status} />
          </dd>
        </div>
      </dl>

      {isManager ? (
        <ManagerTaskForm
          task={task}
          assignees={state.data.assignees}
          projects={state.data.projects}
          onSaved={() => {
            toast.success('تم حفظ تعديلات المهمة.')
            void reload()
          }}
        />
      ) : (
        <label className="block text-sm">
          تحديث الحالة
          <TaskStatusSelect
            value={task.status}
            label={`تحديث حالة ${task.title}`}
            onChange={(status) => {
              void updateMyTaskStatus(task.id, status)
                .then(() => {
                  toast.success('تم تحديث حالة المهمة.')
                  return reload()
                })
                .catch((caught) => {
                  toast.error(caught instanceof ApiRequestError ? caught.message : 'تعذر تحديث الحالة.')
                })
            }}
          />
        </label>
      )}
    </section>
  )
}

function ManagerTaskForm({
  task,
  assignees,
  projects,
  onSaved,
}: {
  task: {
    id: number
    title: string
    description: string | null
    project_id?: number | null
    assigned_to: number
    priority: string
    status: string
    deadline: string | null
  }
  assignees: { id: number; name: string }[]
  projects: { id: number; title: string }[]
  onSaved: () => void
}) {
  const [title, setTitle] = useState(task.title)
  const [description, setDescription] = useState(task.description ?? '')
  const [projectId, setProjectId] = useState(String(task.project_id ?? ''))
  const [assignedTo, setAssignedTo] = useState(String(task.assigned_to))
  const [priority, setPriority] = useState(task.priority)
  const [status, setStatus] = useState(task.status)
  const [deadline, setDeadline] = useState(task.deadline ?? '')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const projectOptions = useMemo(() => projects, [projects])

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    try {
      await updateManagedTask(task.id, {
        title,
        description: description || undefined,
        project_id: Number(projectId),
        assigned_to: Number(assignedTo),
        priority,
        status,
        deadline: deadline || undefined,
      })
      onSaved()
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر تحديث المهمة.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={(event) => void onSubmit(event)} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="text-lg font-semibold">تعديل المهمة</h2>
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      <label className="block text-sm">
        العنوان
        <input required value={title} onChange={(event) => setTitle(event.target.value)} className={fieldClass} />
      </label>
      <label className="block text-sm">
        الوصف
        <textarea value={description} onChange={(event) => setDescription(event.target.value)} className={fieldClass} rows={3} />
      </label>
      <label className="block text-sm">
        المشروع
        <select required aria-label="مشروع المهمة" value={projectId} onChange={(event) => setProjectId(event.target.value)} className={fieldClass}>
          <option value="">اختر مشروعاً</option>
          {projectOptions.map((project) => (
            <option key={project.id} value={project.id}>
              {project.title}
            </option>
          ))}
        </select>
      </label>
      <label className="block text-sm">
        الموظف
        <select required aria-label="الموظف المعيّن" value={assignedTo} onChange={(event) => setAssignedTo(event.target.value)} className={fieldClass}>
          <option value="">اختر موظفاً</option>
          {assignees.map((employee) => (
            <option key={employee.id} value={employee.id}>
              {employee.name}
            </option>
          ))}
        </select>
      </label>
      <div className="grid gap-4 md:grid-cols-2">
        <label className="block text-sm">
          الأولوية
          <select aria-label="أولوية المهمة" value={priority} onChange={(event) => setPriority(event.target.value)} className={fieldClass}>
            {Object.entries(TASK_PRIORITY_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label className="block text-sm">
          الحالة
          <TaskStatusSelect value={status} label="حالة المهمة" onChange={setStatus} />
        </label>
      </div>
      <label className="block text-sm">
        الموعد النهائي
        <input type="date" value={deadline} onChange={(event) => setDeadline(event.target.value)} className={fieldClass} />
      </label>
      <button
        type="submit"
        disabled={saving}
        className="min-h-11 rounded-lg bg-slate-900 px-4 text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        {saving ? 'جاري الحفظ...' : 'حفظ التعديلات'}
      </button>
    </form>
  )
}
