import { useEffect, useState } from 'react'
import {
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { getOperationsInsights, type OperationsInsights } from '../../services/operations'
import { describeApiError } from '../../utils/errors'

type Period = 7 | 30 | 90

export function OwnerOperationsInsightsPage() {
  const [period, setPeriod] = useState<Period>(30)
  const [data, setData] = useState<OperationsInsights | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load(nextPeriod = period) {
    setLoading(true)
    setError(null)
    try {
      const response = await getOperationsInsights(nextPeriod)
      setData(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل تقارير التشغيل.'))
      setData(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load(period)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [period])

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold text-slate-900">تقارير التشغيل</h1>
        <p className="mt-1 text-sm text-slate-600">ملخص إنجاز العمل حسب الفترة والأقسام.</p>
      </header>

      <div className="flex flex-wrap gap-2">
        {([7, 30, 90] as const).map((value) => (
          <button
            key={value}
            type="button"
            onClick={() => setPeriod(value)}
            className={`rounded-full px-3 py-1.5 text-sm ${
              period === value ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
            }`}
          >
            آخر {value} يوم
          </button>
        ))}
      </div>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {loading ? <DashboardPanelSkeleton label="جاري تحميل التقارير..." /> : null}
      {!loading && error && !data ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}

      {!loading && data ? (
        <>
          <p className="text-xs text-slate-500">
            من {data.from} إلى {data.to}
            {data.period_days != null ? ` · ${data.period_days} يوم` : ''}
          </p>

          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {[
              { label: 'إجمالي العمل', value: data.work_total ?? data.tasks_total },
              { label: 'مكتمل', value: data.completed_work ?? data.tasks_completed },
              { label: 'متأخر', value: data.tasks_overdue, danger: data.tasks_overdue > 0 },
              { label: 'بدون قسم', value: data.without_department },
            ].map((chip) => (
              <div
                key={chip.label}
                className={`rounded-2xl border px-4 py-3 ${
                  chip.danger ? 'border-red-300 bg-red-50/70' : 'border-slate-200 bg-white'
                }`}
              >
                <p className="text-xs text-slate-500">{chip.label}</p>
                <p className={`text-2xl font-semibold ${chip.danger ? 'text-red-900' : 'text-slate-900'}`}>
                  {chip.value.toLocaleString('ar-SA')}
                </p>
              </div>
            ))}
          </div>

          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {typeof data.printing_lateness === 'number' ? (
              <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <p className="text-xs text-slate-500">تأخر الطباعة</p>
                <p className="text-2xl font-semibold text-slate-900">
                  {data.printing_lateness.toLocaleString('ar-SA')}
                </p>
              </div>
            ) : null}
            {data.automation ? (
              <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <p className="text-xs text-slate-500">الأتمتة</p>
                <p className="text-2xl font-semibold text-slate-900">
                  {data.automation.success.toLocaleString('ar-SA')} نجح
                </p>
                <p className="text-xs text-slate-500">
                  فشل: {data.automation.failed.toLocaleString('ar-SA')}
                </p>
              </div>
            ) : null}
            {data.sla ? (
              <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <p className="text-xs text-slate-500">SLA</p>
                <p className="text-2xl font-semibold text-slate-900">
                  خرق {data.sla.breached.toLocaleString('ar-SA')}
                </p>
                <p className="text-xs text-slate-500">
                  في الوقت: {data.sla.on_time.toLocaleString('ar-SA')} · قواعد:{' '}
                  {data.sla.rules.toLocaleString('ar-SA')}
                </p>
              </div>
            ) : null}
          </div>

          <DashboardSection title="حسب القسم">
            {data.by_department.length === 0 ? (
              <p className="text-sm text-slate-500">لا أقسام بعد.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 text-slate-500">
                      <th className="px-2 py-2 text-start font-medium">القسم</th>
                      <th className="px-2 py-2 text-start font-medium">الإجمالي</th>
                      <th className="px-2 py-2 text-start font-medium">مكتمل</th>
                      <th className="px-2 py-2 text-start font-medium">متأخر</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.by_department.map((row) => (
                      <tr key={row.department_id} className="border-b border-slate-100">
                        <td className="px-2 py-2">{row.name}</td>
                        <td className="px-2 py-2">{row.total.toLocaleString('ar-SA')}</td>
                        <td className="px-2 py-2">{row.completed.toLocaleString('ar-SA')}</td>
                        <td className="px-2 py-2">{row.overdue.toLocaleString('ar-SA')}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </DashboardSection>

          {data.printing ? (
            <DashboardSection title="الطباعة التجارية">
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                  { label: 'عروض مُرسلة', value: data.printing.quotations_sent },
                  { label: 'عروض مقبولة', value: data.printing.quotations_accepted },
                  { label: 'عروض مرفوضة', value: data.printing.quotations_rejected },
                  { label: 'عدد الدفعات', value: data.printing.payments_count },
                ]
                  .filter((row) => typeof row.value === 'number')
                  .map((row) => (
                    <div key={row.label} className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                      <p className="text-xs text-slate-500">{row.label}</p>
                      <p className="text-2xl font-semibold text-slate-900">
                        {(row.value as number).toLocaleString('ar-SA')}
                      </p>
                    </div>
                  ))}
                {data.printing.revenue != null ? (
                  <div className="rounded-2xl border border-emerald-200 bg-emerald-50/60 px-4 py-3">
                    <p className="text-xs text-emerald-800">إيراد</p>
                    <p className="text-2xl font-semibold text-emerald-950">
                      {String(data.printing.revenue)}
                      {data.printing.currency ? ` ${data.printing.currency}` : ''}
                    </p>
                  </div>
                ) : null}
                {data.printing.outstanding != null ? (
                  <div className="rounded-2xl border border-amber-200 bg-amber-50/60 px-4 py-3">
                    <p className="text-xs text-amber-900">متبقي</p>
                    <p className="text-2xl font-semibold text-amber-950">
                      {String(data.printing.outstanding)}
                      {data.printing.currency ? ` ${data.printing.currency}` : ''}
                    </p>
                  </div>
                ) : null}
              </div>
            </DashboardSection>
          ) : null}
        </>
      ) : null}
    </section>
  )
}
