import { FormEvent, useState } from 'react'
import { useParams } from 'react-router-dom'
import { DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getAdminSupplier, publishAdminSupplier, updateAdminSupplier } from '../../services/supplierWorkspace'
import { describeApiError } from '../../utils/errors'

const TABS = ['نظرة عامة', 'الملف', 'الهوية', 'المعرض', 'المنتجات', 'SEO', 'النشر', 'السجل'] as const

export function OwnerSupplierDetailPage() {
  const { id } = useParams()
  const supplierId = Number(id)
  const { state, reload } = useAsyncData(() => getAdminSupplier(supplierId))
  const toast = useToast()
  const [tab, setTab] = useState<(typeof TABS)[number]>('نظرة عامة')
  const [error, setError] = useState<string | null>(null)

  if (state.status === 'loading') return <DashboardPanelSkeleton label="جاري تحميل المورد..." />
  if (state.status === 'error') return <DashboardErrorState message={state.message} onRetry={() => void reload()} />

  const { supplier, portfolio, products, reviews } = state.data

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const payload: Record<string, unknown> = {}
    for (const [key, value] of form.entries()) {
      if (key === 'is_featured') {
        payload.is_featured = value === '1'
        continue
      }
      payload[key] = String(value)
    }
    if (tab === 'النشر' && !('is_featured' in payload)) {
      payload.is_featured = false
    }
    try {
      await updateAdminSupplier(supplierId, payload)
      toast.success('تم حفظ المورد.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر الحفظ.'))
    }
  }

  return (
    <DashboardSection title={supplier.name} description={`/${supplier.slug} · ${supplier.profile_status}`}>
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      <div className="flex flex-wrap gap-2">
        {TABS.map((item) => (
          <button key={item} type="button" onClick={() => setTab(item)} className={`min-h-10 rounded-full px-4 text-sm ${tab === item ? 'bg-slate-900 text-white' : 'border bg-white'}`}>{item}</button>
        ))}
      </div>

      {tab === 'نظرة عامة' ? (
        <ul className="grid gap-3 sm:grid-cols-2">
          <li className="rounded-2xl border bg-white p-4">الحالة: {supplier.profile_status}</li>
          <li className="rounded-2xl border bg-white p-4">منشور: {supplier.is_published ? 'نعم' : 'لا'}</li>
          <li className="rounded-2xl border bg-white p-4">نشط: {supplier.is_active ? 'نعم' : 'لا'}</li>
          <li className="rounded-2xl border bg-white p-4">مميز: {supplier.is_featured ? 'نعم' : 'لا'}</li>
        </ul>
      ) : null}

      {tab === 'الملف' || tab === 'الهوية' || tab === 'SEO' || tab === 'النشر' ? (
        <form onSubmit={(event) => void save(event)} className="grid gap-3 rounded-2xl border bg-white p-4">
          {tab === 'الملف' ? (
            <>
              <input name="name" defaultValue={supplier.name} className="rounded-md border px-3 py-2" />
              <input name="short_description" defaultValue={supplier.short_description} className="rounded-md border px-3 py-2" />
              <textarea name="description" defaultValue={supplier.description ?? ''} className="min-h-28 rounded-md border px-3 py-2" />
              <input name="location" defaultValue={supplier.location} className="rounded-md border px-3 py-2" />
              <input name="phone" defaultValue={supplier.phone ?? ''} className="rounded-md border px-3 py-2" />
              <input name="email" defaultValue={supplier.email ?? ''} className="rounded-md border px-3 py-2" />
            </>
          ) : null}
          {tab === 'الهوية' ? (
            <>
              <input name="logo" defaultValue={supplier.logo} className="rounded-md border px-3 py-2" />
              <input name="cover_image" defaultValue={supplier.cover_image ?? ''} className="rounded-md border px-3 py-2" />
              <textarea name="brand_description" defaultValue={supplier.brand_description ?? ''} className="min-h-24 rounded-md border px-3 py-2" />
            </>
          ) : null}
          {tab === 'SEO' ? (
            <>
              <input name="seo_title" defaultValue={supplier.seo_title ?? ''} className="rounded-md border px-3 py-2" />
              <textarea name="seo_description" defaultValue={supplier.seo_description ?? ''} className="min-h-20 rounded-md border px-3 py-2" />
              <input name="og_image" defaultValue={supplier.og_image ?? ''} className="rounded-md border px-3 py-2" />
              <input name="robots" defaultValue={supplier.robots ?? 'index,follow'} className="rounded-md border px-3 py-2" />
            </>
          ) : null}
          {tab === 'النشر' ? (
            <>
              <label className="text-sm"><input type="checkbox" name="is_featured" defaultChecked={supplier.is_featured} value="1" /> مميز</label>
              <button type="button" className="min-h-11 max-w-xs rounded-xl bg-emerald-700 px-4 text-sm text-white" onClick={() => void publishAdminSupplier(supplierId).then(() => { toast.success('تمت الموافقة والنشر.'); return reload() })}>موافقة ونشر</button>
            </>
          ) : null}
          <button type="submit" className="min-h-11 max-w-xs rounded-xl bg-slate-900 px-4 text-sm text-white">حفظ</button>
        </form>
      ) : null}

      {tab === 'المعرض' ? (
        <ul className="space-y-2">{portfolio.map((item) => <li key={String(item.id)} className="rounded-xl border bg-white p-3">{String(item.title)} · {String(item.status)}</li>)}</ul>
      ) : null}
      {tab === 'المنتجات' ? (
        <ul className="space-y-2">{products.map((item) => <li key={String(item.id)} className="rounded-xl border bg-white p-3">{String(item.name)} · {String(item.status)}</li>)}</ul>
      ) : null}
      {tab === 'السجل' ? (
        <ul className="space-y-2">{reviews.map((item) => <li key={String(item.id)} className="rounded-xl border bg-white p-3">{String(item.action)} · {String(item.actor ?? '')} · {String(item.notes ?? '')}</li>)}</ul>
      ) : null}
    </DashboardSection>
  )
}
