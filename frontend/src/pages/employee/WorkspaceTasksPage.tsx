import { FormEvent, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { WorkspacePagination, TaskStatusSelect } from '../../components/workspace/WorkspaceListControls'
import { WorkspaceEmptyState, WorkspaceErrorState, WorkspaceSkeleton } from '../../components/workspace/WorkspaceStatus'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { ApiRequestError } from '../../services/api'
import { getWorkspaceProjects } from '../../services/workspaceProjects'
import {
  createManagedTask,
  getManagedTasks,
  getMyTasks,
  getTaskAssignees,
  updateMyTaskStatus,
} from '../../services/workspaceTasks'
import {
  TASK_PRIORITY_LABELS,
  TASK_STATUS_LABELS,
  formatTaskAssignedDate,
  formatTaskDeadline,
} from '../../utils/workspaceTasks'

export function WorkspaceTasksPage() {
  const { user } = useAuth()
  const isManager = user?.role === 'ACCOUNT_MANAGER'

  if (isManager) {
    return <AccountManagerTasksPage />
  }

  return <AssignedTasksPage />
}

function AssignedTasksPage() {
  const toast = useToast()
  const [page, setPage] = useState(1)
  const { state, reload } = useAsyncData(() => getMyTasks(`?page=${page}&per_page=15`))
  const skipReload = useRef(true)

  useEffect(() => {
    if (skipReload.current) {
      skipReload.current = false
      return
    }
    void reload()
  }, [page, reload])

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل المهام..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold">مهامي</h1>
        <p className="text-sm text-slate-600">المهام المعيّنة لك فقط. يمكنك تحديث الحالة دون إعادة التعيين.</p>
      </header>
      {state.data.items.length === 0 ? (
        <WorkspaceEmptyState title="لا توجد مهام معيّنة." description="سيظهر العمل هنا بعد تعيينه من الأكونت مانجر." />
      ) : (
        <ul className="space-y-3">
          {state.data.items.map((task) => (
            <li key={task.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <h2 className="font-semibold">
                <Link
                  to={`/workspace/tasks/${task.id}`}
                  className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  {task.title}
                </Link>
              </h2>
              {task.project ? <p className="mt-1 text-sm text-slate-600">المشروع: {task.project.title}</p> : null}
              {task.description ? <p className="mt-1 text-sm text-slate-600">{task.description}</p> : null}
              <p className="mt-2 text-sm text-slate-600">
                الأولوية: {TASK_PRIORITY_LABELS[task.priority] ?? task.priority}
                <span className="mx-1" aria-hidden="true">
                  ·
                </span>
                الموعد: {formatTaskDeadline(task.deadline)}
                <span className="mx-1" aria-hidden="true">
                  ·
                </span>
                التعيين: {formatTaskAssignedDate(task.created_at)}
                {task.is_overdue ? <span className="ms-2 text-red-700">متأخرة</span> : null}
              </p>
              <div className="mt-3 flex flex-wrap items-center gap-2">
                <StatusBadge status={task.status} label={TASK_STATUS_LABELS[task.status] ?? task.status} />
                <StatusBadge status={task.priority} label={TASK_PRIORITY_LABELS[task.priority] ?? task.priority} />
                {task.is_overdue ? <StatusBadge status="OVERDUE" label="متأخرة" /> : null}
              </div>
              <label className="mt-3 block text-sm">
                الحالة
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
            </li>
          ))}
        </ul>
      )}
      <WorkspacePagination meta={state.data.meta} onPage={setPage} />
    </section>
  )
}

function AccountManagerTasksPage() {
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [appliedSearch, setAppliedSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [priorityFilter, setPriorityFilter] = useState('')
  const [assigneeFilter, setAssigneeFilter] = useState('')
  const [projectFilter, setProjectFilter] = useState('')
  const listQuery = useMemo(() => {
    const params = new URLSearchParams({ page: String(page), per_page: '15' })
    if (appliedSearch) {
      params.set('q', appliedSearch)
    }
    if (statusFilter) {
      params.set('status', statusFilter)
    }
    if (priorityFilter) {
      params.set('priority', priorityFilter)
    }
    if (assigneeFilter) {
      params.set('assigned_to', assigneeFilter)
    }
    if (projectFilter) {
      params.set('project_id', projectFilter)
    }
    return `?${params.toString()}`
  }, [page, appliedSearch, statusFilter, priorityFilter, assigneeFilter, projectFilter])
  const { state, reload } = useAsyncData(async () => {
    const [tasks, assignees, projects] = await Promise.all([
      getManagedTasks(listQuery),
      getTaskAssignees(),
      getWorkspaceProjects('?per_page=50'),
    ])
    return { data: { tasks: tasks.data, assignees: assignees.data, projects: projects.data.items } }
  })
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [projectId, setProjectId] = useState('')
  const [assignedTo, setAssignedTo] = useState('')
  const [priority, setPriority] = useState('MEDIUM')
  const [deadline, setDeadline] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const skipReload = useRef(true)

  useEffect(() => {
    if (skipReload.current) {
      skipReload.current = false
      return
    }
    void reload()
  }, [listQuery, reload])

  const assignees = useMemo(
    () => (state.status === 'ready' ? state.data.assignees : []),
    [state],
  )
  const projects = useMemo(
    () => (state.status === 'ready' ? state.data.projects : []),
    [state],
  )

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    try {
      await createManagedTask({
        title,
        description: description || undefined,
        project_id: Number(projectId),
        assigned_to: Number(assignedTo),
        priority,
        deadline: deadline || undefined,
      })
      setTitle('')
      setDescription('')
      setProjectId('')
      setAssignedTo('')
      setPriority('MEDIUM')
      setDeadline('')
      setPage(1)
      toast.success('تم إنشاء المهمة وتعيينها.')
      await reload()
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إنشاء المهمة.')
    } finally {
      setSaving(false)
    }
  }

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل المهام..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
  }

  const tasks = state.data.tasks
  const fieldClass =
    'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

  return (
    <section className="space-y-8">
      <header>
        <h1 className="text-2xl font-semibold">إدارة المهام</h1>
        <p className="text-sm text-slate-600">تعيين العمل للموظفين النشطين فقط. لا يمكن تعيين المالك أو العملاء أو الحسابات المعطّلة.</p>
      </header>

      <form onSubmit={(event) => void onSubmit(event)} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="space-y-1">
          <p className="text-xs font-medium text-amber-800">إنشاء ← تعيين ← تأكيد ← متابعة</p>
          <h2 className="text-lg font-semibold">إنشاء مهمة وتعيينها</h2>
          <p className="text-sm text-slate-600">اختر مشروعاً وموظفاً نشطاً ثم أكّد التعيين. الموظف يرى المهمة المعيّنة فقط.</p>
        </div>
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
            {projects.map((project) => (
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
            الموعد النهائي
            <input type="date" value={deadline} onChange={(event) => setDeadline(event.target.value)} className={fieldClass} />
          </label>
        </div>
        <button
          type="submit"
          disabled={saving}
          className="min-h-11 rounded-lg bg-slate-900 px-4 text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {saving ? 'جاري الحفظ...' : 'تعيين المهمة'}
        </button>
      </form>

      <div className="space-y-3">
        <h2 className="text-lg font-semibold">المهام المعيّنة</h2>
        {tasks.summary ? (
          <p className="text-sm text-slate-600">
            الإجمالي {tasks.summary.total.toLocaleString('ar-SA')} · قيد التنفيذ {tasks.summary.in_progress.toLocaleString('ar-SA')} · مكتملة{' '}
            {tasks.summary.completed.toLocaleString('ar-SA')} · متأخرة {tasks.summary.overdue.toLocaleString('ar-SA')}
          </p>
        ) : null}
        <form
          className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
          onSubmit={(event) => {
            event.preventDefault()
            setPage(1)
            setAppliedSearch(search.trim())
          }}
        >
          <label className="block text-sm">
            بحث
            <input value={search} onChange={(event) => setSearch(event.target.value)} className={fieldClass} />
          </label>
          <label className="block text-sm">
            الحالة
            <select aria-label="تصفية الحالة" value={statusFilter} onChange={(event) => { setPage(1); setStatusFilter(event.target.value) }} className={fieldClass}>
              <option value="">الكل</option>
              {Object.entries(TASK_STATUS_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            الأولوية
            <select aria-label="تصفية الأولوية" value={priorityFilter} onChange={(event) => { setPage(1); setPriorityFilter(event.target.value) }} className={fieldClass}>
              <option value="">الكل</option>
              {Object.entries(TASK_PRIORITY_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            الموظف
            <select aria-label="تصفية الموظف" value={assigneeFilter} onChange={(event) => { setPage(1); setAssigneeFilter(event.target.value) }} className={fieldClass}>
              <option value="">الكل</option>
              {assignees.map((employee) => (
                <option key={employee.id} value={employee.id}>
                  {employee.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            المشروع
            <select aria-label="تصفية المشروع" value={projectFilter} onChange={(event) => { setPage(1); setProjectFilter(event.target.value) }} className={fieldClass}>
              <option value="">الكل</option>
              {projects.map((project) => (
                <option key={project.id} value={project.id}>
                  {project.title}
                </option>
              ))}
            </select>
          </label>
          <button type="submit" className="self-end rounded-lg border border-slate-300 px-4 py-2.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
            بحث
          </button>
        </form>
        {tasks.items.length === 0 ? (
          <WorkspaceEmptyState title="لا توجد مهام بعد." description="استخدم النموذج أعلاه لإنشاء أول مهمة." />
        ) : (
          <>
            <ul className="space-y-3 lg:hidden">
              {tasks.items.map((task) => (
                <li key={task.id} className="space-y-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                  <Link to={`/workspace/tasks/${task.id}`} className="font-semibold underline">
                    {task.title}
                  </Link>
                  <p className="text-sm text-slate-600">{task.project?.title ?? '—'} · {task.assignee?.name ?? '—'}</p>
                  <div className="flex flex-wrap gap-2">
                    <StatusBadge status={task.status} label={TASK_STATUS_LABELS[task.status] ?? task.status} />
                    <StatusBadge status={task.priority} label={TASK_PRIORITY_LABELS[task.priority] ?? task.priority} />
                    {task.is_overdue ? <StatusBadge status="OVERDUE" label="متأخرة" /> : null}
                  </div>
                  <p className="text-xs text-slate-500">الموعد: {formatTaskDeadline(task.deadline)}</p>
                </li>
              ))}
            </ul>
            <div className="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
            <table className="min-w-full text-right text-sm">
              <thead className="bg-slate-50 text-slate-600">
                <tr>
                  <th className="px-4 py-3 font-medium">المهمة</th>
                  <th className="px-4 py-3 font-medium">المشروع</th>
                  <th className="px-4 py-3 font-medium">الموظف</th>
                  <th className="px-4 py-3 font-medium">الأولوية</th>
                  <th className="px-4 py-3 font-medium">الحالة</th>
                  <th className="px-4 py-3 font-medium">الموعد</th>
                  <th className="px-4 py-3 font-medium">تاريخ الإنشاء</th>
                </tr>
              </thead>
              <tbody>
                {tasks.items.map((task) => (
                  <tr key={task.id} className="border-t border-slate-200">
                    <td className="px-4 py-3 font-medium">
                      <Link
                        to={`/workspace/tasks/${task.id}`}
                        className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                      >
                        {task.title}
                      </Link>
                    </td>
                    <td className="px-4 py-3">{task.project?.title ?? '—'}</td>
                    <td className="px-4 py-3">{task.assignee?.name ?? '—'}</td>
                    <td className="px-4 py-3"><StatusBadge status={task.priority} label={TASK_PRIORITY_LABELS[task.priority] ?? task.priority} /></td>
                    <td className="px-4 py-3"><StatusBadge status={task.status} label={TASK_STATUS_LABELS[task.status] ?? task.status} /></td>
                    <td className="whitespace-nowrap px-4 py-3">{formatTaskDeadline(task.deadline)}</td>
                    <td className="whitespace-nowrap px-4 py-3">{formatTaskAssignedDate(task.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          </>
        )}
        <WorkspacePagination meta={tasks.meta} onPage={setPage} />
      </div>
    </section>
  )
}

