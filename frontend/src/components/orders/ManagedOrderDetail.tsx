import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { OrderTimeline } from './OrderTimeline'
import { WorkspaceErrorState, WorkspaceSkeleton } from '../workspace/WorkspaceStatus'
import { useAsyncData } from '../../hooks/useAsyncData'
import { ApiRequestError } from '../../services/api'
import { getManagedOrder, updateManagedOrderStatus } from '../../services/orders'
import { describeOrderError, describeOrderLoadError, formatOrderProgress } from '../../utils/orderTracking'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'

type ManagedOrderDetailProps = {
  listPath: '/workspace/orders' | '/owner/orders'
  projectPath?: (id: number) => string
}

export function ManagedOrderDetail({ listPath, projectPath }: ManagedOrderDetailProps) {
  const { orderId } = useParams()
  const numericId = Number(orderId)
  const { state, reload } = useAsyncData(() => getManagedOrder(numericId))
  const [updating, setUpdating] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  if (!Number.isInteger(numericId) || numericId <= 0) {
    return <WorkspaceErrorState message="تعذر تحميل الطلب." onRetry={() => undefined} />
  }

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل الطلب..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={describeOrderLoadError(state.message)} onRetry={() => void reload()} />
  }

  const order = state.data

  async function changeStatus(status: string) {
    setError(null)
    setUpdating(status)

    try {
      await updateManagedOrderStatus(numericId, status)
      await reload()
    } catch (caught) {
      const statusCode = caught instanceof ApiRequestError ? caught.status : 500
      setError(describeOrderError(statusCode === 403 ? 403 : statusCode === 422 || statusCode === 409 ? statusCode : 500))
    } finally {
      setUpdating(null)
    }
  }

  return (
    <section className="space-y-6">
      <p>
        <Link to={listPath} className="text-sm underline">
          كل الطلبات
        </Link>
      </p>

      <header className="space-y-2">
        <p className="text-xs text-slate-500" dir="ltr">
          {order.reference}
        </p>
        <h1 className="text-2xl font-semibold">{order.title}</h1>
        <div className="flex flex-wrap items-center gap-2 text-sm text-slate-600">
          <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-800">{order.status_label}</span>
          <span>التقدم: {formatOrderProgress(order.progress)}</span>
          <span>أُنشئ: {formatDashboardDateTime(order.created_at)}</span>
        </div>
      </header>

      {order.description ? <p className="max-w-2xl text-sm leading-7 text-slate-600">{order.description}</p> : null}

      {error ? <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p> : null}

      {order.allowed_transitions.length > 0 ? (
        <div className="flex flex-wrap gap-2">
          {order.allowed_transitions.map((transition) => (
            <button
              key={transition.status}
              type="button"
              disabled={updating !== null}
              onClick={() => void changeStatus(transition.status)}
              className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            >
              {updating === transition.status ? 'جاري التحديث...' : `نقل إلى ${transition.label}`}
            </button>
          ))}
        </div>
      ) : (
        <p className="text-sm text-slate-500">لا توجد انتقالات متاحة لهذه المرحلة.</p>
      )}

      <section className="space-y-3">
        <h2 className="text-lg font-semibold">دورة حياة الطلب</h2>
        <OrderTimeline steps={order.timeline} />
      </section>

      <dl className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <div>
          <dt className="text-xs text-slate-500">العميل</dt>
          <dd>{order.customer?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">مدير الحساب</dt>
          <dd>{order.account_manager?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">المشروع</dt>
          <dd>
            {order.project && projectPath ? (
              <Link to={projectPath(order.project.id)} className="underline">
                {order.project.title}
              </Link>
            ) : (
              (order.project?.title ?? '—')
            )}
          </dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">الخدمة</dt>
          <dd>{order.service?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">الباقة</dt>
          <dd>{order.package?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">آخر تحديث</dt>
          <dd>{formatDashboardDateTime(order.updated_at)}</dd>
        </div>
      </dl>

      {order.history && order.history.length > 0 ? (
        <section className="space-y-3">
          <h2 className="text-lg font-semibold">سجل الحالة</h2>
          <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            {order.history.map((item, index) => (
              <li key={`${item.to_status}-${item.created_at}-${index}`} className="px-4 py-3 text-sm">
                <p className="font-medium">{item.to_status_label}</p>
                <p className="mt-1 text-xs text-slate-500">
                  {formatDashboardDateTime(item.created_at)}
                  {item.changed_by ? ` · ${item.changed_by.name}` : ''}
                </p>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </section>
  )
}
