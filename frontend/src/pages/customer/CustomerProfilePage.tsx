import { Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { customerInitials } from '../../utils/customerDashboard'

export function CustomerProfilePage() {
  const { user } = useAuth()

  return (
    <section className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold">حسابي</h1>
        <p className="text-sm text-slate-600">بيانات حسابك من نظام المستخدم الحالي. لا يمكن تعديل الدور أو صلاحيات الدخول من هنا.</p>
      </header>

      <article className="flex min-w-0 flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
        <span className="inline-flex h-14 w-14 items-center justify-center rounded-full bg-slate-900 text-lg font-medium text-white">
          {customerInitials(user?.name)}
        </span>
        <dl className="grid min-w-0 flex-1 gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-slate-500">الاسم</dt>
            <dd className="font-medium">{user?.name}</dd>
          </div>
          <div>
            <dt className="text-slate-500">البريد</dt>
            <dd dir="ltr">{user?.email}</dd>
          </div>
          <div>
            <dt className="text-slate-500">نوع الحساب</dt>
            <dd>عميل</dd>
          </div>
        </dl>
      </article>

      <p className="text-xs text-slate-500">
        الهاتف واسم الشركة والصورة الشخصية غير موجودة في حساب المستخدم الحالي، لذلك لا تُعرض هنا.
      </p>

      <Link to="/dashboard" className="inline-block text-sm underline">
        العودة إلى لوحة التحكم
      </Link>
    </section>
  )
}
