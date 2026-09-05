import { Link, useParams } from 'react-router-dom'
import { FileLibrary } from '../../components/files/FileLibrary'
import { CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { SupportContextButton } from '../../components/support/SupportContextButton'
import { OrderTimeline } from '../../components/orders/OrderTimeline'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerOrder } from '../../services/orders'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import { describeOrderLoadError, formatOrderProgress } from '../../utils/orderTracking'
import { customerPayPath, formatPaymentAmount, orderNeedsPayment } from '../../utils/payments'

export function CustomerOrderDetailPage() {
  const { orderId } = useParams()
  const numericId = Number(orderId)
  const { state, reload } = useAsyncData(() => getCustomerOrder(numericId))

  if (!Number.isInteger(numericId) || numericId <= 0) {
    return <CatalogErrorState message="تعذر تحميل الطلب." onRetry={() => undefined} />
  }

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل الطلب..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message={describeOrderLoadError(state.message)} onRetry={() => void reload()} />
  }

  const order = state.data

  return (
    <section className="space-y-6">
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

      {orderNeedsPayment(order) ? (
        <article className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="font-semibold">الدفع</h2>
              <p className="text-sm text-slate-600">
                المبلغ المستحق:{' '}
                {formatPaymentAmount(order.payable?.amount ?? null, order.payable?.currency ?? 'SAR')}
              </p>
            </div>
            {order.latest_payment ? (
              <StatusBadge status={order.latest_payment.status} label={order.latest_payment.status_label} />
            ) : null}
          </div>
          <Link
            to={customerPayPath(order.id)}
            className="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 py-2 text-sm text-white"
          >
            إتمام الدفع
          </Link>
        </article>
      ) : null}

      <section className="space-y-3">
        <h2 className="text-lg font-semibold">دورة حياة الطلب</h2>
        <OrderTimeline steps={order.timeline} />
      </section>

      <dl className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <div>
          <dt className="text-xs text-slate-500">الخدمة</dt>
          <dd>{order.service?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">الباقة</dt>
          <dd>{order.package?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">مدير الحساب</dt>
          <dd>{order.account_manager?.name ?? '—'}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">آخر تحديث</dt>
          <dd>{formatDashboardDateTime(order.updated_at)}</dd>
        </div>
      </dl>

      <SupportContextButton orderId={order.id} />

      <article className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold">ملفات الطلب</h2>
        <FileLibrary
          scope="customer"
          query={`?order_id=${order.id}&per_page=15`}
          orders={[{ id: order.id, label: order.reference }]}
        />
      </article>

      <div className="flex flex-col gap-2">
        {order.project ? (
          <Link to={`/dashboard/projects/${order.project.id}`} className="inline-block text-sm underline">
            المشروع المرتبط: {order.project.title}
          </Link>
        ) : null}
        <Link to="/dashboard/orders" className="inline-block text-sm underline">
          كل الطلبات
        </Link>
      </div>
    </section>
  )
}
