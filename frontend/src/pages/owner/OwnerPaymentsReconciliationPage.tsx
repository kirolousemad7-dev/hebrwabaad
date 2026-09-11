import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  getPaymentsReconciliation,
  reconcileOwnerPayment,
  type PaymentReconciliationItem,
} from '../../services/phase7'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'

const CATEGORY_LABELS: Record<string, string> = {
  processing_too_long: 'معالجة طويلة',
  amount_mismatch: 'عدم تطابق المبلغ',
  currency_mismatch: 'عدم تطابق العملة',
  failed_verification: 'فشل التحقق',
  refund_mismatch: 'استرداد معلّق/فاشل',
  processing: 'قيد المعالجة',
  failed: 'فشل',
}

function categoryLabel(category?: string | null) {
  if (!category) return null
  return CATEGORY_LABELS[category] ?? category
}

export function OwnerPaymentsReconciliationPage() {
  const [items, setItems] = useState<PaymentReconciliationItem[]>([])
  const [categories, setCategories] = useState<Record<string, number>>({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getPaymentsReconciliation()
      setItems(response.data.items ?? [])
      setCategories(response.data.meta?.categories ?? {})
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل قائمة المطابقة.'))
      setItems([])
      setCategories({})
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  const categoryEntries = useMemo(
    () => Object.entries(categories).sort((a, b) => b[1] - a[1]),
    [categories],
  )

  async function handleVerify(id: number) {
    setBusyId(id)
    setNotice(null)
    setError(null)
    try {
      await reconcileOwnerPayment(id)
      setNotice(`تم التحقق من الدفعة #${id} مع المزود.`)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر التحقق مع المزود.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">مطابقة المدفوعات</h1>
          <p className="mt-1 text-sm text-slate-600">
            دفعات البطاقة قيد المعالجة أو الفاشلة التي تحتاج مراجعة.
          </p>
        </div>
        <Link to="/owner/payments" className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
          كل المدفوعات
        </Link>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل المطابقة..." /> : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا عناصر تحتاج مطابقة" description="كل دفعات البطاقة بحالة مستقرة حالياً." />
      ) : null}

      {!loading && categoryEntries.length > 0 ? (
        <DashboardSection title="تصنيفات V2">
          <div className="flex flex-wrap gap-2">
            {categoryEntries.map(([key, count]) => (
              <span
                key={key}
                className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700"
              >
                {categoryLabel(key)}: {count.toLocaleString('ar-SA')}
              </span>
            ))}
          </div>
        </DashboardSection>
      ) : null}

      {!loading && items.length > 0 ? (
        <DashboardSection title={`${items.length.toLocaleString('ar-SA')} عنصر`}>
          <ul className="space-y-3">
            {items.map((item) => (
              <li
                key={item.id}
                className="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm"
              >
                <div className="space-y-1">
                  <p className="font-medium text-slate-900">
                    دفعة #{item.id} · {formatMoney(item.amount, item.currency || 'EGP')}
                  </p>
                  <p className="text-xs text-slate-600">
                    الحالة: {item.status}
                    {item.category ? ` · ${categoryLabel(item.category)}` : ''}
                    {item.attention_reason && item.attention_reason !== item.category
                      ? ` · ${item.attention_reason}`
                      : ''}
                  </p>
                  {item.customer?.name ? <p className="text-xs text-slate-600">العميل: {item.customer.name}</p> : null}
                  {item.printing_quotation?.reference ? (
                    <p className="text-xs text-slate-600">عرض: {item.printing_quotation.reference}</p>
                  ) : null}
                  {item.order?.reference ? (
                    <p className="text-xs text-slate-600">طلب: {item.order.reference}</p>
                  ) : null}
                  {item.failure_reason ? (
                    <p className="text-xs text-amber-800">{item.failure_reason}</p>
                  ) : null}
                  {item.reconciliation_note ? (
                    <p className="text-xs text-slate-500">{item.reconciliation_note}</p>
                  ) : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  {item.href ? (
                    <Link to={item.href} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                      فتح
                    </Link>
                  ) : (
                    <Link
                      to={`/owner/payments/${item.id}`}
                      className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                    >
                      فتح
                    </Link>
                  )}
                  {item.status === 'PAID' || item.category === 'refund_mismatch' ? (
                    <Link
                      to={`/owner/payments/${item.id}`}
                      className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs text-amber-950"
                    >
                      استرداد
                    </Link>
                  ) : null}
                  <button
                    type="button"
                    disabled={busyId === item.id}
                    className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white disabled:opacity-50"
                    onClick={() => void handleVerify(item.id)}
                  >
                    تحقق من المزود
                  </button>
                </div>
              </li>
            ))}
          </ul>
        </DashboardSection>
      ) : null}
    </section>
  )
}
