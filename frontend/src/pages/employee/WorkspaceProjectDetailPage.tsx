import { FileLibrary } from '../../components/files/FileLibrary'
import { FormEvent, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { TaskStatusSelect } from '../../components/workspace/WorkspaceListControls'
import { WorkspaceEmptyState, WorkspaceErrorState, WorkspaceSkeleton } from '../../components/workspace/WorkspaceStatus'
import { useAuth } from '../../context/AuthContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { ApiRequestError } from '../../services/api'
import { getWorkspaceProjectTasks, updateWorkspaceProject } from '../../services/workspaceProjects'
import { createManagedTask, getTaskAssignees, updateMyTaskStatus } from '../../services/workspaceTasks'
import { formatProjectDate, formatProjectProgress, PROJECT_STATUS_LABELS } from '../../utils/workspaceProjects'
import { formatTaskDeadline, TASK_PRIORITY_LABELS, TASK_STATUS_LABELS } from '../../utils/workspaceTasks'

const fieldClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function WorkspaceProjectDetailPage() {
  const { projectId } = useParams()
  const { user } = useAuth()
  const isManager = user?.role === 'ACCOUNT_MANAGER'
  const numericId = Number(projectId)
  const { state, reload } = useAsyncData(async () => {
    const [tasks, assignees] = await Promise.all([
      getWorkspaceProjectTasks(numericId, '?per_page=50'),
      isManager ? getTaskAssignees() : Promise.resolve({ data: [] }),
    ])
    return { data: { bundle: tasks.data, assignees: assignees.data } }
  })
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [assignedTo, setAssignedTo] = useState('')
  const [priority, setPriority] = useState('MEDIUM')
  const [deadline, setDeadline] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const assignees = useMemo(
    () => (state.status === 'ready' ? state.data.assignees : []),
    [state],
  )

  if (!Number.isInteger(numericId) || numericId <= 0) {
    return <WorkspaceErrorState message="المشروع غير صالح." onRetry={() => undefined} />
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    try {
      await createManagedTask({
        title,
        description: description || undefined,
        project_id: numericId,
        assigned_to: Number(assignedTo),
        priority,
        deadline: deadline || undefined,
      })
      setTitle('')
      setDescription('')
      setAssignedTo('')
      setPriority('MEDIUM')
      setDeadline('')
      await reload()
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إنشاء المهمة.')
    } finally {
      setSaving(false)
    }
  }

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل المشروع..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
  }

  const project = state.data.bundle.project
  const tasks = state.data.bundle.items
  const progress = project?.progress

  if (!project) {
    return <WorkspaceErrorState message="تعذر تحميل المشروع." onRetry={() => void reload()} />
  }

  return (
    <section className="space-y-8">
      <header className="space-y-2">
        <p>
          <Link to="/workspace/projects" className="text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
            كل المشاريع
          </Link>
        </p>
        <h1 className="text-2xl font-semibold">{project.title}</h1>
        {project.description ? <p className="text-sm text-slate-600">{project.description}</p> : null}
      </header>

      <dl className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm sm:grid-cols-2 xl:grid-cols-3">
        <div>
          <dt className="text-slate-500">العميل</dt>
          <dd className="font-medium">{project.customer?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">الأكونت مانجر</dt>
          <dd className="font-medium">{project.account_manager?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">الحالة</dt>
          <dd>{PROJECT_STATUS_LABELS[project.status] ?? project.status}</dd>
        </div>
        <div>
          <dt className="text-slate-500">تاريخ البدء</dt>
          <dd>{formatProjectDate(project.started_at)}</dd>
        </div>
        <div>
          <dt className="text-slate-500">الموعد النهائي</dt>
          <dd>{formatProjectDate(project.deadline)}</dd>
        </div>
        <div>
          <dt className="text-slate-500">التقدم</dt>
          <dd>{progress ? formatProjectProgress(progress.percent) : '—'}</dd>
        </div>
      </dl>

      {progress ? (
        <p className="text-sm text-slate-600">
          {progress.total.toLocaleString('ar-SA')} مهام · مكتملة {progress.completed.toLocaleString('ar-SA')} · قيد التنفيذ{' '}
          {progress.in_progress.toLocaleString('ar-SA')} · قيد الانتظار {progress.todo.toLocaleString('ar-SA')}
        </p>
      ) : null}

      {isManager ? (
        <ProjectEditForm project={project} onSaved={() => void reload()} />
      ) : null}

      {isManager ? (
        <form onSubmit={(event) => void onSubmit(event)} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-semibold">إنشاء مهمة</h2>
          {error ? <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p> : null}
          <label className="block text-sm">
            عنوان المهمة
            <input required value={title} onChange={(event) => setTitle(event.target.value)} className={fieldClass} />
          </label>
          <label className="block text-sm">
            الوصف
            <textarea value={description} onChange={(event) => setDescription(event.target.value)} className={fieldClass} rows={3} />
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
              الموعد النهائي
              <input type="date" value={deadline} onChange={(event) => setDeadline(event.target.value)} className={fieldClass} />
            </label>
          </div>
          <button
            type="submit"
            disabled={saving}
            className="rounded-lg bg-slate-900 px-4 py-2.5 text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            {saving ? 'جاري الحفظ...' : 'تعيين المهمة'}
          </button>
        </form>
      ) : null}

      <div className="space-y-3">
        <h2 className="text-lg font-semibold">مهام المشروع</h2>
        {tasks.length === 0 ? (
          <WorkspaceEmptyState title="لا توجد مهام في هذا المشروع." description={isManager ? 'أنشئ مهمة وعيّنها لموظف نشط.' : 'ستظهر هنا المهام المعيّنة لك فقط.'} />
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full text-right text-sm">
              <thead className="bg-slate-50 text-slate-600">
                <tr>
                  <th className="px-4 py-3 font-medium">المهمة</th>
                  <th className="px-4 py-3 font-medium">الموظف</th>
                  <th className="px-4 py-3 font-medium">الأولوية</th>
                  <th className="px-4 py-3 font-medium">الحالة</th>
                  <th className="px-4 py-3 font-medium">الموعد</th>
                </tr>
              </thead>
              <tbody>
                {tasks.map((task) => (
                  <tr key={task.id} className="border-t border-slate-200">
                    <td className="px-4 py-3 font-medium">
                      <Link
                        to={`/workspace/tasks/${task.id}`}
                        className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                      >
                        {task.title}
                      </Link>
                    </td>
                    <td className="px-4 py-3">{task.assignee?.name ?? '—'}</td>
                    <td className="px-4 py-3">{TASK_PRIORITY_LABELS[task.priority] ?? task.priority}</td>
                    <td className="px-4 py-3">
                      {isManager ? (
                        TASK_STATUS_LABELS[task.status] ?? task.status
                      ) : (
                        <TaskStatusSelect
                          value={task.status}
                          label={`تحديث حالة ${task.title}`}
                          onChange={(status) => {
                            void updateMyTaskStatus(task.id, status).then(() => reload())
                          }}
                        />
                      )}
                    </td>
                    <td className="px-4 py-3">{formatTaskDeadline(task.deadline)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <article className="space-y-3">
        <h2 className="text-lg font-semibold">ملفات المشروع</h2>
        <FileLibrary
          scope="workspace"
          query={`?project_id=${project.id}&per_page=15`}
          projects={[{ id: project.id, label: project.title }]}
        />
      </article>
    </section>
  )
}

function ProjectEditForm({
  project,
  onSaved,
}: {
  project: {
    id: number
    title: string
    description: string | null
    customer_id: number
    status: string
    started_at: string | null
    deadline: string | null
  }
  onSaved: () => void
}) {
  const [title, setTitle] = useState(project.title)
  const [description, setDescription] = useState(project.description ?? '')
  const [status, setStatus] = useState(project.status)
  const [startedAt, setStartedAt] = useState(project.started_at ?? '')
  const [deadline, setDeadline] = useState(project.deadline ?? '')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    try {
      await updateWorkspaceProject(project.id, {
        title,
        description: description || undefined,
        customer_id: project.customer_id,
        status,
        started_at: startedAt || undefined,
        deadline: deadline || undefined,
      })
      onSaved()
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر تحديث المشروع.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={(event) => void onSubmit(event)} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="text-lg font-semibold">تحديث المشروع</h2>
      {error ? <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p> : null}
      <label className="block text-sm">
        اسم المشروع
        <input required value={title} onChange={(event) => setTitle(event.target.value)} className={fieldClass} />
      </label>
      <label className="block text-sm">
        الوصف
        <textarea value={description} onChange={(event) => setDescription(event.target.value)} className={fieldClass} rows={3} />
      </label>
      <label className="block text-sm">
        الحالة
        <select aria-label="حالة المشروع" value={status} onChange={(event) => setStatus(event.target.value)} className={fieldClass}>
          {Object.entries(PROJECT_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </label>
      <div className="grid gap-4 md:grid-cols-2">
        <label className="block text-sm">
          تاريخ البدء
          <input type="date" value={startedAt} onChange={(event) => setStartedAt(event.target.value)} className={fieldClass} />
        </label>
        <label className="block text-sm">
          الموعد النهائي
          <input type="date" value={deadline} onChange={(event) => setDeadline(event.target.value)} className={fieldClass} />
        </label>
      </div>
      <button
        type="submit"
        disabled={saving}
        className="rounded-lg border border-slate-300 px-4 py-2.5 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        {saving ? 'جاري الحفظ...' : 'حفظ المشروع'}
      </button>
    </form>
  )
}
