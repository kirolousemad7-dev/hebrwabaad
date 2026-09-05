import { FormEvent, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { WorkspaceEmptyState, WorkspaceErrorState, WorkspaceSkeleton } from '../workspace/WorkspaceStatus'
import { WorkspacePagination } from '../workspace/WorkspaceListControls'
import { useAsyncData } from '../../hooks/useAsyncData'
import { ApiRequestError } from '../../services/api'
import { getPublicPackages, getPublicServices } from '../../services/catalog'
import { createManagedOrder, getManagedOrderLookups, getManagedOrders } from '../../services/orders'
import { formatOrderProgress, ORDER_STATUS_LABELS, ORDER_STATUS_SEQUENCE } from '../../utils/orderTracking'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'

const fieldClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type ManagedOrdersBoardProps = {
  detailBase: '/workspace/orders' | '/owner/orders'
  isOwner: boolean
}

export function ManagedOrdersBoard({ detailBase, isOwner }: ManagedOrdersBoardProps) {
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
    const [orders, lookups, services, packages] = await Promise.all([
      getManagedOrders(listQuery),
      getManagedOrderLookups(),
      getPublicServices(),
      getPublicPackages(),
    ])

    return {
      data: {
        orders: orders.data,
        lookups: lookups.data,
        services: services.data,
        packages: packages.data,
      },
    }
  })

  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [customerId, setCustomerId] = useState('')
  const [managerId, setManagerId] = useState('')
  const [projectId, setProjectId] = useState('')
  const [serviceId, setServiceId] = useState('')
  const [packageId, setPackageId] = useState('')
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

  const projects =
    state.status === 'ready'
      ? state.data.lookups.projects.filter((project) => !customerId || String(project.customer_id) === customerId)
      : []

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    try {
      await createManagedOrder({
        title,
        description: description || undefined,
        customer_id: Number(customerId),
        account_manager_id: isOwner && managerId ? Number(managerId) : undefined,
        project_id: projectId ? Number(projectId) : undefined,
        service_id: serviceId ? Number(serviceId) : undefined,
        package_id: packageId ? Number(packageId) : undefined,
      })
      setTitle('')
      setDescription('')
      setCustomerId('')
      setManagerId('')
      setProjectId('')
      setServiceId('')
      setPackageId('')
      setPage(1)
      await reload()
    } catch (caught) {
      setError(caught instanceof ApiRequestError ? caught.message : 'تعذر إنشاء الطلب.')
    } finally {
      setSaving(false)
    }
  }

  if (state.status === 'loading') {
    return <WorkspaceSkeleton label="جاري تحميل الطلبات..." />
  }

  if (state.status === 'error') {
    return <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
  }

  return (
    <section className="space-y-8">
      <header>
        <h1 className="text-2xl font-semibold">الطلبات</h1>
        <p className="text-sm text-slate-600">متابعة دورة حياة الطلبات وتحديث الحالة وفق الانتقالات المسموحة فقط.</p>
      </header>

      <form onSubmit={(event) => void onSubmit(event)} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold">إنشاء طلب</h2>
        {error ? <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p> : null}
        <label className="block text-sm">
          عنوان الطلب
          <input required value={title} onChange={(event) => setTitle(event.target.value)} className={fieldClass} />
        </label>
        <label className="block text-sm">
          الوصف
          <textarea value={description} onChange={(event) => setDescription(event.target.value)} className={fieldClass} rows={3} />
        </label>
        <label className="block text-sm">
          العميل
          <select required aria-label="عميل الطلب" value={customerId} onChange={(event) => setCustomerId(event.target.value)} className={fieldClass}>
            <option value="">اختر عميلاً</option>
            {state.data.lookups.customers.map((customer) => (
              <option key={customer.id} value={customer.id}>
                {customer.name}
              </option>
            ))}
          </select>
        </label>
        {isOwner ? (
          <label className="block text-sm">
            مدير الحساب
            <select required aria-label="مدير الحساب" value={managerId} onChange={(event) => setManagerId(event.target.value)} className={fieldClass}>
              <option value="">اختر مدير حساب</option>
              {state.data.lookups.account_managers.map((manager) => (
                <option key={manager.id} value={manager.id}>
                  {manager.name}
                </option>
              ))}
            </select>
          </label>
        ) : null}
        <div className="grid gap-4 md:grid-cols-3">
          <label className="block text-sm">
            المشروع
            <select aria-label="مشروع الطلب" value={projectId} onChange={(event) => setProjectId(event.target.value)} className={fieldClass}>
              <option value="">بدون مشروع</option>
              {projects.map((project) => (
                <option key={project.id} value={project.id}>
                  {project.title}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            الخدمة
            <select aria-label="خدمة الطلب" value={serviceId} onChange={(event) => setServiceId(event.target.value)} className={fieldClass}>
              <option value="">بدون خدمة</option>
              {state.data.services.map((service) => (
                <option key={service.id} value={service.id}>
                  {service.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            الباقة
            <select aria-label="باقة الطلب" value={packageId} onChange={(event) => setPackageId(event.target.value)} className={fieldClass}>
              <option value="">بدون باقة</option>
              {state.data.packages.map((pkg) => (
                <option key={pkg.id} value={pkg.id}>
                  {pkg.name}
                </option>
              ))}
            </select>
          </label>
        </div>
        <button
          type="submit"
          disabled={saving}
          className="rounded-lg bg-slate-900 px-4 py-2.5 text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {saving ? 'جاري الحفظ...' : 'إنشاء الطلب'}
        </button>
      </form>

      <div className="space-y-3">
        <h2 className="text-lg font-semibold">قائمة الطلبات</h2>
        <form
          className="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
          onSubmit={(event) => {
            event.preventDefault()
            setPage(1)
            setAppliedSearch(search.trim())
          }}
        >
          <label className="block text-sm">
            بحث بالمرجع أو العنوان
            <input value={search} onChange={(event) => setSearch(event.target.value)} className={fieldClass} />
          </label>
          <label className="block text-sm">
            الحالة
            <select
              aria-label="تصفية حالة الطلب"
              value={statusFilter}
              onChange={(event) => {
                setPage(1)
                setStatusFilter(event.target.value)
              }}
              className={fieldClass}
            >
              <option value="">الكل</option>
              {ORDER_STATUS_SEQUENCE.map((status) => (
                <option key={status} value={status}>
                  {ORDER_STATUS_LABELS[status]}
                </option>
              ))}
            </select>
          </label>
          <button type="submit" className="self-end rounded-lg border border-slate-300 px-4 py-2.5">
            بحث
          </button>
        </form>

        {state.data.orders.items.length === 0 ? (
          <WorkspaceEmptyState title="لا توجد طلبات بعد." description="أنشئ طلباً مرتبطاً بعميل حقيقي." />
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full text-right text-sm">
              <thead className="bg-slate-50 text-slate-600">
                <tr>
                  <th className="px-4 py-3 font-medium">المرجع</th>
                  <th className="px-4 py-3 font-medium">الطلب</th>
                  <th className="px-4 py-3 font-medium">العميل</th>
                  <th className="px-4 py-3 font-medium">الحالة</th>
                  <th className="px-4 py-3 font-medium">التقدم</th>
                  <th className="px-4 py-3 font-medium">التاريخ</th>
                </tr>
              </thead>
              <tbody>
                {state.data.orders.items.map((order) => (
                  <tr key={order.id} className="border-t border-slate-200">
                    <td className="px-4 py-3" dir="ltr">
                      {order.reference}
                    </td>
                    <td className="px-4 py-3 font-medium">
                      <Link to={`${detailBase}/${order.id}`} className="underline">
                        {order.title}
                      </Link>
                    </td>
                    <td className="px-4 py-3">{order.customer?.name ?? '—'}</td>
                    <td className="px-4 py-3">{order.status_label}</td>
                    <td className="px-4 py-3">{formatOrderProgress(order.progress)}</td>
                    <td className="px-4 py-3">{formatDashboardDateTime(order.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <WorkspacePagination meta={state.data.orders.meta} onPage={setPage} />
      </div>
    </section>
  )
}
