import { useMemo, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { AddCustomerModal } from '../../components/workspace/AddCustomerModal'
import { useAsyncData } from '../../hooks/useAsyncData'
import { listStaffCustomers, type StaffCustomer } from '../../services/staffCustomers'

const fieldClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

function accountStatusLabel(customer: StaffCustomer): string {
  if (customer.account_status === 'ACTIVE' || customer.has_account) {
    return 'لديه حساب'
  }
  return 'بدون حساب دخول'
}

export function StaffCustomersPage() {
  const [search, setSearch] = useState('')
  const [appliedSearch, setAppliedSearch] = useState('')
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [modalOpen, setModalOpen] = useState(false)

  const query = useMemo(() => {
    const params = new URLSearchParams()
    if (appliedSearch) {
      params.set('q', appliedSearch)
    }
    const suffix = params.toString()
    return suffix ? `?${suffix}` : ''
  }, [appliedSearch])

  const { state, reload } = useAsyncData(() => listStaffCustomers(query), [query])
  const customers = state.status === 'ready' ? state.data : []

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">العملاء</h1>
          <p className="text-sm text-slate-600">
            إدارة العملاء للاستخدام في المشاريع — سواء سجّلوا بأنفسهم أو أُضيفوا من لوحة التحكم.
          </p>
        </div>
        <button
          type="button"
          onClick={() => {
            setError(null)
            setModalOpen(true)
          }}
          className="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 text-sm text-white"
        >
          إضافة عميل
        </button>
      </header>

      {message ? <FeedbackBanner kind="success">{message}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <form
        className="flex flex-wrap items-end gap-3"
        onSubmit={(event) => {
          event.preventDefault()
          setAppliedSearch(search.trim())
        }}
      >
        <label className="block min-w-[16rem] flex-1 text-sm">
          بحث
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            className={fieldClass}
            placeholder="الاسم أو البريد"
          />
        </label>
        <button type="submit" className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
          بحث
        </button>
      </form>

      <DashboardSection title="قائمة العملاء" description="يظهر هؤلاء العملاء مباشرة عند إنشاء مشروع جديد.">
        {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل العملاء..." /> : null}
        {state.status === 'error' ? (
          <DashboardErrorState message={state.message} onRetry={() => void reload()} />
        ) : null}
        {state.status === 'ready' && customers.length === 0 ? (
          <DashboardEmptyState
            title="لا يوجد عملاء"
            description="أضف عميلاً من الزر أعلاه، أو انتظر تسجيل عميل جديد من الموقع."
          />
        ) : null}
        {state.status === 'ready' && customers.length > 0 ? (
          <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            {customers.map((customer) => (
              <li key={customer.id} className="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                <div className="min-w-0 space-y-1">
                  <p className="font-medium text-slate-900">{customer.name}</p>
                  <p className="text-sm text-slate-600" dir="ltr">
                    {customer.email}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2 text-xs">
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-700">
                    {accountStatusLabel(customer)}
                  </span>
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-700">
                    مشاريع: {customer.projects_count}
                  </span>
                  {!customer.is_active ? (
                    <span className="rounded-full bg-red-50 px-3 py-1 text-red-700">معطّل</span>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </DashboardSection>

      <AddCustomerModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onCreated={async () => {
          setMessage('تمت إضافة العميل بنجاح.')
          setError(null)
          await reload()
        }}
      />
    </section>
  )
}
