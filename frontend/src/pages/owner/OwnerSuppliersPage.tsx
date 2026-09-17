import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  activateAdminSupplier,
  deactivateAdminSupplier,
  deleteAdminSupplier,
  getAdminSuppliers,
  publishAdminSupplier,
  unpublishAdminSupplier,
} from '../../services/supplierWorkspace'
import { describeApiError } from '../../utils/errors'

export function OwnerSuppliersPage() {
  const [filters, setFilters] = useState({
    q: '',
    status: '',
    verification_status: '',
    city: '',
    category: '',
    service: '',
  })
  const [applied, setApplied] = useState(filters)
  const query = useMemo(() => ({
    q: applied.q || undefined,
    status: applied.status || undefined,
    verification_status: applied.verification_status || undefined,
    city: applied.city || undefined,
    category: applied.category || undefined,
    service: applied.service || undefined,
  }), [applied])

  const { state, reload } = useAsyncData(() => getAdminSuppliers(query), [query])
  const toast = useToast()
  const [error, setError] = useState<string | null>(null)

  const items = state.status === 'ready' ? state.data.items : []

  return (
    <DashboardSection
      title="الموردين"
      description="إدارة الموردين الداخليين. لا يظهر للعامة إلا النشط المنشور المعتمد، وبيانات الاتصال الداخلية مخفية عن العملاء."
      action={<Link to="/owner/suppliers/new" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm leading-11 text-white">مورد جديد</Link>}
    >
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <form
        className="grid gap-2 rounded-2xl border bg-white p-4 sm:grid-cols-3 lg:grid-cols-6"
        onSubmit={(event) => {
          event.preventDefault()
          setApplied({ ...filters })
        }}
      >
        <input value={filters.q} onChange={(e) => setFilters((f) => ({ ...f, q: e.target.value }))} placeholder="بحث" className="rounded-md border px-3 py-2 text-sm" />
        <select value={filters.status} onChange={(e) => setFilters((f) => ({ ...f, status: e.target.value }))} className="rounded-md border px-3 py-2 text-sm">
          <option value="">كل الحالات</option>
          <option value="PENDING">PENDING</option>
          <option value="ACTIVE">ACTIVE</option>
          <option value="SUSPENDED">SUSPENDED</option>
          <option value="REJECTED">REJECTED</option>
          <option value="BLOCKED">BLOCKED</option>
        </select>
        <select value={filters.verification_status} onChange={(e) => setFilters((f) => ({ ...f, verification_status: e.target.value }))} className="rounded-md border px-3 py-2 text-sm">
          <option value="">كل التحقق</option>
          <option value="UNVERIFIED">UNVERIFIED</option>
          <option value="EMAIL_VERIFIED">EMAIL_VERIFIED</option>
          <option value="PHONE_VERIFIED">PHONE_VERIFIED</option>
          <option value="FULLY_VERIFIED">FULLY_VERIFIED</option>
        </select>
        <input value={filters.city} onChange={(e) => setFilters((f) => ({ ...f, city: e.target.value }))} placeholder="المدينة" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.category} onChange={(e) => setFilters((f) => ({ ...f, category: e.target.value }))} placeholder="التصنيف" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.service} onChange={(e) => setFilters((f) => ({ ...f, service: e.target.value }))} placeholder="خدمة" className="rounded-md border px-3 py-2 text-sm" />
        <button type="submit" className="min-h-11 rounded-xl border px-4 text-sm sm:col-span-3 lg:col-span-6">تطبيق الفلاتر</button>
      </form>

      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل الموردين..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}

      {items.length === 0 && state.status === 'ready' ? (
        <DashboardEmptyState title="لا يوجد موردون." description="أنشئ مورداً ثم اعتمد ملفه قبل ظهوره للعامة." />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-right">
              <tr>
                <th className="px-3 py-2">الكود</th>
                <th className="px-3 py-2">الاسم</th>
                <th className="px-3 py-2">الحالة</th>
                <th className="px-3 py-2">التحقق</th>
                <th className="px-3 py-2">المدينة</th>
                <th className="px-3 py-2">منشور</th>
                <th className="px-3 py-2">المحتوى</th>
                <th className="px-3 py-2">إجراءات</th>
              </tr>
            </thead>
            <tbody>
              {items.map((supplier) => (
                <tr key={supplier.id} className="border-t">
                  <td className="px-3 py-2 font-mono text-xs">{supplier.supplier_code ?? '—'}</td>
                  <td className="px-3 py-2 font-medium">{supplier.display_name || supplier.name}</td>
                  <td className="px-3 py-2">{supplier.status ?? supplier.profile_status}</td>
                  <td className="px-3 py-2">{supplier.verification_status ?? '—'}</td>
                  <td className="px-3 py-2">{supplier.city ?? supplier.location ?? '—'}</td>
                  <td className="px-3 py-2">{supplier.is_published ? 'نعم' : 'لا'}</td>
                  <td className="px-3 py-2">{supplier.content_count} / {supplier.products_count}</td>
                  <td className="px-3 py-2">
                    <div className="flex flex-wrap gap-1">
                      <Link className="rounded-lg border px-2 py-1" to={`/owner/suppliers/${supplier.id}`}>إدارة</Link>
                      <button type="button" className="rounded-lg border px-2 py-1" onClick={() => void publishAdminSupplier(supplier.id).then(() => reload()).catch((caught) => setError(describeApiError(caught, 'تعذر النشر.')))}>نشر</button>
                      <button type="button" className="rounded-lg border px-2 py-1" onClick={() => void unpublishAdminSupplier(supplier.id).then(() => reload())}>إلغاء نشر</button>
                      <button type="button" className="rounded-lg border px-2 py-1" onClick={() => void (supplier.is_active ? deactivateAdminSupplier(supplier.id) : activateAdminSupplier(supplier.id)).then(() => reload())}>{supplier.is_active ? 'إيقاف' : 'تفعيل'}</button>
                      <button type="button" className="rounded-lg border px-2 py-1 text-rose-700" onClick={() => void deleteAdminSupplier(supplier.id).then(() => { toast.success('تم الحذف.'); return reload() }).catch((caught) => setError(describeApiError(caught, 'تعذر الحذف.')))}>حذف</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </DashboardSection>
  )
}
