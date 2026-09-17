import { FormEvent, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { BrandLogo } from '../../components/brand/BrandLogo'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAuth } from '../../context/AuthContext'
import { persistSession } from '../../services/auth'
import { registerSupplier } from '../../services/supplierPortal'
import { describeApiError } from '../../utils/errors'

export function SupplierRegisterPage() {
  const navigate = useNavigate()
  const { refreshUser } = useAuth()
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [passwordless, setPasswordless] = useState(false)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    const form = new FormData(event.currentTarget)
    const servicesRaw = String(form.get('services') || '')
    try {
      const response = await registerSupplier({
        company_name: String(form.get('company_name') || ''),
        contact_person: String(form.get('contact_person') || ''),
        email: String(form.get('email') || ''),
        phone: String(form.get('phone') || '') || undefined,
        whatsapp: String(form.get('whatsapp') || '') || undefined,
        country: String(form.get('country') || '') || undefined,
        city: String(form.get('city') || '') || undefined,
        category: String(form.get('category') || '') || undefined,
        short_description: String(form.get('short_description') || ''),
        services: servicesRaw ? servicesRaw.split(',').map((s) => s.trim()).filter(Boolean) : [],
        passwordless,
        password: passwordless ? undefined : String(form.get('password') || ''),
        password_confirmation: passwordless ? undefined : String(form.get('password_confirmation') || ''),
      })
      if (response.data.token) {
        persistSession({ user: response.data.user, token: response.data.token })
        await refreshUser()
        navigate('/supplier', { replace: true })
      } else {
        navigate('/supplier/login', { replace: true, state: { registered: true } })
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تسجيل المورد.'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="mx-auto max-w-xl space-y-6">
      <div className="flex justify-center"><BrandLogo size="auth" to="/" /></div>
      <h1 className="text-center text-2xl font-semibold">تسجيل مورد جديد</h1>
      <p className="text-center text-sm text-slate-600">بعد التسجيل يكون الحساب بانتظار موافقة الإدارة (PENDING).</p>
      <form onSubmit={(event) => void submit(event)} className="grid gap-3 rounded-2xl border bg-white p-6 shadow-sm">
        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
        <input required name="company_name" placeholder="اسم الشركة *" className="rounded-lg border px-3 py-2 text-sm" />
        <input required name="contact_person" placeholder="اسم جهة الاتصال *" className="rounded-lg border px-3 py-2 text-sm" />
        <input required name="email" type="email" placeholder="البريد *" className="rounded-lg border px-3 py-2 text-sm" />
        <input name="phone" placeholder="الهاتف" className="rounded-lg border px-3 py-2 text-sm" />
        <input name="whatsapp" placeholder="واتساب" className="rounded-lg border px-3 py-2 text-sm" />
        <input name="country" placeholder="الدولة" className="rounded-lg border px-3 py-2 text-sm" />
        <input name="city" placeholder="المدينة" className="rounded-lg border px-3 py-2 text-sm" />
        <input name="category" placeholder="التصنيف" className="rounded-lg border px-3 py-2 text-sm" />
        <input name="services" placeholder="الخدمات (مفصولة بفاصلة)" className="rounded-lg border px-3 py-2 text-sm" />
        <textarea required name="short_description" placeholder="وصف قصير *" className="min-h-24 rounded-lg border px-3 py-2 text-sm" />
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={passwordless} onChange={(e) => setPasswordless(e.target.checked)} />
          تسجيل بدون كلمة مرور (دخول لاحق برمز لمرة واحدة)
        </label>
        {!passwordless ? (
          <>
            <input required name="password" type="password" placeholder="كلمة المرور *" className="rounded-lg border px-3 py-2 text-sm" />
            <input required name="password_confirmation" type="password" placeholder="تأكيد كلمة المرور *" className="rounded-lg border px-3 py-2 text-sm" />
          </>
        ) : null}
        <button type="submit" disabled={loading} className="min-h-11 rounded-xl bg-slate-900 text-sm text-white disabled:opacity-60">إرسال الطلب</button>
        <p className="text-center text-sm">لديك حساب؟ <Link className="underline" to="/supplier/login">تسجيل دخول المورد</Link></p>
      </form>
    </section>
  )
}
