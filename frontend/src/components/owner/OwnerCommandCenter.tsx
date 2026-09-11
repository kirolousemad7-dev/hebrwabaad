import { Link } from 'react-router-dom'
import { useEffect, useState } from 'react'
import {
  DashboardErrorState,
  DashboardSection,
} from '../owner/DashboardSection'
import {
  getCommandCenter,
  searchOperations,
  snoozeAttention,
  type AttentionItem,
  type AttentionSnoozePreset,
  type CommandCenterData,
  type OperationsSearchResult,
} from '../../services/operations'
import { formatTimeShort } from '../../utils/calendarDates'
import { calendarTypeLabel } from '../../utils/calendarLabels'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

function severityClass(severity: string): string {
  if (severity === 'critical') return 'border-red-300 bg-red-50/60'
  if (severity === 'high') return 'border-amber-300 bg-amber-50/50'
  return 'border-slate-200 bg-white'
}

function healthLabel(status: string): string {
  if (status === 'overdue') return 'متأخر'
  if (status === 'needs_attention') return 'يحتاج متابعة'
  return 'على المسار'
}

function healthClass(status: string): string {
  if (status === 'overdue') return 'text-red-800'
  if (status === 'needs_attention') return 'text-amber-800'
  return 'text-slate-700'
}

function attentionHref(item: AttentionItem): string {
  if (item.href) return item.href
  if (item.work_id) return '/owner/work'
  if (item.related_type === 'project' && item.related_id) {
    return `/owner/projects/${item.related_id}`
  }
  if (item.related_type === 'calendar_item' && item.related_id) {
    return `/owner/calendar?item=${item.related_id}`
  }
  if (item.type === 'printing_overdue') return '/owner/printing-ops'
  return '/owner/work'
}

function workloadLabel(row: CommandCenterData['workload_snapshot'][number]): string {
  if (row.name) return row.name
  if (row.user_id != null && row.user_id > 0) return `مستخدم #${row.user_id}`
  if (row.id != null) return `مستخدم #${row.id}`
  return 'غير معيّن'
}

export function OwnerCommandCenter() {
  const [data, setData] = useState<CommandCenterData | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [searchDraft, setSearchDraft] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const [searchResults, setSearchResults] = useState<OperationsSearchResult | null>(null)
  const [searching, setSearching] = useState(false)
  const [snoozingKey, setSnoozingKey] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCommandCenter()
      setData(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل مركز العمل.'))
      setData(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  useEffect(() => {
    const handle = window.setTimeout(() => setSearchQuery(searchDraft.trim()), 300)
    return () => window.clearTimeout(handle)
  }, [searchDraft])

  useEffect(() => {
    if (searchQuery.length < 2) {
      setSearchResults(null)
      return
    }

    let cancelled = false
    setSearching(true)
    void searchOperations(searchQuery)
      .then((response) => {
        if (!cancelled) setSearchResults(response.data)
      })
      .catch(() => {
        if (!cancelled) setSearchResults(null)
      })
      .finally(() => {
        if (!cancelled) setSearching(false)
      })

    return () => {
      cancelled = true
    }
  }, [searchQuery])

  async function handleSnooze(item: AttentionItem, preset: AttentionSnoozePreset) {
    const key = item.attention_key
    if (!key) return
    setSnoozingKey(key)
    try {
      await snoozeAttention(key, { preset })
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تأجيل التنبيه.'))
    } finally {
      setSnoozingKey(null)
    }
  }

  if (loading) {
    return <div className="h-48 animate-pulse rounded-2xl border border-slate-200 bg-slate-50" aria-busy="true" />
  }

  if (error && !data) {
    return <DashboardErrorState message={error} onRetry={() => void load()} />
  }

  if (!data) {
    return null
  }

  const slaBreached = data.summary.sla_breached ?? 0
  const summaryCards = [
    { label: 'مهام اليوم', value: data.summary.today_tasks },
    { label: 'المتأخر', value: data.summary.overdue, danger: data.summary.overdue > 0 },
    { label: 'اجتماعات اليوم', value: data.summary.today_meetings },
    { label: 'تسليمات قريبة', value: data.summary.upcoming_deliveries },
    {
      label: 'مشاريع تحتاج متابعة',
      value: data.summary.projects_need_attention,
      warn: data.summary.projects_need_attention > 0,
    },
    ...(typeof data.summary.sla_breached === 'number'
      ? [{ label: 'خرق SLA', value: slaBreached, danger: slaBreached > 0 }]
      : []),
  ]

  const hasSearchHits =
    searchResults &&
    (searchResults.calendar.length > 0 ||
      searchResults.projects.length > 0 ||
      searchResults.crm_leads.length > 0 ||
      searchResults.orders.length > 0)

  const overdueAttention = data.attention.filter((item) => item.type.includes('overdue'))
  const intervention = data.attention.filter((item) => !item.type.includes('overdue') || item.severity === 'critical')

  return (
    <DashboardSection
      title="مركز العمل"
      description="ملخص تشغيلي لما يحتاج انتباهك اليوم."
      action={
        <div className="flex flex-wrap gap-3">
          <Link
            to="/owner/work"
            className="text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            فتح العمل
          </Link>
          <Link
            to="/owner/calendar"
            className="text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            فتح التقويم
          </Link>
        </div>
      }
    >
      {error ? <p className="mb-3 text-sm text-red-700">{error}</p> : null}

      <div className="mb-4">
        <label className="block text-xs text-slate-500">
          بحث سريع
          <input
            type="search"
            value={searchDraft}
            onChange={(event) => setSearchDraft(event.target.value)}
            placeholder="مهام، مشاريع، طلبات..."
            className={`mt-1 ${fieldClass} max-w-md`}
          />
        </label>
        {searching ? <p className="mt-2 text-xs text-slate-500">جاري البحث...</p> : null}
        {hasSearchHits ? (
          <div className="mt-2 grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 text-sm sm:grid-cols-2">
            {searchResults.calendar.length > 0 ? (
              <div>
                <p className="mb-1 text-xs font-medium text-slate-500">التقويم</p>
                <ul className="space-y-1">
                  {searchResults.calendar.slice(0, 4).map((item) => (
                    <li key={`cal-${item.id}`}>
                      <Link to={`/owner/calendar?item=${item.id}`} className="underline">
                        {item.title}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            {searchResults.projects.length > 0 ? (
              <div>
                <p className="mb-1 text-xs font-medium text-slate-500">المشاريع</p>
                <ul className="space-y-1">
                  {searchResults.projects.slice(0, 4).map((item) => (
                    <li key={`proj-${item.id}`}>
                      <Link to={`/owner/projects/${item.id}`} className="underline">
                        {item.title}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            {searchResults.orders.length > 0 ? (
              <div>
                <p className="mb-1 text-xs font-medium text-slate-500">الطلبات</p>
                <ul className="space-y-1">
                  {searchResults.orders.slice(0, 4).map((item) => (
                    <li key={`ord-${item.id}`}>
                      <Link to={`/owner/orders/${item.id}`} className="underline">
                        {item.title}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            {searchResults.crm_leads.length > 0 ? (
              <div>
                <p className="mb-1 text-xs font-medium text-slate-500">CRM</p>
                <ul className="space-y-1">
                  {searchResults.crm_leads.slice(0, 4).map((item) => (
                    <li key={`lead-${item.id}`}>
                      <Link to={`/crm/leads/${item.id}`} className="underline">
                        {item.title}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        ) : null}
      </div>

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {summaryCards.map((chip) => (
          <div
            key={chip.label}
            className={`rounded-2xl border px-3 py-2 ${
              chip.danger
                ? 'border-red-300 bg-red-50/70'
                : chip.warn
                  ? 'border-amber-200 bg-amber-50/50'
                  : 'border-slate-200 bg-white'
            }`}
          >
            <p className="text-xs text-slate-500">{chip.label}</p>
            <p
              className={`text-xl font-semibold ${
                chip.danger ? 'text-red-900' : chip.warn ? 'text-amber-900' : 'text-slate-900'
              }`}
            >
              {chip.value.toLocaleString('ar-SA')}
            </p>
          </div>
        ))}
      </div>

      {data.system_health ? (
        <div className="mt-4">
          <h3 className="mb-2 text-sm font-semibold text-slate-800">صحة النظام</h3>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {[
              {
                label: 'أتمتة فاشلة (24س)',
                value: Number(data.system_health.failed_automations_24h ?? 0),
                href: '/owner/automations',
              },
              {
                label: 'Webhooks فاشلة (24س)',
                value: Number(data.system_health.failed_webhooks_24h ?? 0),
                href: '/owner/integrations/webhooks',
              },
              ...(typeof data.system_health.delayed_notifications === 'number'
                ? [
                    {
                      label: 'إشعارات مؤجّلة',
                      value: Number(data.system_health.delayed_notifications),
                      href: '/owner/notifications',
                    },
                  ]
                : []),
            ].map((chip) => (
              <Link
                key={chip.label}
                to={chip.href}
                className={`rounded-2xl border px-3 py-2 ${
                  chip.value > 0 ? 'border-red-300 bg-red-50/70' : 'border-slate-200 bg-white'
                }`}
              >
                <p className="text-xs text-slate-500">{chip.label}</p>
                <p className={`text-xl font-semibold ${chip.value > 0 ? 'text-red-900' : 'text-slate-900'}`}>
                  {chip.value.toLocaleString('ar-SA')}
                </p>
              </Link>
            ))}
          </div>
        </div>
      ) : null}

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <div className="space-y-2">
          <h3 className="text-sm font-semibold text-slate-800">يحتاج تدخل</h3>
          {intervention.length === 0 ? (
            <p className="rounded-xl border border-slate-100 px-3 py-2 text-sm text-slate-500">لا عناصر عاجلة.</p>
          ) : (
            <ul className="space-y-1.5">
              {intervention.slice(0, 8).map((item, index) => (
                <li
                  key={`${item.attention_key ?? item.type}-${item.related_id ?? index}`}
                  className={`rounded-xl border px-3 py-2 text-sm ${severityClass(item.severity)}`}
                >
                  <Link to={attentionHref(item)} className="font-medium text-slate-900 underline-offset-2 hover:underline">
                    {item.title}
                  </Link>
                  {item.description ? <p className="mt-0.5 text-xs text-slate-600">{item.description}</p> : null}
                  {item.attention_key ? (
                    <div className="mt-2 flex flex-wrap gap-1">
                      <span className="text-[11px] text-slate-500">تأجيل:</span>
                      {([
                        ['1h', 'ساعة'],
                        ['today', 'اليوم'],
                        ['tomorrow', 'غداً'],
                      ] as const).map(([preset, label]) => (
                        <button
                          key={preset}
                          type="button"
                          disabled={snoozingKey === item.attention_key}
                          onClick={() => void handleSnooze(item, preset)}
                          className="rounded border border-slate-300 bg-white px-2 py-0.5 text-[11px] disabled:opacity-50"
                        >
                          {label}
                        </button>
                      ))}
                    </div>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="space-y-2">
          <h3 className="text-sm font-semibold text-slate-800">المتأخر</h3>
          {overdueAttention.length === 0 ? (
            <p className="rounded-xl border border-slate-100 px-3 py-2 text-sm text-slate-500">لا عناصر متأخرة ظاهرة.</p>
          ) : (
            <ul className="space-y-1.5">
              {overdueAttention.slice(0, 6).map((item, index) => (
                <li
                  key={`overdue-${item.attention_key ?? item.type}-${index}`}
                  className="rounded-xl border border-red-200 bg-red-50/50 px-3 py-2 text-sm"
                >
                  <Link to={attentionHref(item)} className="font-medium underline-offset-2 hover:underline">
                    {item.title}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <div className="space-y-2">
          <h3 className="text-sm font-semibold text-slate-800">اليوم</h3>
          {data.today_timeline.length === 0 ? (
            <p className="rounded-xl border border-slate-100 px-3 py-2 text-sm text-slate-500">لا عناصر مجدولة اليوم.</p>
          ) : (
            <ul className="space-y-1.5">
              {data.today_timeline.slice(0, 8).map((item) => (
                <li
                  key={String(item.id)}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm"
                >
                  <span className="font-medium text-slate-900">{item.title}</span>
                  <span className="text-xs text-slate-500">
                    {calendarTypeLabel(item.type)} · {formatTimeShort(item.starts_at)}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="space-y-2">
          <h3 className="text-sm font-semibold text-slate-800">الفريق</h3>
          {data.workload_snapshot.length === 0 ? (
            <p className="rounded-xl border border-slate-100 px-3 py-2 text-sm text-slate-500">لا بيانات عبء حالياً.</p>
          ) : (
            <ul className="space-y-1.5">
              {data.workload_snapshot.map((row, index) => (
                <li
                  key={`${row.user_id ?? row.id ?? 'none'}-${index}`}
                  className="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2 text-sm"
                >
                  <span>{workloadLabel(row)}</span>
                  <span className="tabular-nums text-slate-600">
                    {row.count.toLocaleString('ar-SA')}
                    {typeof row.overdue === 'number' && row.overdue > 0
                      ? ` · متأخر ${row.overdue.toLocaleString('ar-SA')}`
                      : ''}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <div className="space-y-2">
          <h3 className="text-sm font-semibold text-slate-800">المشاريع</h3>
          {data.project_health.length === 0 ? (
            <p className="rounded-xl border border-slate-100 px-3 py-2 text-sm text-slate-500">كل المشاريع على المسار.</p>
          ) : (
            <ul className="space-y-1.5">
              {data.project_health.map((project) => (
                <li
                  key={project.id}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm"
                >
                  <Link to={`/owner/projects/${project.id}`} className="font-medium underline-offset-2 hover:underline">
                    {project.title}
                  </Link>
                  <span className={`text-xs ${healthClass(project.health.status)}`}>
                    {project.health.label || healthLabel(project.health.status)}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="space-y-2">
          <h3 className="text-sm font-semibold text-slate-800">الطباعة</h3>
          <p className="rounded-xl border border-slate-100 px-3 py-2 text-sm text-slate-600">
            تسليمات قريبة:{' '}
            <span className="font-semibold text-slate-900">
              {data.summary.upcoming_deliveries.toLocaleString('ar-SA')}
            </span>
          </p>
          <Link to="/owner/printing-ops" className="inline-block text-sm underline">
            فتح تشغيل الطباعة
          </Link>
          <Link to="/owner/printing-quotations" className="ms-3 inline-block text-sm underline">
            عروض الأسعار
          </Link>
        </div>
      </div>

      {data.revenue_ops ? (
        <div className="mt-4 space-y-2">
          <h3 className="text-sm font-semibold text-slate-800">الإيرادات / الطباعة التجارية</h3>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {[
              { label: 'مسودات', value: data.revenue_ops.quotations_draft },
              { label: 'مُرسلة', value: data.revenue_ops.quotations_sent },
              { label: 'مقبولة', value: data.revenue_ops.quotations_accepted },
              { label: 'بانتظار دفع', value: data.revenue_ops.quotations_pending_payment },
              { label: 'دفعات مسجّلة', value: data.revenue_ops.payments_recorded },
            ]
              .filter((row) => typeof row.value === 'number')
              .map((row) => (
                <div key={row.label} className="rounded-xl border border-slate-100 bg-white px-3 py-2 text-sm">
                  <p className="text-xs text-slate-500">{row.label}</p>
                  <p className="font-semibold tabular-nums text-slate-900">
                    {(row.value as number).toLocaleString('ar-SA')}
                  </p>
                </div>
              ))}
            {data.revenue_ops.amount_collected != null ? (
              <div className="rounded-xl border border-emerald-200 bg-emerald-50/50 px-3 py-2 text-sm">
                <p className="text-xs text-emerald-800">محصّل</p>
                <p className="font-semibold text-emerald-950">
                  {String(data.revenue_ops.amount_collected)}
                  {data.revenue_ops.currency ? ` ${data.revenue_ops.currency}` : ''}
                </p>
              </div>
            ) : null}
            {data.revenue_ops.amount_outstanding != null ? (
              <div className="rounded-xl border border-amber-200 bg-amber-50/50 px-3 py-2 text-sm">
                <p className="text-xs text-amber-900">متبقي</p>
                <p className="font-semibold text-amber-950">
                  {String(data.revenue_ops.amount_outstanding)}
                  {data.revenue_ops.currency ? ` ${data.revenue_ops.currency}` : ''}
                </p>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}
    </DashboardSection>
  )
}
