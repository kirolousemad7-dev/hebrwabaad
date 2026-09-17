import { FormEvent, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { createAdminSupplier } from '../../services/supplierWorkspace'
import { describeApiError } from '../../utils/errors'

export function OwnerSupplierNewPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  async function submit(event: FormEvent<HTMLFormElement>, asDraft: boolean) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setSaving(true)
    setError(null)
    try {
      const response = await createAdminSupplier({
        name: String(form.get('name') || ''),
        legal_name: String(form.get('legal_name') || '') || undefined,
        display_name: String(form.get('display_name') || '') || undefined,
        short_description: String(form.get('short_description') || ''),
        description: String(form.get('description') || '') || undefined,
        location: String(form.get('location') || ''),
        city: String(form.get('city') || '') || undefined,
        country: String(form.get('country') || '') || undefined,
        email: String(form.get('email') || '') || undefined,
        phone: String(form.get('phone') || '') || undefined,
        whatsapp: String(form.get('whatsapp') || '') || undefined,
        website: String(form.get('website') || '') || undefined,
        category: String(form.get('category') || '') || undefined,
        notes: String(form.get('notes') || '') || undefined,
        internal_notes: String(form.get('internal_notes') || '') || undefined,
        account_email: String(form.get('account_email') || '') || undefined,
        account_password: String(form.get('account_password') || '') || undefined,
        account_name: String(form.get('display_name') || form.get('name') || '') || undefined,
        save_as_draft: asDraft,
      })
      toast.success(asDraft ? 'تم حفظ المورد كمسودة.' : 'تم إنشاء المورد.')
      navigate(`/owner/suppliers/${response.data.id}`)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إنشاء المورد.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <DashboardSection
      title="مورد جديد"
      description="إنشاء مورد داخلي. بيانات الاتصال والملاحظات الداخلية لا تظهر للعملاء تلقائياً."
      action={<Link to="/owner/suppliers" className="min-h-11 rounded-xl border px-4 text-sm leading-11">رجوع</Link>}
    >
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      <form className="grid gap-4 rounded-2xl border bg-white p-4 md:grid-cols-2" onSubmit={(event) => void submit(event, true)}>
        <fieldset className="grid gap-2 md:col-span-2">
          <legend className="mb-1 text-sm font-semibold">المعلومات الأساسية</legend>
          <input required name="name" placeholder="الاسم الظاهر في النظام *" className="rounded-md border px-3 py-2 text-sm" />
          <input name="display_name" placeholder="اسم العرض" className="rounded-md border px-3 py-2 text-sm" />
          <input name="legal_name" placeholder="الاسم القانوني" className="rounded-md border px-3 py-2 text-sm" />
          <input required name="short_description" placeholder="وصف قصير *" className="rounded-md border px-3 py-2 text-sm" />
          <textarea name="description" placeholder="الوصف" className="min-h-24 rounded-md border px-3 py-2 text-sm" />
        </fieldset>

        <fieldset className="grid gap-2">
          <legend className="mb-1 text-sm font-semibold">الموقع والتواصل</legend>
          <input required name="location" placeholder="الموقع *" className="rounded-md border px-3 py-2 text-sm" />
          <input name="city" placeholder="المدينة" className="rounded-md border px-3 py-2 text-sm" />
          <input name="country" placeholder="الدولة" className="rounded-md border px-3 py-2 text-sm" />
          <input name="email" type="email" placeholder="البريد" className="rounded-md border px-3 py-2 text-sm" />
          <input name="phone" placeholder="الهاتف" className="rounded-md border px-3 py-2 text-sm" />
          <input name="whatsapp" placeholder="واتساب" className="rounded-md border px-3 py-2 text-sm" />
          <input name="website" placeholder="الموقع الإلكتروني" className="rounded-md border px-3 py-2 text-sm" />
        </fieldset>

        <fieldset className="grid gap-2">
          <legend className="mb-1 text-sm font-semibold">التصنيف والحساب</legend>
          <input name="category" placeholder="تصنيف نصي (اختياري)" className="rounded-md border px-3 py-2 text-sm" />
          <input name="account_email" type="email" placeholder="بريد حساب المورد (اختياري)" className="rounded-md border px-3 py-2 text-sm" />
          <input name="account_password" type="password" placeholder="كلمة مرور الحساب" className="rounded-md border px-3 py-2 text-sm" />
          <textarea name="notes" placeholder="ملاحظات عامة" className="min-h-20 rounded-md border px-3 py-2 text-sm" />
          <textarea name="internal_notes" placeholder="ملاحظات داخلية (لا تظهر للعامة)" className="min-h-20 rounded-md border px-3 py-2 text-sm" />
        </fieldset>

        <div className="flex flex-wrap gap-2 md:col-span-2">
          <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">حفظ كمسودة</button>
          <button type="button" disabled={saving} className="min-h-11 rounded-xl border px-4 text-sm" onClick={(event) => {
            const form = (event.currentTarget as HTMLButtonElement).form
            if (form) void submit({ preventDefault() {}, currentTarget: form } as FormEvent<HTMLFormElement>, false)
          }}>إنشاء ومتابعة</button>
        </div>
      </form>
    </DashboardSection>
  )
}
