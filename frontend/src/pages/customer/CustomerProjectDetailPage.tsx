import { Link, useParams } from 'react-router-dom'
import { CustomerProjectActivityList } from '../../components/customer/CustomerProjectActivityList'
import { FileLibrary } from '../../components/files/FileLibrary'
import { CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { SupportContextButton } from '../../components/support/SupportContextButton'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerProject, getCustomerProjectActivities } from '../../services/customerDashboard'
import { formatProjectDate, formatProjectProgress, PROJECT_STATUS_LABELS } from '../../utils/workspaceProjects'

function phaseMarker(status: string): string {
  if (status === 'DONE') return '✓'
  if (status === 'IN_PROGRESS') return '●'
  return '○'
}

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
  const percent = Math.min(100, Math.max(0, progress.percent))
  const companyName =
    typeof project.client_profile?.company_name === 'string' ? project.client_profile.company_name : null
  const objective = typeof project.brief?.objective === 'string' ? project.brief.objective : null
  const desiredOutcome =
    typeof project.brief?.desired_outcome === 'string' ? project.brief.desired_outcome : null

  return (
    <section className="space-y-6">
      <header className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
        <p className="text-xs text-slate-500">مشروع #{project.id}</p>
        {companyName ? <p className="text-sm font-medium text-slate-700">{companyName}</p> : null}
        <h1 className="text-2xl font-semibold">{project.title}</h1>
        <div className="flex flex-wrap gap-2">
          <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium">
            {PROJECT_STATUS_LABELS[project.status] ?? project.status}
          </span>
          <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium">
            التقدم {formatProjectProgress(progress.percent)}
          </span>
        </div>
        <div className="h-3 overflow-hidden rounded-full bg-slate-100">
          <div className="h-full rounded-full bg-brand-primary/80" style={{ width: `${percent}%` }} />
        </div>
        <dl className="grid gap-3 sm:grid-cols-3">
          <div>
            <dt className="text-xs text-slate-500">المرحلة الحالية</dt>
            <dd className="text-sm font-medium">{project.current_phase?.title ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">المعلم التالي</dt>
            <dd className="text-sm font-medium">
              {project.next_milestone?.title ?? '—'}
              {project.next_milestone?.due_date ? ` · ${formatProjectDate(project.next_milestone.due_date)}` : ''}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-slate-500">التسليم المتوقع</dt>
            <dd className="text-sm font-medium">{formatProjectDate(project.deadline)}</dd>
          </div>
        </dl>
      </header>

      {project.description ? <p className="max-w-2xl text-sm leading-7 text-slate-600">{project.description}</p> : null}
      {objective || desiredOutcome ? (
        <article className="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-700">
          {objective ? <p>الهدف: {objective}</p> : null}
          {desiredOutcome ? <p className="mt-2">النتيجة المتوقعة: {desiredOutcome}</p> : null}
        </article>
      ) : null}

      {(project.phases?.length ?? 0) > 0 ? (
        <article className="rounded-2xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold">الخط الزمني</h2>
          <ul className="mt-3 space-y-2 text-sm">
            {project.phases?.map((phase) => (
              <li key={phase.id} className="flex items-center gap-2">
                <span className="w-4 text-center">{phaseMarker(phase.status)}</span>
                <span className={phase.status === 'DONE' ? 'text-slate-500 line-through' : 'text-slate-900'}>
                  {phase.title}
                </span>
                <span className="text-xs text-slate-500">
                  {phase.status === 'DONE' ? 'مكتمل' : phase.status === 'IN_PROGRESS' ? 'جارٍ' : 'قادم'}
                </span>
              </li>
            ))}
          </ul>
        </article>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2">
        <article className="rounded-2xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold">معالم مكتملة</h2>
          {(project.completed_milestones?.length ?? 0) === 0 ? (
            <p className="mt-2 text-sm text-slate-500">لا معالم مكتملة ظاهرة بعد.</p>
          ) : (
            <ul className="mt-3 space-y-2 text-sm">
              {project.completed_milestones?.map((milestone) => (
                <li key={milestone.id}>✓ {milestone.title}</li>
              ))}
            </ul>
          )}
        </article>
        <article className="rounded-2xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold">معالم قادمة</h2>
          {(project.upcoming_milestones?.length ?? 0) === 0 ? (
            <p className="mt-2 text-sm text-slate-500">لا معالم قادمة ظاهرة.</p>
          ) : (
            <ul className="mt-3 space-y-2 text-sm">
              {project.upcoming_milestones?.map((milestone) => (
                <li key={milestone.id}>
                  ○ {milestone.title}
                  {milestone.due_date ? (
                    <span className="ms-2 text-xs text-slate-500">{formatProjectDate(milestone.due_date)}</span>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </article>
      </div>

      <article className="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold">مواعيد مهمة</h2>
        <ul className="mt-3 space-y-2 text-sm text-slate-700">
          <li>البداية: {formatProjectDate(project.started_at)}</li>
          <li>
            المعلم التالي:{' '}
            {project.next_milestone
              ? `${project.next_milestone.title}${project.next_milestone.due_date ? ` · ${formatProjectDate(project.next_milestone.due_date)}` : ''}`
              : '—'}
          </li>
          <li>التسليم النهائي: {formatProjectDate(project.deadline)}</li>
          <li>مدير الحساب: {project.account_manager?.name ?? '—'}</li>
        </ul>
      </article>

      {(project.client_action_items?.length ?? 0) > 0 ? (
        <article className="rounded-2xl border border-amber-200 bg-amber-50 p-5">
          <h2 className="font-semibold text-amber-950">إجراء مطلوب منك</h2>
          <ul className="mt-3 space-y-2 text-sm text-amber-950">
            {project.client_action_items?.map((item) => (
              <li key={item.id}>
                {item.title}
                {item.deadline ? ` · قبل ${formatProjectDate(item.deadline)}` : ''}
              </li>
            ))}
          </ul>
        </article>
      ) : null}

      {(project.references?.length ?? 0) > 0 ? (
        <article className="rounded-2xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold">مراجع مشتركة</h2>
          <ul className="mt-3 space-y-2 text-sm">
            {project.references?.map((reference) => (
              <li key={reference.id}>
                {reference.url ? (
                  <a href={reference.url} target="_blank" rel="noreferrer" className="underline">
                    {reference.title}
                  </a>
                ) : (
                  reference.title
                )}
              </li>
            ))}
          </ul>
        </article>
      ) : null}

      {(project.deliverables?.length ?? 0) > 0 || (project.service_progress?.length ?? 0) > 0 ? (
        <article className="rounded-2xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold">التسليمات</h2>
          <ul className="mt-3 divide-y divide-slate-100">
            {(project.deliverables?.length ?? 0) > 0
              ? project.deliverables?.map((line) => (
                  <li key={line.id} className="flex items-center justify-between gap-3 py-3 text-sm">
                    <span className="font-medium text-slate-900">
                      {line.name}
                      {line.quantity > 1 ? ` × ${line.quantity.toLocaleString('ar-SA')}` : ''}
                    </span>
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                      {line.status_label}
                    </span>
                  </li>
                ))
              : project.service_progress?.map((line) => (
                  <li
                    key={`${line.service_name}-${line.quantity}`}
                    className="flex items-center justify-between gap-3 py-3 text-sm"
                  >
                    <span className="font-medium text-slate-900">
                      {line.service_name}
                      {line.quantity > 1 ? ` × ${line.quantity.toLocaleString('ar-SA')}` : ''}
                    </span>
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                      {line.status_label}
                    </span>
                  </li>
                ))}
          </ul>
        </article>
      ) : null}

      <SupportContextButton projectId={project.id} />

      <article className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold">أحدث التحديثات</h2>
        <CustomerProjectActivitySection projectId={project.id} />
      </article>

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

function CustomerProjectActivitySection({ projectId }: { projectId: number }) {
  const { state } = useAsyncData(
    () => getCustomerProjectActivities(projectId, 1, 12),
    [projectId],
  )

  if (state.status === 'error') {
    return <CustomerProjectActivityList activities={[]} error={state.message} />
  }

  return (
    <CustomerProjectActivityList
      activities={state.status === 'ready' ? state.data.items : []}
      loading={state.status === 'loading'}
    />
  )
}
