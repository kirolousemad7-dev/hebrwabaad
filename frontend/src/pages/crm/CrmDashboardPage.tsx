import { Link } from 'react-router-dom'
import { useEffect, useMemo, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardOverviewSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { useAuth } from '../../context/AuthContext'
import {
  getCrmDashboard,
  getCrmForecast,
  getCrmLeads,
  getCrmTargetsProgress,
  type CrmDashboardData,
  type CrmForecastData,
  type CrmLead,
  type CrmTargetProgress,
} from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { isCrmManager } from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

type PeriodKey = 'today' | 'week' | 'month' | 'quarter' | 'year'

const PERIODS: { key: PeriodKey; label: string }[] = [
  { key: 'today', label: 'اليوم' },
  { key: 'week', label: 'هذا الأسبوع' },
  { key: 'month', label: 'هذا الشهر' },
  { key: 'quarter', label: 'هذا الربع' },
  { key: 'year', label: 'هذه السنة' },
]

function toDateInput(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function rangeForPeriod(period: PeriodKey): { from: string; to: string } {
  const now = new Date()
  const to = toDateInput(now)
  const start = new Date(now)

  if (period === 'today') return { from: to, to }
  if (period === 'week') {
    const day = start.getDay()
    const diff = day === 0 ? 6 : day - 1
    start.setDate(start.getDate() - diff)
    return { from: toDateInput(start), to }
  }
  if (period === 'month') {
    start.setDate(1)
    return { from: toDateInput(start), to }
  }
  if (period === 'quarter') {
    const quarter = Math.floor(start.getMonth() / 3)
    start.setMonth(quarter * 3, 1)
    return { from: toDateInput(start), to }
  }
  start.setMonth(0, 1)
  return { from: toDateInput(start), to }
}

function KpiCard({ title, value, hint }: { title: string; value: string; hint?: string }) {
  return (
    <article className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p className="text-sm text-slate-600">{title}</p>
      <p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p>
      {hint ? <p className="mt-1 text-xs text-slate-500">{hint}</p> : null}
    </article>
  )
}

export function CrmDashboardPage() {
  const { user } = useAuth()
  const manager = isCrmManager(user?.role)
  const [period, setPeriod] = useState<PeriodKey>('month')
  const range = useMemo(() => rangeForPeriod(period), [period])
  const [data, setData] = useState<CrmDashboardData | null>(null)
  const [unassigned, setUnassigned] = useState<CrmLead[]>([])
  const [stale, setStale] = useState<CrmLead[]>([])
  const [forecast, setForecast] = useState<CrmForecastData | null>(null)
  const [targets, setTargets] = useState<CrmTargetProgress[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)

    try {
      const response = await getCrmDashboard(range.from, range.to)
      setData(response.data)

      const extras: PromiseSettledResult<unknown>[] = await Promise.allSettled([
        getCrmLeads({ unassigned: true, per_page: 10 }),
        getCrmLeads({ needs_attention: true, per_page: 10 }),
        manager ? getCrmForecast() : Promise.resolve(null),
        getCrmTargetsProgress(),
      ])

      if (extras[0].status === 'fulfilled' && extras[0].value) {
        setUnassigned((extras[0].value as Awaited<ReturnType<typeof getCrmLeads>>).data.items)
      }
      if (extras[1].status === 'fulfilled' && extras[1].value) {
        setStale((extras[1].value as Awaited<ReturnType<typeof getCrmLeads>>).data.items)
      }
      if (manager && extras[2].status === 'fulfilled' && extras[2].value) {
        setForecast((extras[2].value as Awaited<ReturnType<typeof getCrmForecast>>).data)
      }
      if (extras[3].status === 'fulfilled' && extras[3].value) {
        setTargets((extras[3].value as Awaited<ReturnType<typeof getCrmTargetsProgress>>).data.items.slice(0, 5))
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل لوحة المبيعات.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [range.from, range.to, manager])

  const kpis = data?.kpis ?? null
  const view = data?.view ?? (manager ? 'manager' : 'rep')

  return (
    <section className="space-y-8">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold">لوحة المبيعات</h1>
          <p className="text-slate-600">
            {view === 'manager' ? 'نظرة المدير: الفريق، غير المعيّنين، والمتأخرين.' : 'نظرتك الشخصية: مهامك ومتابعاتك.'}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {PERIODS.map((item) => (
            <button
              key={item.key}
              type="button"
              onClick={() => setPeriod(item.key)}
              className={`min-h-10 rounded-xl px-3 text-sm ${
                period === item.key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </header>

      {loading ? <DashboardOverviewSkeleton /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && kpis ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard title="عملاء جدد" value={kpis.new_leads.toLocaleString('ar-SA')} />
            <KpiCard title="مفتوحون" value={kpis.open_leads.toLocaleString('ar-SA')} />
            <KpiCard title="تم الإغلاق" value={kpis.won_leads.toLocaleString('ar-SA')} />
            <KpiCard title="خسارة" value={kpis.lost_leads.toLocaleString('ar-SA')} />
            <KpiCard title="نسبة الفوز" value={`${kpis.win_rate.toLocaleString('ar-SA')}%`} />
            <KpiCard title="قيمة خط الأنابيب" value={formatMoney(kpis.pipeline_value)} />
            <KpiCard title="إيرادات مغلقة" value={formatMoney(kpis.won_revenue)} />
            <KpiCard title="خط أنابيب مرجّح" value={formatMoney(kpis.weighted_pipeline)} />
            <KpiCard title="متابعات مجدولة" value={kpis.follow_ups_scheduled.toLocaleString('ar-SA')} />
            <KpiCard
              title="متابعات متأخرة"
              value={kpis.follow_ups_overdue.toLocaleString('ar-SA')}
              hint={kpis.follow_ups_overdue > 0 ? 'راجع قائمة المتابعات' : undefined}
            />
            <KpiCard title="غير معيّنين" value={(kpis.unassigned_leads ?? data?.manager?.unassigned_count ?? 0).toLocaleString('ar-SA')} />
            <KpiCard title="يحتاجون انتباه" value={(kpis.stale_leads ?? data?.manager?.stale_leads ?? 0).toLocaleString('ar-SA')} />
          </div>

          {view === 'rep' && data?.rep ? (
            <DashboardSection title="ملخصك">
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard title="عملائي المفتوحون" value={data.rep.my_open_leads.toLocaleString('ar-SA')} />
                <KpiCard title="متابعاتي المتأخرة" value={data.rep.my_overdue_follow_ups.toLocaleString('ar-SA')} />
                <KpiCard title="يحتاجون انتباهي" value={data.rep.my_needs_attention.toLocaleString('ar-SA')} />
                <KpiCard title="إيرادي المغلق" value={formatMoney(data.rep.my_won_revenue)} />
              </div>
            </DashboardSection>
          ) : null}

          {view === 'manager' && data?.manager ? (
            <DashboardSection title="أداء الفريق">
              {(data.manager.rep_performance ?? []).length === 0 ? (
                <DashboardEmptyState title="لا مندوبين." description="أضف مندوبي مبيعات لرؤية الأداء." />
              ) : (
                <div className="overflow-x-auto rounded-2xl border bg-white">
                  <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-right">
                      <tr>
                        <th className="px-3 py-2">المندوب</th>
                        <th className="px-3 py-2">مفتوح</th>
                        <th className="px-3 py-2">صفقات رابحة</th>
                        <th className="px-3 py-2">إيراد</th>
                        <th className="px-3 py-2">أنشطة</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.manager.rep_performance.map((row) => (
                        <tr key={row.user.id} className="border-t">
                          <td className="px-3 py-2 font-medium">{row.user.name}</td>
                          <td className="px-3 py-2">{row.open_leads.toLocaleString('ar-SA')}</td>
                          <td className="px-3 py-2">{row.won_deals.toLocaleString('ar-SA')}</td>
                          <td className="px-3 py-2">{formatMoney(row.won_revenue)}</td>
                          <td className="px-3 py-2">{row.activities.toLocaleString('ar-SA')}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
              <p className="mt-3 text-sm text-slate-600">
                متوسط أول تواصل:{' '}
                {data.manager.avg_first_contact_minutes != null
                  ? `${data.manager.avg_first_contact_minutes.toLocaleString('ar-SA')} دقيقة`
                  : '—'}{' '}
                · هدف SLA: {data.manager.sla_target_minutes.toLocaleString('ar-SA')} دقيقة
              </p>
            </DashboardSection>
          ) : null}

          <div className="grid gap-4 lg:grid-cols-2">
            <DashboardSection
              title="غير معيّنين"
              action={
                <Link to="/crm/leads?assigned=unassigned" className="text-sm text-slate-600 underline">
                  عرض الكل
                </Link>
              }
            >
              {unassigned.length === 0 ? (
                <p className="text-sm text-slate-600">لا عملاء غير معيّنين.</p>
              ) : (
                <ul className="divide-y divide-slate-100 rounded-2xl border bg-white">
                  {unassigned.map((lead) => (
                    <li key={lead.id} className="flex items-center justify-between gap-2 px-4 py-3 text-sm">
                      <Link to={`/crm/leads/${lead.id}`} className="font-medium hover:underline">
                        {lead.full_name}
                      </Link>
                      <span className="text-xs text-slate-500">{lead.age_days != null ? `${lead.age_days} يوم` : ''}</span>
                    </li>
                  ))}
                </ul>
              )}
            </DashboardSection>

            <DashboardSection
              title="يحتاجون انتباه"
              action={
                <Link to="/crm/leads?stale=1" className="text-sm text-slate-600 underline">
                  عرض الكل
                </Link>
              }
            >
              {stale.length === 0 ? (
                <p className="text-sm text-slate-600">لا عملاء متأخرين.</p>
              ) : (
                <ul className="divide-y divide-slate-100 rounded-2xl border bg-white">
                  {stale.map((lead) => (
                    <li key={lead.id} className="flex items-center justify-between gap-2 px-4 py-3 text-sm">
                      <Link to={`/crm/leads/${lead.id}`} className="font-medium hover:underline">
                        {lead.full_name}
                      </Link>
                      <span className="text-xs text-amber-700">
                        {lead.days_since_contact != null ? `${lead.days_since_contact} يوم بلا تواصل` : 'يحتاج انتباه'}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </DashboardSection>
          </div>

          {forecast ? (
            <DashboardSection title="توقعات سريعة" action={<Link to="/crm/forecast" className="text-sm underline">التفاصيل</Link>}>
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard title="مرجّح" value={formatMoney(forecast.weighted)} />
                <KpiCard title="التزام" value={formatMoney(forecast.commit)} />
                <KpiCard title="هذا الشهر" value={formatMoney(forecast.expected_this_month)} />
                <KpiCard title="الشهر القادم" value={formatMoney(forecast.expected_next_month)} />
              </div>
            </DashboardSection>
          ) : null}

          {targets.length > 0 ? (
            <DashboardSection title="تقدم الأهداف" action={<Link to="/crm/targets" className="text-sm underline">كل الأهداف</Link>}>
              <ul className="space-y-3">
                {targets.map((item) => {
                  const percent = Math.min(100, Math.max(0, item.progress_percent ?? 0))
                  return (
                    <li key={item.id} className="rounded-2xl border bg-white p-3">
                      <div className="mb-2 flex justify-between text-sm">
                        <span>{item.user?.name ?? 'هدف'} · {item.target_type}</span>
                        <span>{percent.toLocaleString('ar-SA')}%</span>
                      </div>
                      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div className="h-full rounded-full bg-amber-500" style={{ width: `${percent}%` }} />
                      </div>
                    </li>
                  )
                })}
              </ul>
            </DashboardSection>
          ) : null}

          {kpis.follow_ups_overdue > 0 ? (
            <DashboardSection title="تنبيه">
              <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                لديك {kpis.follow_ups_overdue.toLocaleString('ar-SA')} متابعة متأخرة.{' '}
                <Link to="/crm/follow-ups?overdue=1" className="font-medium underline">
                  عرض المتابعات المتأخرة
                </Link>
              </div>
            </DashboardSection>
          ) : null}

          {data?.funnel && data.funnel.length > 0 ? (
            <DashboardSection title="قمع المبيعات" description="عدد وقيمة العملاء المحتملين في كل مرحلة خلال الفترة.">
              <ol className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {data.funnel.map((stage, index) => {
                  const previous = index > 0 ? data.funnel![index - 1] : null
                  const conversion =
                    previous && previous.count > 0 ? Math.round((stage.count / previous.count) * 1000) / 10 : null

                  return (
                    <li key={stage.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                      <p className="text-sm font-medium text-slate-900">{stage.name}</p>
                      <p className="mt-2 text-2xl font-semibold">{stage.count.toLocaleString('ar-SA')}</p>
                      <p className="mt-1 text-sm text-slate-600">{formatMoney(stage.value)}</p>
                      {conversion !== null ? (
                        <p className="mt-2 text-xs text-slate-500">تحويل من المرحلة السابقة: {conversion}%</p>
                      ) : null}
                    </li>
                  )
                })}
              </ol>
            </DashboardSection>
          ) : null}

          <DashboardSection title="اختصارات سريعة">
            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              {[
                { to: '/crm/inbox', label: 'صندوق الوارد' },
                { to: '/crm/leads', label: 'العملاء المحتملون' },
                { to: '/crm/pipeline', label: 'خط الأنابيب' },
                { to: '/crm/calendar', label: 'التقويم' },
                { to: '/crm/follow-ups', label: 'المتابعات' },
                { to: '/crm/quotations', label: 'عروض الأسعار' },
                { to: '/crm/companies', label: 'الشركات' },
                { to: '/crm/opportunities', label: 'الفرص' },
              ].map((item) => (
                <li key={item.to}>
                  <Link
                    to={item.to}
                    className="flex min-h-12 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium shadow-sm hover:bg-slate-50"
                  >
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </DashboardSection>
        </>
      ) : null}

      {!loading && !error && !kpis ? (
        <DashboardEmptyState title="لا توجد بيانات." description="ابدأ بإضافة عملاء محتملين لرؤية المؤشرات." />
      ) : null}
    </section>
  )
}
