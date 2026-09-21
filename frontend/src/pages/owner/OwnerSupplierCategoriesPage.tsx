import { FormEvent, useState } from 'react'
import { DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  createSupplierCategory,
  deleteSupplierCategory,
  getSupplierCategories,
  updateSupplierCategory,
  type SupplierCategoryRow,
} from '../../services/supplierWorkspace'
import { describeApiError } from '../../utils/errors'

export function OwnerSupplierCategoriesPage() {
  const { state, reload } = useAsyncData(() => getSupplierCategories())
  const toast = useToast()
  const [error, setError] = useState<string | null>(null)
  const [editing, setEditing] = useState<SupplierCategoryRow | null>(null)

  if (state.status === 'loading') return <DashboardPanelSkeleton label="جاري تحميل التصنيفات..." />
  if (state.status === 'error') return <DashboardErrorState message={state.message} onRetry={() => void reload()} />

  const items = state.data.items ?? []

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const payload = {
      name: String(form.get('name') || ''),
      parent_id: form.get('parent_id') ? Number(form.get('parent_id')) : null,
      description: String(form.get('description') || '') || null,
      icon: String(form.get('icon') || '') || null,
      sort_order: Number(form.get('sort_order') || 0),
      is_active: form.get('is_active') === '1',
      seo_title: String(form.get('seo_title') || '') || null,
      seo_description: String(form.get('seo_description') || '') || null,
      slug: String(form.get('slug') || '') || undefined,
    }
    setError(null)
    try {
      if (editing) {
        await updateSupplierCategory(editing.id, payload)
        toast.success('تم تحديث التصنيف.')
      } else {
        await createSupplierCategory(payload)
        toast.success('تم إنشاء التصنيف.')
      }
      setEditing(null)
      event.currentTarget.reset()
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ التصنيف.'))
    }
  }

  return (
    <DashboardSection title="تصنيفات الموردين" description="تصنيفات رئيسية وفرعية مع أيقونة وترتيب وSEO slug.">
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      <form onSubmit={(event) => void save(event)} className="grid gap-2 rounded-2xl border bg-white p-4 sm:grid-cols-2">
        <input name="name" required defaultValue={editing?.name ?? ''} placeholder="الاسم *" className="rounded-md border px-3 py-2 text-sm" />
        <input name="slug" defaultValue={editing?.slug ?? ''} placeholder="مسار تحسين البحث" className="rounded-md border px-3 py-2 text-sm" />
        <select name="parent_id" defaultValue={editing?.parent_id ?? ''} className="rounded-md border px-3 py-2 text-sm">
          <option value="">بدون أب (رئيسي)</option>
          {items.filter((item) => !item.parent_id && item.id !== editing?.id).map((item) => (
            <option key={item.id} value={item.id}>{item.name}</option>
          ))}
        </select>
        <input name="icon" defaultValue={editing?.icon ?? ''} placeholder="أيقونة / مسار" className="rounded-md border px-3 py-2 text-sm" />
        <input name="sort_order" type="number" defaultValue={editing?.sort_order ?? 0} placeholder="الترتيب" className="rounded-md border px-3 py-2 text-sm" />
        <label className="flex items-center gap-2 text-sm"><input name="is_active" type="checkbox" value="1" defaultChecked={editing?.is_active !== false} /> نشط</label>
        <input name="seo_title" defaultValue={editing?.seo_title ?? ''} placeholder="عنوان SEO" className="rounded-md border px-3 py-2 text-sm sm:col-span-2" />
        <textarea name="description" defaultValue={editing?.description ?? ''} placeholder="الوصف" className="min-h-20 rounded-md border px-3 py-2 text-sm sm:col-span-2" />
        <textarea name="seo_description" defaultValue={editing?.seo_description ?? ''} placeholder="وصف SEO" className="min-h-16 rounded-md border px-3 py-2 text-sm sm:col-span-2" />
        <div className="flex gap-2 sm:col-span-2">
          <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white">{editing ? 'تحديث' : 'إنشاء'}</button>
          {editing ? <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setEditing(null)}>إلغاء</button> : null}
        </div>
      </form>

      <ul className="divide-y rounded-2xl border bg-white">
        {items.map((item) => (
          <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
            <div>
              <p className="font-medium">{item.parent_id ? '↳ ' : ''}{item.name}</p>
              <p className="text-slate-500">{item.slug} · ترتيب {item.sort_order ?? 0} · {item.is_active === false ? 'غير نشط' : 'نشط'}</p>
            </div>
            <div className="flex gap-2">
              <button type="button" className="rounded-lg border px-3 py-1.5" onClick={() => setEditing(item)}>تعديل</button>
              <button
                type="button"
                className="rounded-lg border border-red-200 px-3 py-1.5 text-red-700"
                onClick={() => void deleteSupplierCategory(item.id).then(() => reload()).catch((caught) => setError(describeApiError(caught, 'تعذر الحذف.')))}
              >
                حذف
              </button>
            </div>
          </li>
        ))}
      </ul>
    </DashboardSection>
  )
}
