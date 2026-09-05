import { FormEvent, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { WorkspaceEmptyState, WorkspaceErrorState, WorkspaceSkeleton } from '../../components/workspace/WorkspaceStatus'
import { WorkspacePagination } from '../../components/workspace/WorkspaceListControls'
import { useAuth } from '../../context/AuthContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { ApiRequestError } from '../../services/api'
import { createWorkspaceProject, getProjectCustomers, getWorkspaceProjects } from '../../services/workspaceProjects'
import type { Employee } from '../../types/api'
import { formatProjectDate, formatProjectProgress, PROJECT_STATUS_LABELS } from '../../utils/workspaceProjects'

const fieldClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function WorkspaceProjectsPage() {
  const { user } = useAuth()
  const isManager = user?.role === 'ACCOUNT_MANAGER'

  return isManager ? <AccountManagerProjectsPage /> : <AssignedProjectsPage />
}

function AssignedProjectsPage() {
  const [page, setPage] = useState(1)
  const { state, reload } = useAsyncData(() => getWorkspaceProjects(`?page=${page}&per_page=15`))
  const skipReload = useRef(true)

  useEffect(() => {
    if (skipReload.current) {
      skipReload.current = false
      return
    }
    void reload()
  }, [page, reload])

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل المشاريع..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold">المشاريع</h1>
        <p className="text-sm text-slate-600">المشاريع المرتبطة بالمهام المعيّنة لك فقط.</p>
      </header>
      {state.data.items.length === 0 ? (
        <WorkspaceEmptyState title="لا توجد مشاريع مرتبطة." description="سيظهر المشروع هنا بعد تعيين مهمة لك داخله." />
      ) : (
        <ProjectTable projects={state.data.items} />
      )}
      <WorkspacePagination meta={state.data.meta} onPage={setPage} />
    </section>
  )
}

function AccountManagerProjectsPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [appliedSearch, setAppliedSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const listQuery = useMemo(() => {
    const params = new URLSearchParams({ page: String(page), per_page: '15' })
    if (appliedSearch) {
      params.set('q', appliedSearch)
    }
    if (statusFilter) {
      params.set('status', statusFilter)
    }
    return `?${params.toString()}`
  }, [page, appliedSearch, statusFilter])
  const { state, reload } = useAsyncData(async () => {
    const [projects, customers] = await Promise.all([getWorkspaceProjects(listQuery), getProjectCustomers()])
    return { data: { projects: projects.data, customers: customers.data } }
  })
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [customerId, setCustomerId] = useState('')
  const [deadline, setDeadline] = useState('')
  const [startedAt, setStartedAt] = useState('')
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

  const customers = useMemo(
    () => (state.status === 'ready' ? state.data.customers : []),
    [state],
  )

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    try {
      await createWorkspaceProject({
        title,
        description: description || undefined,
        customer_id: Number(customerId),
        started_at: startedAt || undefined,
        deadline: deadline || undefined,
      })
      setTitle('')
      setDescription('')
      setCustomerId('')
      setDeadline('')
      setStartedAt('')
      setPage(1)
      await reload()
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إنشاء المشروع.')
    } finally {
      setSaving(false)
    }
  }

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل المشاريع..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
  }

  return (
    <section className="space-y-8">
      <header>
        <h1 className="text-2xl font-semibold">المشاريع</h1>
        <p className="text-sm text-slate-600">إنشاء مشاريع مرتبطة بعملاء حقيقيين ثم توزيع المهام على الموظفين.</p>
      </header>

      <form onSubmit={(event) => void onSubmit(event)} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold">إنشاء مشروع</h2>
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
          العميل
          <select required aria-label="عميل المشروع" value={customerId} onChange={(event) => setCustomerId(event.target.value)} className={fieldClass}>
            <option value="">اختر عميلاً</option>
            {customers.map((customer: Employee) => (
              <option key={customer.id} value={customer.id}>
                {customer.name}
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
          className="rounded-lg bg-slate-900 px-4 py-2.5 text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {saving ? 'جاري الحفظ...' : 'إنشاء المشروع'}
        </button>
      </form>

      <div className="space-y-3">
        <h2 className="text-lg font-semibold">المشاريع المُدارة</h2>
        <form
          className="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
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
            <select aria-label="تصفية حالة المشروع" value={statusFilter} onChange={(event) => { setPage(1); setStatusFilter(event.target.value) }} className={fieldClass}>
              <option value="">الكل</option>
              {Object.entries(PROJECT_STATUS_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <button type="submit" className="self-end rounded-lg border border-slate-300 px-4 py-2.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
            بحث
          </button>
        </form>
        {state.data.projects.items.length === 0 ? (
          <WorkspaceEmptyState title="لا توجد مشاريع بعد." description="أنشئ مشروعاً مرتبطاً بعميل نشط." />
        ) : (
          <ProjectTable projects={state.data.projects.items} />
        )}
        <WorkspacePagination meta={state.data.projects.meta} onPage={setPage} />
      </div>
    </section>
  )
}

function ProjectTable({ projects }: { projects: { id: number; title: string; status: string; customer?: { name: string } | null; deadline: string | null; progress: { percent: number; total: number; completed: number } }[] }) {
  return (
    <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
      <table className="min-w-full text-right text-sm">
        <thead className="bg-slate-50 text-slate-600">
          <tr>
            <th className="px-4 py-3 font-medium">المشروع</th>
            <th className="px-4 py-3 font-medium">العميل</th>
            <th className="px-4 py-3 font-medium">الحالة</th>
            <th className="px-4 py-3 font-medium">التقدم</th>
            <th className="px-4 py-3 font-medium">الموعد</th>
          </tr>
        </thead>
        <tbody>
          {projects.map((project) => (
            <tr key={project.id} className="border-t border-slate-200">
              <td className="px-4 py-3 font-medium">
                <Link
                  to={`/workspace/projects/${project.id}`}
                  className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  {project.title}
                </Link>
              </td>
              <td className="px-4 py-3">{project.customer?.name ?? '—'}</td>
              <td className="px-4 py-3">{PROJECT_STATUS_LABELS[project.status] ?? project.status}</td>
              <td className="px-4 py-3">
                {formatProjectProgress(project.progress.percent)} · {project.progress.completed.toLocaleString('ar-SA')}/
                {project.progress.total.toLocaleString('ar-SA')}
              </td>
              <td className="px-4 py-3">{formatProjectDate(project.deadline)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
