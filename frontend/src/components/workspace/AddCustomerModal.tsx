import { FormEvent, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { createStaffCustomer, type StaffCustomer } from '../../services/staffCustomers'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type Props = {
  open: boolean
  onClose: () => void
  onCreated: (customer: StaffCustomer) => void | Promise<void>
}

export function AddCustomerModal({ open, onClose, onCreated }: Props) {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  if (!open) {
    return null
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (saving) {
      return
    }

    setError(null)
    setSaving(true)
    try {
      const response = await createStaffCustomer({
        name: name.trim(),
        email: email.trim(),
      })
      setName('')
      setEmail('')
      await onCreated(response.data)
      onClose()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة العميل.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="add-customer-title">
      <form
        onSubmit={(event) => void onSubmit(event)}
        className="w-full max-w-md space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xl"
      >
        <div className="flex items-start justify-between gap-3">
          <h2 id="add-customer-title" className="text-lg font-semibold text-slate-900">
            إضافة عميل
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="text-sm text-slate-600 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            إغلاق
          </button>
        </div>
        <p className="text-sm text-slate-600">
          يُنشأ سجل عميل للاستخدام في المشاريع دون إنشاء جلسة دخول تلقائية. يمكن للعميل لاحقًا تفعيل الدخول عبر إعادة تعيين كلمة المرور.
        </p>
        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
        <label className="block text-sm">
          اسم العميل
          <input
            required
            value={name}
            onChange={(event) => setName(event.target.value)}
            className={fieldClass}
            autoFocus
          />
        </label>
        <label className="block text-sm">
          البريد الإلكتروني
          <input
            required
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            className={fieldClass}
            dir="ltr"
          />
        </label>
        <div className="flex flex-wrap justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm"
            disabled={saving}
          >
            إلغاء
          </button>
          <button
            type="submit"
            disabled={saving}
            className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm text-white disabled:opacity-60"
          >
            {saving ? 'جاري الإضافة...' : 'إضافة العميل'}
          </button>
        </div>
      </form>
    </div>
  )
}
