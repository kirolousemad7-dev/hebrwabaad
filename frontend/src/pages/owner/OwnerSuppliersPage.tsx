import { FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  activateAdminSupplier,
  createAdminSupplier,
  deactivateAdminSupplier,
  deleteAdminSupplier,
  getAdminSuppliers,
  publishAdminSupplier,
  unpublishAdminSupplier,
} from '../../services/supplierWorkspace'
import { describeApiError } from '../../utils/errors'

export function OwnerSuppliersPage() {
  const { state, reload } = useAsyncData(() => getAdminSuppliers())
  const toast = useToast()
  const [error, setError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)

  const items = state.status === 'ready' ? state.data.items : []

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await createAdminSupplier({
        name: String(form.get('name')),
        short_description: String(form.get('short_description')),
        location: String(form.get('location')),
        account_email: String(form.get('account_email') || '') || undefined,
        account_password: String(form.get('account_password') || '') || undefined,
        account_name: String(form.get('name')),
      })
      setCreating(false)
      toast.success('تم إنشاء المورد كمسودة.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إنشاء المورد.'))
    }
  }

  return (
    <DashboardSection
      title="الموردين"
      description="إدارة الموردين والنشر. لا يظهر للعامة إلا النشط المنشور المعتمد."
      action={<button type="button" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white" onClick={() => setCreating(true)}>مورد جديد</button>}
    >
      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل الموردين..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {creating ? (
        <form onSubmit={(event) => void create(event)} className="grid gap-3 rounded-2xl border bg-white p-4">
          <input required name="name" placeholder="اسم المورد" className="rounded-md border px-3 py-2 text-sm" />
          <input required name="short_description" placeholder="وصف قصير" className="rounded-md border px-3 py-2 text-sm" />
          <input required name="location" placeholder="الموقع" className="rounded-md border px-3 py-2 text-sm" />
          <input name="account_email" type="email" placeholder="بريد حساب المورد (اختياري)" className="rounded-md border px-3 py-2 text-sm" />
          <input name="account_password" type="password" placeholder="كلمة مرور الحساب" className="rounded-md border px-3 py-2 text-sm" />
          <div className="flex gap-2">
            <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white">إنشاء</button>
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setCreating(false)}>إلغاء</button>
          </div>
        </form>
      ) : null}

      {items.length === 0 && state.status === 'ready' ? (
        <DashboardEmptyState title="لا يوجد موردون." description="أنشئ مورداً ثم اعتمد ملفه قبل ظهوره للعامة." />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-right">
              <tr>
                <th className="px-3 py-2">الاسم</th>
                <th className="px-3 py-2">التصنيف</th>
                <th className="px-3 py-2">الحالة</th>
                <th className="px-3 py-2">منشور</th>
                <th className="px-3 py-2">مميز</th>
                <th className="px-3 py-2">المحتوى</th>
                <th className="px-3 py-2">آخر تحديث</th>
                <th className="px-3 py-2">إجراءات</th>
              </tr>
            </thead>
            <tbody>
              {items.map((supplier) => (
                <tr key={supplier.id} className="border-t">
                  <td className="px-3 py-2 font-medium">{supplier.name}</td>
                  <td className="px-3 py-2">{supplier.category ?? '—'}</td>
                  <td className="px-3 py-2">{supplier.profile_status}{supplier.is_active ? '' : ' · متوقف'}</td>
                  <td className="px-3 py-2">{supplier.is_published ? 'نعم' : 'لا'}</td>
                  <td className="px-3 py-2">{supplier.is_featured ? 'نعم' : 'لا'}</td>
                  <td className="px-3 py-2">{supplier.content_count} / {supplier.products_count}</td>
                  <td className="px-3 py-2">{supplier.updated_at ? new Date(supplier.updated_at).toLocaleDateString('ar-SA') : '—'}</td>
                  <td className="px-3 py-2">
                    <div className="flex flex-wrap gap-1">
                      <Link className="rounded-lg border px-2 py-1" to={`/owner/suppliers/${supplier.id}`}>إدارة</Link>
                      <button type="button" className="rounded-lg border px-2 py-1" onClick={() => void publishAdminSupplier(supplier.id).then(() => reload())}>نشر</button>
                      <button type="button" className="rounded-lg border px-2 py-1" onClick={() => void unpublishAdminSupplier(supplier.id).then(() => reload())}>إلغاء النشر</button>
                      {supplier.is_active ? (
                        <button type="button" className="rounded-lg border px-2 py-1" onClick={() => void deactivateAdminSupplier(supplier.id).then(() => reload())}>إيقاف</button>
                      ) : (
                        <button type="button" className="rounded-lg border px-2 py-1" onClick={() => void activateAdminSupplier(supplier.id).then(() => reload())}>تفعيل</button>
                      )}
                      <button type="button" className="rounded-lg border border-red-300 px-2 py-1 text-red-800" onClick={() => {
                        if (window.confirm(`حذف ${supplier.name}؟ لا يمكن التراجع.`)) void deleteAdminSupplier(supplier.id).then(() => reload())
                      }}>حذف</button>
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
