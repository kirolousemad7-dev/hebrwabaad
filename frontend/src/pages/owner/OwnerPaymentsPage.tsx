import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { getOwnerPayments, type OwnerPaymentListFilters } from '../../services/payments'
import type { OwnerPaymentListData } from '../../types/api'
import { describeApiError } from '../../utils/errors'
import { formatPaymentAmount, ownerPaymentPath, PAYMENT_COPY } from '../../utils/payments'

const STATUS_FILTERS = [
  { value: '', label: 'كل الحالات' },
  { value: 'PAID', label: 'مدفوع' },
  { value: 'PENDING_VERIFICATION', label: 'بانتظار التحقق' },
  { value: 'PROCESSING', label: 'قيد المعالجة' },
  { value: 'FAILED', label: 'فشل' },
  { value: 'REJECTED', label: 'مرفوض' },
  { value: 'PENDING', label: 'بانتظار الدفع' },
  { value: 'CANCELLED', label: 'ملغى' },
]

const METHOD_FILTERS = [
  { value: '', label: 'كل الطرق' },
  { value: 'CARD', label: 'بطاقة' },
  { value: 'INSTAPAY', label: 'إنستاباي' },
  { value: 'BANK_TRANSFER', label: 'تحويل بنكي' },
]

export function OwnerPaymentsPage() {
  const [filters, setFilters] = useState<OwnerPaymentListFilters>({ q: '', status: '', payment_method: '', page: 1 })
  const [list, setList] = useState<OwnerPaymentListData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)

    try {
      const response = await getOwnerPayments(filters)
      setList(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل المدفوعات.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    const handle = window.setTimeout(() => {
      void load()
    }, filters.q ? 250 : 0)

    return () => window.clearTimeout(handle)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.q, filters.status, filters.payment_method, filters.page])

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">المدفوعات</h1>
          <p className="text-sm text-slate-600">
            إدارة المعاملات الحقيقية والتحقق من التحويلات اليدوية (إنستاباي والتحويل البنكي).
          </p>
        </div>
        <Link to="/owner/payments/settings" className="rounded-xl border border-slate-300 px-4 py-2 text-sm">
          إعدادات الدفع
        </Link>
      </header>

      {list?.summary.available ? (
        <p className="text-sm text-slate-700">
          الإيرادات المؤكدة: {formatPaymentAmount(list.summary.value, list.summary.currency ?? 'SAR')} من{' '}
          {list.summary.paid_count.toLocaleString('ar-SA')} دفعة مدفوعة.
        </p>
      ) : (
        <FeedbackBanner kind="info">لا توجد إيرادات مسجّلة بعد من مدفوعات مؤكدة.</FeedbackBanner>
      )}

      {list && list.summary.pending_verification_count > 0 ? (
        <FeedbackBanner kind="warning">
          {list.summary.pending_verification_count.toLocaleString('ar-SA')} تحويل بانتظار التحقق.
        </FeedbackBanner>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-3">
        <input
          value={filters.q ?? ''}
          onChange={(event) => setFilters((current) => ({ ...current, q: event.target.value, page: 1 }))}
          placeholder="بحث بالعميل أو الطلب أو المرجع"
          className="min-h-11 rounded-xl border border-slate-300 px-3 py-2 text-sm"
        />
        <select
          value={filters.status ?? ''}
          onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value, page: 1 }))}
          className="min-h-11 rounded-xl border border-slate-300 px-3 py-2 text-sm"
        >
          {STATUS_FILTERS.map((option) => (
            <option key={option.value || 'all-status'} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        <select
          value={filters.payment_method ?? ''}
          onChange={(event) => setFilters((current) => ({ ...current, payment_method: event.target.value, page: 1 }))}
          className="min-h-11 rounded-xl border border-slate-300 px-3 py-2 text-sm"
        >
          {METHOD_FILTERS.map((option) => (
            <option key={option.value || 'all-method'} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </div>

      {loading ? <DashboardPanelSkeleton label="جاري تحميل المدفوعات..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && list && list.items.length === 0 ? (
        <DashboardEmptyState title={PAYMENT_COPY.emptyOwner} description={PAYMENT_COPY.emptyOwnerDescription} />
      ) : null}

      {!loading && list && list.items.length > 0 ? (
        <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-start text-xs text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">العميل</th>
                <th className="px-4 py-3 font-medium">الطلب</th>
                <th className="px-4 py-3 font-medium">المبلغ</th>
                <th className="px-4 py-3 font-medium">الطريقة</th>
                <th className="px-4 py-3 font-medium">الحالة</th>
                <th className="px-4 py-3 font-medium">المرجع</th>
              </tr>
            </thead>
            <tbody>
              {list.items.map((payment) => (
                <tr key={payment.id} className="border-t border-slate-100">
                  <td className="px-4 py-3">
                    <Link to={ownerPaymentPath(payment.id)} className="font-medium underline">
                      {payment.customer?.name ?? '—'}
                    </Link>
                    <p className="text-xs text-slate-500">{payment.customer?.email}</p>
                  </td>
                  <td className="px-4 py-3" dir="ltr">
                    {payment.order?.reference ?? '—'}
                  </td>
                  <td className="px-4 py-3 tabular-nums">
                    {formatPaymentAmount(payment.amount, payment.currency)}
                  </td>
                  <td className="px-4 py-3">{payment.payment_method_label}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={payment.status} label={payment.status_label} />
                  </td>
                  <td className="max-w-32 truncate px-4 py-3" dir="ltr">
                    {payment.reference_number ?? payment.provider_transaction_id ?? '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {list && list.meta.last_page > 1 ? (
        <div className="flex gap-2">
          <button
            type="button"
            disabled={list.meta.current_page <= 1}
            onClick={() => setFilters((current) => ({ ...current, page: (current.page ?? 1) - 1 }))}
            className="rounded-lg border px-3 py-2 text-sm disabled:opacity-50"
          >
            السابق
          </button>
          <button
            type="button"
            disabled={list.meta.current_page >= list.meta.last_page}
            onClick={() => setFilters((current) => ({ ...current, page: (current.page ?? 1) + 1 }))}
            className="rounded-lg border px-3 py-2 text-sm disabled:opacity-50"
          >
            التالي
          </button>
        </div>
      ) : null}
    </section>
  )
}
