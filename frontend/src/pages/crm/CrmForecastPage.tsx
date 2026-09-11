import { useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardOverviewSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { getCrmForecast, type CrmForecastData } from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'

function KpiCard({ title, value, hint }: { title: string; value: string; hint?: string }) {
  return (
    <article className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p className="text-sm text-slate-600">{title}</p>
      <p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p>
      {hint ? <p className="mt-1 text-xs text-slate-500">{hint}</p> : null}
    </article>
  )
}

export function CrmForecastPage() {
  const [data, setData] = useState<CrmForecastData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmForecast()
      setData(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل التوقعات.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  return (
    <DashboardSection title="توقعات المبيعات" description="خط الأنابيب المرجح والالتزام وأفضل حالة.">
      {loading ? <DashboardOverviewSkeleton /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && !data ? (
        <DashboardEmptyState title="لا بيانات توقعات." description="أضف فرصاً مفتوحة لرؤية التوقعات." />
      ) : null}

      {!loading && !error && data ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <KpiCard title="إجمالي خط الأنابيب" value={formatMoney(data.pipeline_total)} hint={`${data.open_count.toLocaleString('ar-SA')} فرصة مفتوحة`} />
          <KpiCard title="مرجّح" value={formatMoney(data.weighted)} hint="حسب احتمالية كل فرصة" />
          <KpiCard title="التزام (≥ 80%)" value={formatMoney(data.commit)} />
          <KpiCard title="أفضل حالة (≥ 50%)" value={formatMoney(data.best_case)} />
          <KpiCard title="متوقع هذا الشهر" value={formatMoney(data.expected_this_month)} />
          <KpiCard title="متوقع الشهر القادم" value={formatMoney(data.expected_next_month)} />
        </div>
      ) : null}
    </DashboardSection>
  )
}
