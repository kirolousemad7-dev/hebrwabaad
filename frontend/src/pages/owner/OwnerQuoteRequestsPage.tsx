import { FormEvent, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  getOwnerQuoteRequests,
  type QuoteRequest,
  type QuoteRequestSummary,
} from '../../services/quoteRequests'
import { describeApiError } from '../../utils/errors'
import {
  formatQuoteDate,
  quoteRequestSourceLabel,
  quoteRequestStatusLabel,
} from '../../utils/quoteRequests'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const SUMMARY_CARDS: Array<{ key: keyof QuoteRequestSummary; label: string; filter?: string }> = [
  { key: 'NEW', label: 'جديد', filter: 'NEW' },
  { key: 'UNDER_REVIEW', label: 'قيد المراجعة', filter: 'UNDER_REVIEW' },
  { key: 'waiting_customer', label: 'ينتظر العميل' },
  { key: 'QUOTED', label: 'تم إرسال عرض', filter: 'QUOTED' },
  { key: 'REVISION_REQUESTED', label: 'طلب تعديل', filter: 'REVISION_REQUESTED' },
]

export function OwnerQuoteRequestsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [items, setItems] = useState<QuoteRequest[]>([])
  const [summary, setSummary] = useState<QuoteRequestSummary | null>(null)
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
  const [page, setPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState(searchParams.get('status') || '')
  const [sourceFilter, setSourceFilter] = useState(searchParams.get('source') || '')
  const [query, setQuery] = useState(searchParams.get('q') || '')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function loadList() {
    setLoading(true)
    setError(null)
    try {
      const response = await getOwnerQuoteRequests({
        page,
        per_page: 20,
        status: statusFilter || undefined,
        source: sourceFilter || undefined,
        q: query || undefined,
      })
      setItems(response.data.items ?? [])
      setMeta(response.data.meta)
      setSummary(response.data.summary ?? null)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل طلبات التسعير.'))
      setItems([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadList()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, statusFilter, sourceFilter])

  function applyFilters(event?: FormEvent) {
    event?.preventDefault()
    const next = new URLSearchParams()
    if (statusFilter) next.set('status', statusFilter)
    if (sourceFilter) next.set('source', sourceFilter)
    if (query.trim()) next.set('q', query.trim())
    setSearchParams(next)
    setPage(1)
    void loadList()
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">طلبات التسعير</h1>
          <p className="mt-1 text-sm text-slate-600">مراجعة طلبات عروض الأسعار وإنشاء العروض التجارية.</p>
        </div>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {summary ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          {SUMMARY_CARDS.map((card) => (
            <button
              key={card.key}
              type="button"
              onClick={() => {
                if (card.filter) {
                  setStatusFilter(card.filter)
                  setPage(1)
                }
              }}
              className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-start shadow-sm hover:border-slate-300"
            >
              <p className="text-xs text-slate-500">{card.label}</p>
              <p className="mt-1 text-2xl font-semibold text-slate-900">
                {(summary[card.key] ?? 0).toLocaleString('ar-SA')}
              </p>
            </button>
          ))}
        </div>
      ) : null}

      <form onSubmit={(event) => applyFilters(event)} className="flex flex-wrap gap-2">
        <select
          className={`${fieldClass} max-w-xs`}
          value={statusFilter}
          onChange={(event) => {
            setPage(1)
            setStatusFilter(event.target.value)
          }}
        >
          <option value="">كل الحالات</option>
          {Object.entries({
            NEW: 'جديد',
            UNDER_REVIEW: 'قيد المراجعة',
            NEEDS_INFORMATION: 'نحتاج معلومات',
            READY_TO_PRICE: 'جاهز للتسعير',
            QUOTED: 'تم إرسال عرض',
            REVISION_REQUESTED: 'طلب تعديل',
            ACCEPTED: 'مقبول',
            REJECTED: 'مرفوض',
            EXPIRED: 'منتهي',
            CANCELLED: 'ملغي',
          }).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
        <select
          className={`${fieldClass} max-w-xs`}
          value={sourceFilter}
          onChange={(event) => {
            setPage(1)
            setSourceFilter(event.target.value)
          }}
        >
          <option value="">كل المصادر</option>
          {Object.entries({
            SERVICE: 'خدمة',
            PACKAGE: 'باقة',
            PACKAGE_TIER: 'مستوى باقة',
            CUSTOM_PACKAGE: 'باقة مخصصة',
            PRINTING_REQUEST: 'طباعة',
            EVENT_REQUEST: 'فعالية',
            ORDER: 'طلب',
            CONSULTANT_RECOMMENDATION: 'مستشار',
          }).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
        <input
          className={`${fieldClass} max-w-xs`}
          placeholder="بحث..."
          value={query}
          onChange={(event) => setQuery(event.target.value)}
        />
        <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">
          تصفية
        </button>
      </form>

      {loading ? <DashboardPanelSkeleton label="جاري تحميل الطلبات..." /> : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void loadList()} />
      ) : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState
          title="لا توجد طلبات تسعير تحتاج إلى مراجعة."
          description="ستظهر هنا الطلبات الجديدة من العملاء."
        />
      ) : null}

      {!loading && items.length > 0 ? (
        <DashboardSection title={`الطلبات (${meta.total.toLocaleString('ar-SA')})`}>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-slate-500">
                  <th className="px-2 py-2 text-start font-medium">المرجع</th>
                  <th className="px-2 py-2 text-start font-medium">العنوان</th>
                  <th className="px-2 py-2 text-start font-medium">العميل</th>
                  <th className="px-2 py-2 text-start font-medium">المصدر</th>
                  <th className="px-2 py-2 text-start font-medium">الحالة</th>
                  <th className="px-2 py-2 text-start font-medium">التاريخ</th>
                  <th className="px-2 py-2 text-start font-medium"> </th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id} className="border-b border-slate-100">
                    <td className="px-2 py-2 font-mono text-xs" dir="ltr">
                      {item.reference}
                    </td>
                    <td className="px-2 py-2">{item.title}</td>
                    <td className="px-2 py-2">{item.customer?.name || `#${item.customer_id}`}</td>
                    <td className="px-2 py-2">{quoteRequestSourceLabel(String(item.source_type))}</td>
                    <td className="px-2 py-2">{quoteRequestStatusLabel(String(item.status))}</td>
                    <td className="px-2 py-2">{formatQuoteDate(item.requested_at ?? item.created_at)}</td>
                    <td className="px-2 py-2">
                      <Link to={`/owner/quote-requests/${item.id}`} className="underline">
                        تفاصيل
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {meta.last_page > 1 ? (
            <div className="mt-3 flex gap-2">
              <button
                type="button"
                disabled={page <= 1}
                className="rounded-lg border px-3 py-1.5 text-sm disabled:opacity-40"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                السابق
              </button>
              <span className="self-center text-sm text-slate-500">
                {page} / {meta.last_page}
              </span>
              <button
                type="button"
                disabled={page >= meta.last_page}
                className="rounded-lg border px-3 py-1.5 text-sm disabled:opacity-40"
                onClick={() => setPage((p) => p + 1)}
              >
                التالي
              </button>
            </div>
          ) : null}
        </DashboardSection>
      ) : null}
    </section>
  )
}
