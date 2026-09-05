import { Link, useParams } from 'react-router-dom'
import { FileLibrary } from '../../components/files/FileLibrary'
import { CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { SupportContextButton } from '../../components/support/SupportContextButton'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerProject } from '../../services/customerDashboard'
import { formatProjectDate, formatProjectProgress, PROJECT_STATUS_LABELS } from '../../utils/workspaceProjects'

export function CustomerProjectDetailPage() {
  const { projectId } = useParams()
  const numericId = Number(projectId)
  const { state, reload } = useAsyncData(() => getCustomerProject(numericId))

  if (!Number.isInteger(numericId) || numericId <= 0) {
    return <CatalogErrorState message="المشروع غير صالح." onRetry={() => undefined} />
  }

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل المشروع..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message="حدث خطأ أثناء تحميل البيانات. حاول مرة أخرى." onRetry={() => void reload()} />
  }

  const project = state.data
  const progress = project.progress

  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <p className="text-xs text-slate-500">مشروع #{project.id}</p>
        <h1 className="text-2xl font-semibold">{project.title}</h1>
        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium">
          {PROJECT_STATUS_LABELS[project.status] ?? project.status}
        </span>
      </header>

      {project.description ? <p className="max-w-2xl text-sm leading-7 text-slate-600">{project.description}</p> : null}

      <dl className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <div>
          <dt className="text-xs text-slate-500">البداية</dt>
          <dd>{formatProjectDate(project.started_at)}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">التسليم المتوقع</dt>
          <dd>{formatProjectDate(project.deadline)}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">التقدم</dt>
          <dd>{formatProjectProgress(progress.percent)}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">مدير الحساب</dt>
          <dd>{project.account_manager?.name ?? '—'}</dd>
        </div>
      </dl>

      <article className="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold">ملخص المهام</h2>
        <ul className="mt-3 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
          <li>الإجمالي: {progress.total.toLocaleString('ar-SA')}</li>
          <li>قيد التنفيذ: {progress.in_progress.toLocaleString('ar-SA')}</li>
          <li>المكتمل: {progress.completed.toLocaleString('ar-SA')}</li>
          <li>المراجعة: {progress.review.toLocaleString('ar-SA')}</li>
        </ul>
      </article>

      <SupportContextButton projectId={project.id} />

      <article className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold">ملفات المشروع</h2>
        <FileLibrary
          scope="customer"
          query={`?project_id=${project.id}&per_page=15`}
          projects={[{ id: project.id, label: project.title }]}
        />
      </article>

      <Link to="/dashboard/projects" className="inline-block text-sm underline">
        كل المشاريع
      </Link>
    </section>
  )
}
