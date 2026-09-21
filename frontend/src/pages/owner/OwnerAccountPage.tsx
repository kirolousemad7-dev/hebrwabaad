import { FormEvent, useEffect, useState } from 'react'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import { changePassword, updateAccount } from '../../services/auth'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function OwnerAccountPage() {
  const { user, refreshUser } = useAuth()
  const toast = useToast()

  const [email, setEmail] = useState(user?.email ?? '')
  const [name, setName] = useState(user?.name ?? '')
  const [accountError, setAccountError] = useState<string | null>(null)
  const [accountNotice, setAccountNotice] = useState<string | null>(null)
  const [savingAccount, setSavingAccount] = useState(false)

  const [currentPassword, setCurrentPassword] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [passwordError, setPasswordError] = useState<string | null>(null)
  const [passwordNotice, setPasswordNotice] = useState<string | null>(null)
  const [savingPassword, setSavingPassword] = useState(false)

  useEffect(() => {
    setEmail(user?.email ?? '')
    setName(user?.name ?? '')
  }, [user?.email, user?.name])

  async function onSaveAccount(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (savingAccount) {
      return
    }

    setAccountError(null)
    setAccountNotice(null)
    setSavingAccount(true)

    try {
      await updateAccount({
        email: email.trim(),
        name: name.trim() || undefined,
      })
      await refreshUser()
      setAccountNotice('تم تحديث بيانات الحساب.')
      toast.success('تم تحديث بيانات الحساب.')
    } catch (caught) {
      setAccountError(describeApiError(caught, 'تعذر تحديث الحساب.'))
    } finally {
      setSavingAccount(false)
    }
  }

  async function onChangePassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (savingPassword) {
      return
    }

    setPasswordError(null)
    setPasswordNotice(null)
    setSavingPassword(true)

    try {
      await changePassword({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      })
      setCurrentPassword('')
      setPassword('')
      setPasswordConfirmation('')
      setPasswordNotice('تم تغيير كلمة المرور بنجاح.')
      toast.success('تم تغيير كلمة المرور بنجاح.')
    } catch (caught) {
      setPasswordError(describeApiError(caught, 'تعذر تغيير كلمة المرور.'))
    } finally {
      setSavingPassword(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold text-slate-900">حسابي</h1>
        <p className="text-sm text-slate-600">
          تحديث البريد وكلمة المرور لحساب المالك الحالي دون تغيير الدور أو المعرّف.
        </p>
      </header>

      <article className="rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm">
        <dl className="grid gap-3 sm:grid-cols-3">
          <div>
            <dt className="text-slate-500">المعرّف</dt>
            <dd className="font-medium" dir="ltr">
              {user?.id ?? '—'}
            </dd>
          </div>
          <div>
            <dt className="text-slate-500">الدور</dt>
            <dd className="font-medium">مالك</dd>
          </div>
          <div>
            <dt className="text-slate-500">البريد الحالي</dt>
            <dd className="font-medium" dir="ltr">
              {user?.email ?? '—'}
            </dd>
          </div>
        </dl>
      </article>

      <form onSubmit={(event) => void onSaveAccount(event)} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">بيانات الحساب</h2>
        {accountNotice ? <FeedbackBanner kind="success">{accountNotice}</FeedbackBanner> : null}
        {accountError ? <FeedbackBanner kind="error">{accountError}</FeedbackBanner> : null}
        <label className="block text-sm">
          الاسم
          <input
            value={name}
            onChange={(event) => setName(event.target.value)}
            className={fieldClass}
            autoComplete="name"
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
            autoComplete="email"
          />
        </label>
        <button
          type="submit"
          disabled={savingAccount}
          className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {savingAccount ? 'جاري الحفظ...' : 'حفظ البيانات'}
        </button>
      </form>

      <form
        onSubmit={(event) => void onChangePassword(event)}
        className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <h2 className="text-lg font-semibold text-slate-900">تغيير كلمة المرور</h2>
        {passwordNotice ? <FeedbackBanner kind="success">{passwordNotice}</FeedbackBanner> : null}
        {passwordError ? <FeedbackBanner kind="error">{passwordError}</FeedbackBanner> : null}
        <label className="block text-sm">
          كلمة المرور الحالية
          <input
            required
            type="password"
            value={currentPassword}
            onChange={(event) => setCurrentPassword(event.target.value)}
            className={fieldClass}
            autoComplete="current-password"
          />
        </label>
        <label className="block text-sm">
          كلمة المرور الجديدة
          <input
            required
            type="password"
            minLength={8}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className={fieldClass}
            autoComplete="new-password"
          />
        </label>
        <label className="block text-sm">
          تأكيد كلمة المرور الجديدة
          <input
            required
            type="password"
            minLength={8}
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            className={fieldClass}
            autoComplete="new-password"
          />
        </label>
        <button
          type="submit"
          disabled={savingPassword}
          className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {savingPassword ? 'جاري التحديث...' : 'تحديث كلمة المرور'}
        </button>
      </form>
    </section>
  )
}
