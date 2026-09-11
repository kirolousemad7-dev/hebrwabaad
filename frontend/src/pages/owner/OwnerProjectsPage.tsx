import { Link } from 'react-router-dom'
import { useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { getOperationsProjects, type OperationsProjectListItem } from '../../services/operations'
import { describeApiError } from '../../utils/errors'

function healthBadge(status: string, label: string) {
  const tone =
    status === 'overdue'
      ? 'border-red-300 bg-red-50 text-red-900'
      : status === 'needs_attention'
        ? 'border-amber-300 bg-amber-50 text-amber-900'
        : 'border-slate-200 bg-slate-50 text-slate-700'
  return (
    <span className={`rounded-full border px-2 py-0.5 text-xs ${tone}`}>{label || status}</span>
  )
}

export function OwnerProjectsPage() {
  const [items, setItems] = useState<OperationsProjectListItem[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load(nextPage = page) {
    setLoading(true)
    setError(null)
    try {
      const response = await getOperationsProjects(nextPage)
      setItems(response.data.items ?? [])
      setLastPage(response.data.meta?.last_page ?? 1)
      setPage(response.data.meta?.current_page ?? nextPage)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل المشاريع.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load(1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold text-slate-900">المشاريع</h1>
        <p className="mt-1 text-sm text-slate-600">مساحة عمل المشاريع مع صحة التقدم والمهام.</p>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {loading ? <DashboardPanelSkeleton label="جاري تحميل المشاريع..." /> : null}
      {!loading && items.length === 0 && !error ? (
        <DashboardEmptyState title="لا توجد مشاريع." description="ستظهر المشاريع النشطة هنا." />
      ) : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load(page)} />
      ) : null}

      {!loading && items.length > 0 ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {items.map((project) => (
            <Link
              key={project.id}
              to={`/owner/projects/${project.id}`}
              className="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-amber-300"
            >
              <div className="flex items-start justify-between gap-2">
                <h2 className="font-semibold text-slate-900">{project.title}</h2>
                {healthBadge(project.health?.status ?? 'on_track', project.health?.label ?? 'على المسار')}
              </div>
              <p className="mt-2 text-xs text-slate-500">
                {project.customer?.name ?? 'بدون عميل'}
                {project.account_manager ? ` · ${project.account_manager.name}` : ''}
              </p>
              <div className="mt-3">
                <div className="mb-1 flex justify-between text-xs text-slate-500">
                  <span>التقدم</span>
                  <span>{Math.round(project.progress ?? 0).toLocaleString('ar-SA')}%</span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                  <div
                    className="h-full rounded-full bg-amber-500/80"
                    style={{ width: `${Math.min(100, Math.max(0, project.progress ?? 0))}%` }}
                  />
                </div>
              </div>
              <p className="mt-2 text-xs text-slate-500">
                الموعد: {project.deadline ?? '—'} · الحالة: {project.status}
              </p>
            </Link>
          ))}
        </div>
      ) : null}

      {lastPage > 1 ? (
        <div className="flex items-center justify-center gap-2">
          <button
            type="button"
            disabled={page <= 1 || loading}
            className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-50"
            onClick={() => void load(page - 1)}
          >
            السابق
          </button>
          <span className="text-sm text-slate-600">
            {page.toLocaleString('ar-SA')} / {lastPage.toLocaleString('ar-SA')}
          </span>
          <button
            type="button"
            disabled={page >= lastPage || loading}
            className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-50"
            onClick={() => void load(page + 1)}
          >
            التالي
          </button>
        </div>
      ) : null}
    </section>
  )
}
