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
    country: '',
    category: '',
    service: '',
    product: '',
    tag: '',
    availability: '',
    visibility: '',
    price_min: '',
    price_max: '',
  })
  const [applied, setApplied] = useState(filters)
  const query = useMemo(() => ({
    q: applied.q || undefined,
    status: applied.status || undefined,
    verification_status: applied.verification_status || undefined,
    city: applied.city || undefined,
    country: applied.country || undefined,
    category: applied.category || undefined,
    service: applied.service || undefined,
    product: applied.product || undefined,
    tag: applied.tag || undefined,
    availability: applied.availability || undefined,
    visibility: applied.visibility || undefined,
    price_min: applied.price_min || undefined,
    price_max: applied.price_max || undefined,
  }), [applied])

  const { state, reload } = useAsyncData(() => getAdminSuppliers(query), [query])
  const toast = useToast()
  const [error, setError] = useState<string | null>(null)

  const items = state.status === 'ready' ? state.data.items : []

  return (
    <DashboardSection
      title="الموردين"
      description="البحث حسب الاسم / الخدمة / المنتج / التصنيف / الوسم / المدينة / الدولة / السعر / التوفر / التحقق / الحالة. الظهور العام افتراضياً PRIVATE."
      action={<Link to="/owner/suppliers/new" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm leading-11 text-white">مورد جديد</Link>}
    >
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <form
        className="grid gap-2 rounded-2xl border bg-white p-4 sm:grid-cols-3 lg:grid-cols-4"
        onSubmit={(event) => {
          event.preventDefault()
          setApplied({ ...filters })
        }}
      >
        <input value={filters.q} onChange={(e) => setFilters((f) => ({ ...f, q: e.target.value }))} placeholder="بحث بالاسم" className="rounded-md border px-3 py-2 text-sm" />
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
        <select value={filters.visibility} onChange={(e) => setFilters((f) => ({ ...f, visibility: e.target.value }))} className="rounded-md border px-3 py-2 text-sm">
          <option value="">كل الظهور</option>
          <option value="PRIVATE">PRIVATE</option>
          <option value="INTERNAL">INTERNAL</option>
          <option value="PUBLIC">PUBLIC</option>
        </select>
        <input value={filters.city} onChange={(e) => setFilters((f) => ({ ...f, city: e.target.value }))} placeholder="المدينة" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.country} onChange={(e) => setFilters((f) => ({ ...f, country: e.target.value }))} placeholder="الدولة" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.category} onChange={(e) => setFilters((f) => ({ ...f, category: e.target.value }))} placeholder="التصنيف" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.tag} onChange={(e) => setFilters((f) => ({ ...f, tag: e.target.value }))} placeholder="وسم" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.service} onChange={(e) => setFilters((f) => ({ ...f, service: e.target.value }))} placeholder="خدمة" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.product} onChange={(e) => setFilters((f) => ({ ...f, product: e.target.value }))} placeholder="منتج" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.availability} onChange={(e) => setFilters((f) => ({ ...f, availability: e.target.value }))} placeholder="التوفر" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.price_min} onChange={(e) => setFilters((f) => ({ ...f, price_min: e.target.value }))} placeholder="سعر من" className="rounded-md border px-3 py-2 text-sm" />
        <input value={filters.price_max} onChange={(e) => setFilters((f) => ({ ...f, price_max: e.target.value }))} placeholder="سعر إلى" className="rounded-md border px-3 py-2 text-sm" />
        <button type="submit" className="min-h-11 rounded-xl border px-4 text-sm sm:col-span-3 lg:col-span-4">تطبيق الفلاتر</button>
      </form>

      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل الموردين..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && items.length === 0 ? <DashboardEmptyState title="لا يوجد موردون" description="جرّب فلاتر أخرى أو أنشئ مورداً." /> : null}

      <ul className="space-y-2">
        {items.map((supplier) => (
          <li key={supplier.id} className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-white p-4">
            <div>
              <Link to={`/owner/suppliers/${supplier.id}`} className="font-medium underline">{supplier.display_name || supplier.name}</Link>
              <p className="text-sm text-slate-600">{supplier.status} · {supplier.verification_status} · {supplier.visibility ?? 'PRIVATE'} · {supplier.city ?? supplier.location}</p>
            </div>
            <div className="flex flex-wrap gap-2 text-sm">
              <button type="button" className="rounded-lg border px-3 py-1.5" onClick={() => void activateAdminSupplier(supplier.id).then(() => reload()).catch((c) => setError(describeApiError(c, 'تعذر التفعيل.')))}>تفعيل</button>
              <button type="button" className="rounded-lg border px-3 py-1.5" onClick={() => void deactivateAdminSupplier(supplier.id).then(() => reload()).catch((c) => setError(describeApiError(c, 'تعذر التعطيل.')))}>تعطيل</button>
              <button type="button" className="rounded-lg border px-3 py-1.5" onClick={() => void publishAdminSupplier(supplier.id).then(() => { toast.success('نُشر'); return reload() }).catch((c) => setError(describeApiError(c, 'تعذر النشر.')))}>نشر</button>
              <button type="button" className="rounded-lg border px-3 py-1.5" onClick={() => void unpublishAdminSupplier(supplier.id).then(() => reload()).catch((c) => setError(describeApiError(c, 'تعذر إلغاء النشر.')))}>إخفاء</button>
              <button type="button" className="rounded-lg border border-red-200 px-3 py-1.5 text-red-700" onClick={() => void deleteAdminSupplier(supplier.id).then(() => reload()).catch((c) => setError(describeApiError(c, 'تعذر الحذف.')))}>حذف</button>
            </div>
          </li>
        ))}
      </ul>
    </DashboardSection>
  )
}
