import { FormEvent, useState } from 'react'
import { DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  createSupplierTag,
  deleteSupplierTag,
  getSupplierTags,
  updateSupplierTag,
  type SupplierTagRow,
} from '../../services/supplierWorkspace'
import { describeApiError } from '../../utils/errors'

const SCOPES = ['shared', 'supplier', 'service', 'product', 'portfolio', 'project', 'task']

export function OwnerTagsPage() {
  const [scope, setScope] = useState('')
  const { state, reload } = useAsyncData(() => getSupplierTags(scope || undefined), [scope])
  const toast = useToast()
  const [error, setError] = useState<string | null>(null)
  const [editing, setEditing] = useState<SupplierTagRow | null>(null)

  if (state.status === 'loading') return <DashboardPanelSkeleton label="جاري تحميل الوسوم..." />
  if (state.status === 'error') return <DashboardErrorState message={state.message} onRetry={() => void reload()} />

  const items = state.data.items ?? []
  const scopes = state.data.scopes ?? SCOPES

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const payload = {
      name: String(form.get('name') || ''),
      slug: String(form.get('slug') || '') || undefined,
      scope: String(form.get('scope') || 'shared'),
      color: String(form.get('color') || '') || null,
      is_active: form.get('is_active') === '1',
    }
    setError(null)
    try {
      if (editing) {
        await updateSupplierTag(editing.id, payload)
        toast.success('تم تحديث الوسم.')
      } else {
        await createSupplierTag(payload)
        toast.success('تم إنشاء الوسم.')
      }
      setEditing(null)
      event.currentTarget.reset()
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الوسم.'))
    }
  }

  return (
    <DashboardSection title="الوسوم" description="وسوم قابلة لإعادة الاستخدام عبر الموردين والخدمات والمنتجات والمعرض والمشاريع والمهام.">
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      <div className="flex flex-wrap gap-2">
        <button type="button" className={`rounded-full border px-3 py-1.5 text-xs ${scope === '' ? 'bg-slate-900 text-white' : 'bg-white'}`} onClick={() => setScope('')}>الكل</button>
        {scopes.map((value) => (
          <button key={value} type="button" className={`rounded-full border px-3 py-1.5 text-xs ${scope === value ? 'bg-slate-900 text-white' : 'bg-white'}`} onClick={() => setScope(value)}>{value}</button>
        ))}
      </div>

      <form onSubmit={(event) => void save(event)} className="grid gap-2 rounded-2xl border bg-white p-4 sm:grid-cols-2">
        <input name="name" required defaultValue={editing?.name ?? ''} placeholder="الاسم *" className="rounded-md border px-3 py-2 text-sm" />
        <input name="slug" defaultValue={editing?.slug ?? ''} placeholder="المسار" className="rounded-md border px-3 py-2 text-sm" />
        <select name="scope" defaultValue={editing?.scope ?? 'shared'} className="rounded-md border px-3 py-2 text-sm">
          {SCOPES.map((value) => <option key={value} value={value}>{value}</option>)}
        </select>
        <input name="color" defaultValue={editing?.color ?? ''} placeholder="لون (#hex)" className="rounded-md border px-3 py-2 text-sm" />
        <label className="flex items-center gap-2 text-sm sm:col-span-2"><input name="is_active" type="checkbox" value="1" defaultChecked={editing?.is_active !== false} /> نشط</label>
        <div className="flex gap-2 sm:col-span-2">
          <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white">{editing ? 'تحديث' : 'إنشاء'}</button>
          {editing ? <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setEditing(null)}>إلغاء</button> : null}
        </div>
      </form>

      <ul className="divide-y rounded-2xl border bg-white">
        {items.map((item) => (
          <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
            <div className="flex items-center gap-2">
              <span className="h-3 w-3 rounded-full border" style={{ background: item.color || '#e2e8f0' }} />
              <div>
                <p className="font-medium">{item.name}</p>
                <p className="text-slate-500">{item.slug} · {item.scope}</p>
              </div>
            </div>
            <div className="flex gap-2">
              <button type="button" className="rounded-lg border px-3 py-1.5" onClick={() => setEditing(item)}>تعديل</button>
              <button
                type="button"
                className="rounded-lg border border-red-200 px-3 py-1.5 text-red-700"
                onClick={() => void deleteSupplierTag(item.id).then(() => reload()).catch((caught) => setError(describeApiError(caught, 'تعذر الحذف.')))}
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
