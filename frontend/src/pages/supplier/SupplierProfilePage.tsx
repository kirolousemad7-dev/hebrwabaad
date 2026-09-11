import { FormEvent, useState } from 'react'
import { DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getSupplierProfile, submitSupplierProfile, updateSupplierProfile } from '../../services/supplierWorkspace'
import { describeApiError } from '../../utils/errors'

export function SupplierProfilePage() {
  const { state, reload } = useAsyncData(getSupplierProfile)
  const toast = useToast()
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (state.status === 'loading') return <DashboardPanelSkeleton label="جاري تحميل الملف..." />
  if (state.status === 'error') return <DashboardErrorState message={state.message} onRetry={() => void reload()} />

  const profile = state.data

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setSaving(true)
    setError(null)
    try {
      await updateSupplierProfile({
        name: String(form.get('name') ?? ''),
        short_description: String(form.get('short_description') ?? ''),
        description: String(form.get('description') ?? ''),
        location: String(form.get('location') ?? ''),
        phone: String(form.get('phone') ?? '') || null,
        email: String(form.get('email') ?? '') || null,
        website: String(form.get('website') ?? '') || null,
        seo_title: String(form.get('seo_title') ?? '') || null,
        seo_description: String(form.get('seo_description') ?? '') || null,
      })
      toast.success(profile.is_published ? 'حُفظت التعديلات بانتظار مراجعة المالك.' : 'تم حفظ المسودة.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الملف.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <DashboardSection title="الملف الشخصي" description="التعديلات على ملف منشور لا تظهر للعامة حتى يوافق المالك.">
      {profile.pending_profile ? <FeedbackBanner kind="warning">توجد تعديلات معلّقة بحالة {profile.pending_profile.status}.</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      <form onSubmit={(event) => void save(event)} className="grid gap-3 rounded-2xl border bg-white p-4">
        <label className="text-sm">الاسم<input name="name" defaultValue={profile.name} className="mt-1 w-full rounded-md border px-3 py-2" /></label>
        <label className="text-sm">وصف قصير<input name="short_description" defaultValue={profile.short_description} className="mt-1 w-full rounded-md border px-3 py-2" /></label>
        <label className="text-sm">الوصف<textarea name="description" defaultValue={profile.description ?? ''} className="mt-1 min-h-28 w-full rounded-md border px-3 py-2" /></label>
        <label className="text-sm">الموقع<input name="location" defaultValue={profile.location} className="mt-1 w-full rounded-md border px-3 py-2" /></label>
        <label className="text-sm">الهاتف<input name="phone" defaultValue={profile.phone ?? ''} className="mt-1 w-full rounded-md border px-3 py-2" /></label>
        <label className="text-sm">البريد<input name="email" defaultValue={profile.email ?? ''} className="mt-1 w-full rounded-md border px-3 py-2" /></label>
        <label className="text-sm">الموقع الإلكتروني<input name="website" defaultValue={profile.website ?? ''} className="mt-1 w-full rounded-md border px-3 py-2" /></label>
        <label className="text-sm">عنوان SEO<input name="seo_title" defaultValue={profile.seo_title ?? ''} className="mt-1 w-full rounded-md border px-3 py-2" /></label>
        <label className="text-sm">وصف SEO<textarea name="seo_description" defaultValue={profile.seo_description ?? ''} className="mt-1 min-h-20 w-full rounded-md border px-3 py-2" /></label>
        <div className="flex flex-wrap gap-2">
          <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white">{saving ? 'جاري الحفظ...' : 'حفظ'}</button>
          <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => void submitSupplierProfile().then(() => { toast.success('أُرسل للمراجعة.'); return reload() })}>إرسال للمراجعة</button>
        </div>
      </form>
    </DashboardSection>
  )
}
