import { FormEvent, useState } from 'react'

type ConsultantLeadFormProps = {
  busy: boolean
  captured: boolean
  defaultBusiness?: string | null
  onSubmit: (payload: {
    name: string
    email: string
    phone?: string
    business_name?: string
    contact_method: 'email' | 'phone' | 'whatsapp'
  }) => void
}

export function ConsultantLeadForm({ busy, captured, defaultBusiness, onSubmit }: ConsultantLeadFormProps) {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [businessName, setBusinessName] = useState(defaultBusiness ?? '')
  const [contactMethod, setContactMethod] = useState<'email' | 'phone' | 'whatsapp'>('email')

  if (captured) {
    return (
      <p className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        استلمنا بياناتك. سيتواصل معك مختص من حبر عبر وسيلة التواصل التي اخترتها.
      </p>
    )
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    onSubmit({
      name: name.trim(),
      email: email.trim(),
      phone: phone.trim() || undefined,
      business_name: businessName.trim() || undefined,
      contact_method: contactMethod,
    })
  }

  return (
    <form
      className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
      onSubmit={handleSubmit}
      aria-labelledby="consultant-lead-title"
    >
      <h3 id="consultant-lead-title" className="font-semibold">
        تواصل مع مختص
      </h3>
      <p className="text-sm text-slate-600">نطلب هذه البيانات بعد التوصية فقط لترتيب التواصل.</p>
      <label className="block space-y-1 text-sm">
        <span>الاسم</span>
        <input
          required
          value={name}
          onChange={(event) => setName(event.target.value)}
          className="w-full rounded-lg border border-slate-300 px-3 py-2.5"
        />
      </label>
      <label className="block space-y-1 text-sm">
        <span>البريد</span>
        <input
          required
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          className="w-full rounded-lg border border-slate-300 px-3 py-2.5"
          dir="ltr"
        />
      </label>
      <label className="block space-y-1 text-sm">
        <span>الهاتف (اختياري)</span>
        <input
          value={phone}
          onChange={(event) => setPhone(event.target.value)}
          className="w-full rounded-lg border border-slate-300 px-3 py-2.5"
          dir="ltr"
        />
      </label>
      <label className="block space-y-1 text-sm">
        <span>اسم النشاط (اختياري)</span>
        <input
          value={businessName}
          onChange={(event) => setBusinessName(event.target.value)}
          className="w-full rounded-lg border border-slate-300 px-3 py-2.5"
        />
      </label>
      <fieldset className="space-y-2 text-sm">
        <legend>وسيلة التواصل المفضلة</legend>
        <div className="flex flex-wrap gap-3">
          {(
            [
              ['email', 'البريد'],
              ['phone', 'الهاتف'],
              ['whatsapp', 'واتساب'],
            ] as const
          ).map(([value, label]) => (
            <label key={value} className="inline-flex items-center gap-2">
              <input
                type="radio"
                name="contact_method"
                checked={contactMethod === value}
                onChange={() => setContactMethod(value)}
              />
              {label}
            </label>
          ))}
        </div>
      </fieldset>
      <button
        type="submit"
        disabled={busy}
        className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
      >
        إرسال
      </button>
    </form>
  )
}
