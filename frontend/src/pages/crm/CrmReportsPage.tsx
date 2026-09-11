import { useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import {
  getCrmDashboard,
  getCrmForecast,
  getCrmReportLostReasons,
  getCrmReportReps,
  getCrmReportServices,
  getCrmReportSources,
  type CrmDashboardData,
  type CrmForecastData,
} from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'

type TabKey = 'sources' | 'reps' | 'lost' | 'services' | 'funnel' | 'cycle'

type SourcesRow = { source_id: number | null; total: number; source?: { id: number; name: string; slug: string } | null }
type RepsRow = {
  user: { id: number; name: string; email: string; role?: string } | null
  total_leads: number
  won_leads: number
  lost_leads: number
  deal_value_sum: number
}
type LostRow = { reason: { id: number; name: string; slug?: string } | null; total: number }
type ServicesRow = {
  service_id: number | null
  total: number
  deal_value_sum: number
  service?: { id: number; name: string; slug: string } | null
}

export function CrmReportsPage() {
  const [tab, setTab] = useState<TabKey>('sources')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [sources, setSources] = useState<SourcesRow[]>([])
  const [reps, setReps] = useState<RepsRow[]>([])
  const [lost, setLost] = useState<LostRow[]>([])
  const [services, setServices] = useState<ServicesRow[]>([])
  const [dashboard, setDashboard] = useState<CrmDashboardData | null>(null)
  const [forecast, setForecast] = useState<CrmForecastData | null>(null)

  async function load() {
    setLoading(true)
    setError(null)

    try {
      if (tab === 'sources') {
        const response = await getCrmReportSources()
        setSources(response.data.items)
      } else if (tab === 'reps') {
        const response = await getCrmReportReps()
        setReps(response.data.items)
      } else if (tab === 'lost') {
        const response = await getCrmReportLostReasons()
        setLost(response.data.items)
      } else if (tab === 'services') {
        const response = await getCrmReportServices()
        setServices(response.data.items)
      } else if (tab === 'funnel') {
        const [dash, fore] = await Promise.all([getCrmDashboard(), getCrmForecast()])
        setDashboard(dash.data)
        setForecast(fore.data)
      } else {
        const dash = await getCrmDashboard()
        setDashboard(dash.data)
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل التقرير.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab])

  const lostTotal = lost.reduce((sum, row) => sum + Number(row.total), 0)
  const empty =
    (tab === 'sources' && sources.length === 0) ||
    (tab === 'reps' && reps.length === 0) ||
    (tab === 'lost' && lost.length === 0) ||
    (tab === 'services' && services.length === 0) ||
    (tab === 'funnel' && !(dashboard?.funnel && dashboard.funnel.length > 0)) ||
    (tab === 'cycle' && !dashboard?.manager)

  return (
    <DashboardSection title="تقارير المبيعات" description="مصادر، مندوبون، خسارة، خدمات، قمع التحويل، ودورة التواصل.">
      <div className="flex flex-wrap gap-2">
        {(
          [
            { key: 'sources', label: 'المصادر' },
            { key: 'reps', label: 'المندوبون' },
            { key: 'lost', label: 'تحليل الخسارة' },
            { key: 'services', label: 'الخدمات' },
            { key: 'funnel', label: 'قمع التحويل' },
            { key: 'cycle', label: 'دورة التواصل' },
          ] as const
        ).map((item) => (
          <button
            key={item.key}
            type="button"
            onClick={() => setTab(item.key)}
            className={`min-h-10 rounded-xl px-3 text-sm ${
              tab === item.key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {loading ? <DashboardPanelSkeleton label="جاري تحميل التقرير..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && empty ? (
        <DashboardEmptyState title="لا بيانات لهذا التقرير." description="ستظهر الإحصاءات بعد تسجيل عملاء محتملين." />
      ) : null}

      {!loading && !error && tab === 'sources' && sources.length > 0 ? (
        <ReportTable headers={['المصدر', 'العدد']}>
          {sources.map((row) => (
            <tr key={row.source_id ?? 'none'} className="border-t">
              <td className="px-3 py-2">{row.source?.name ?? 'بدون مصدر'}</td>
              <td className="px-3 py-2">{Number(row.total).toLocaleString('ar-SA')}</td>
            </tr>
          ))}
        </ReportTable>
      ) : null}

      {!loading && !error && tab === 'reps' && reps.length > 0 ? (
        <ReportTable headers={['المندوب', 'إجمالي', 'رابح', 'خسارة', 'قيمة الصفقات', 'تحويل']}>
          {reps.map((row) => {
            const closed = row.won_leads + row.lost_leads
            const rate = closed > 0 ? Math.round((row.won_leads / closed) * 1000) / 10 : 0
            return (
              <tr key={row.user?.id ?? 'none'} className="border-t">
                <td className="px-3 py-2">{row.user?.name ?? '—'}</td>
                <td className="px-3 py-2">{row.total_leads.toLocaleString('ar-SA')}</td>
                <td className="px-3 py-2">{row.won_leads.toLocaleString('ar-SA')}</td>
                <td className="px-3 py-2">{row.lost_leads.toLocaleString('ar-SA')}</td>
                <td className="px-3 py-2">{formatMoney(row.deal_value_sum)}</td>
                <td className="px-3 py-2">{rate.toLocaleString('ar-SA')}%</td>
              </tr>
            )
          })}
        </ReportTable>
      ) : null}

      {!loading && !error && tab === 'lost' && lost.length > 0 ? (
        <>
          <ReportTable headers={['السبب', 'العدد', 'النسبة']}>
            {lost.map((row) => (
              <tr key={row.reason?.id ?? 'none'} className="border-t">
                <td className="px-3 py-2">{row.reason?.name ?? '—'}</td>
                <td className="px-3 py-2">{row.total.toLocaleString('ar-SA')}</td>
                <td className="px-3 py-2">
                  {lostTotal > 0 ? `${Math.round((Number(row.total) / lostTotal) * 1000) / 10}%` : '—'}
                </td>
              </tr>
            ))}
          </ReportTable>
          <p className="text-sm text-slate-600">إجمالي سجلات الخسارة: {lostTotal.toLocaleString('ar-SA')}</p>
        </>
      ) : null}

      {!loading && !error && tab === 'services' && services.length > 0 ? (
        <ReportTable headers={['الخدمة', 'العدد', 'قيمة الصفقات']}>
          {services.map((row) => (
            <tr key={row.service_id ?? 'none'} className="border-t">
              <td className="px-3 py-2">{row.service?.name ?? '—'}</td>
              <td className="px-3 py-2">{Number(row.total).toLocaleString('ar-SA')}</td>
              <td className="px-3 py-2">{formatMoney(row.deal_value_sum)}</td>
            </tr>
          ))}
        </ReportTable>
      ) : null}

      {!loading && !error && tab === 'funnel' && dashboard?.funnel && dashboard.funnel.length > 0 ? (
        <div className="space-y-4">
          <ol className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {dashboard.funnel.map((stage, index) => {
              const previous = index > 0 ? dashboard.funnel![index - 1] : null
              const conversion =
                previous && previous.count > 0 ? Math.round((stage.count / previous.count) * 1000) / 10 : null
              return (
                <li key={stage.id} className="rounded-2xl border bg-white p-4 shadow-sm">
                  <p className="font-medium">{stage.name}</p>
                  <p className="mt-2 text-2xl font-semibold">{stage.count.toLocaleString('ar-SA')}</p>
                  <p className="text-sm text-slate-600">{formatMoney(stage.value)}</p>
                  {conversion !== null ? <p className="mt-2 text-xs text-slate-500">تحويل: {conversion}%</p> : null}
                </li>
              )
            })}
          </ol>
          {forecast ? (
            <div className="grid gap-3 sm:grid-cols-3">
              <article className="rounded-2xl border bg-white p-4">
                <p className="text-sm text-slate-600">مرجّح</p>
                <p className="text-xl font-semibold">{formatMoney(forecast.weighted)}</p>
              </article>
              <article className="rounded-2xl border bg-white p-4">
                <p className="text-sm text-slate-600">التزام</p>
                <p className="text-xl font-semibold">{formatMoney(forecast.commit)}</p>
              </article>
              <article className="rounded-2xl border bg-white p-4">
                <p className="text-sm text-slate-600">أفضل حالة</p>
                <p className="text-xl font-semibold">{formatMoney(forecast.best_case)}</p>
              </article>
            </div>
          ) : null}
        </div>
      ) : null}

      {!loading && !error && tab === 'cycle' && dashboard ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <article className="rounded-2xl border bg-white p-4">
            <p className="text-sm text-slate-600">نسبة الفوز</p>
            <p className="mt-2 text-2xl font-semibold">{dashboard.kpis.win_rate.toLocaleString('ar-SA')}%</p>
          </article>
          <article className="rounded-2xl border bg-white p-4">
            <p className="text-sm text-slate-600">متوسط أول تواصل</p>
            <p className="mt-2 text-2xl font-semibold">
              {dashboard.manager?.avg_first_contact_minutes != null
                ? `${dashboard.manager.avg_first_contact_minutes.toLocaleString('ar-SA')} د`
                : '—'}
            </p>
            <p className="mt-1 text-xs text-slate-500">
              هدف SLA: {dashboard.manager?.sla_target_minutes?.toLocaleString('ar-SA') ?? '—'} دقيقة
            </p>
          </article>
          <article className="rounded-2xl border bg-white p-4">
            <p className="text-sm text-slate-600">عملاء يحتاجون انتباه</p>
            <p className="mt-2 text-2xl font-semibold">
              {(dashboard.kpis.stale_leads ?? dashboard.manager?.stale_leads ?? 0).toLocaleString('ar-SA')}
            </p>
          </article>
        </div>
      ) : null}
    </DashboardSection>
  )
}

function ReportTable({ headers, children }: { headers: string[]; children: React.ReactNode }) {
  return (
    <div className="overflow-x-auto rounded-2xl border bg-white">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-50 text-right">
          <tr>
            {headers.map((header) => (
              <th key={header} className="px-3 py-2">
                {header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  )
}
