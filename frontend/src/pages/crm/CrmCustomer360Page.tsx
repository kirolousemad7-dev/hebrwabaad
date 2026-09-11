import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { getCrmCustomer360, type CrmCustomer360 } from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { CRM_ACTIVITY_TYPE_LABELS, CRM_QUOTATION_STATUS_LABELS, crmStatusLabel } from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

function Kpi({ title, value }: { title: string; value: string }) {
  return (
    <article className="rounded-2xl border bg-white p-4 shadow-sm">
      <p className="text-sm text-slate-600">{title}</p>
      <p className="mt-2 text-xl font-semibold">{value}</p>
    </article>
  )
}

export function CrmCustomer360Page() {
  const { id } = useParams()
  const userId = Number(id)
  const [data, setData] = useState<CrmCustomer360 | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load() {
    if (!Number.isFinite(userId) || userId <= 0) {
      setError('معرّف غير صالح.')
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmCustomer360(userId)
      setData(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل ملف العميل 360.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId])

  if (loading) return <DashboardPanelSkeleton label="جاري تحميل ملف العميل..." />
  if (error) return <DashboardErrorState message={error} onRetry={() => void load()} />
  if (!data) return <DashboardEmptyState title="غير موجود" description="لم يتم العثور على العميل." />

  const { customer, metrics } = data

  return (
    <section className="space-y-6">
      <header className="space-y-1">
        <Link to="/crm/leads" className="text-sm text-slate-600 hover:underline">
          ← العودة
        </Link>
        <h1 className="text-2xl font-semibold">{customer.name}</h1>
        <p className="text-sm text-slate-600" dir="ltr">
          {customer.email}
          {customer.phone ? ` · ${customer.phone}` : ''}
        </p>
      </header>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi title="عملاء محتملون" value={metrics.leads.toLocaleString('ar-SA')} />
        <Kpi title="صفقات رابحة" value={metrics.won_leads.toLocaleString('ar-SA')} />
        <Kpi title="طلبات" value={metrics.orders.toLocaleString('ar-SA')} />
        <Kpi title="قيمة العمر" value={formatMoney(metrics.lifetime_deal_value)} />
      </div>

      <DashboardSection title="العملاء المحتملون المرتبطون">
        {data.leads.length === 0 ? (
          <DashboardEmptyState title="لا عملاء محتملين." description="" />
        ) : (
          <ul className="divide-y divide-slate-100 rounded-2xl border bg-white">
            {data.leads.map((lead) => (
              <li key={lead.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <Link to={`/crm/leads/${lead.id}`} className="font-medium hover:underline">
                  {lead.full_name} · {lead.reference}
                </Link>
                <StatusBadge status={lead.status} label={crmStatusLabel(lead.status)} />
              </li>
            ))}
          </ul>
        )}
      </DashboardSection>

      <div className="grid gap-4 lg:grid-cols-2">
        <DashboardSection title="عروض الأسعار">
          {data.quotations.length === 0 ? (
            <DashboardEmptyState title="لا عروض." description="" />
          ) : (
            <ul className="divide-y divide-slate-100 rounded-2xl border bg-white text-sm">
              {data.quotations.map((quotation) => (
                <li key={quotation.id} className="flex items-center justify-between gap-2 px-4 py-3">
                  <span dir="ltr">{quotation.number}</span>
                  <StatusBadge
                    status={quotation.status}
                    label={CRM_QUOTATION_STATUS_LABELS[quotation.status] ?? quotation.status}
                  />
                  <span>{formatMoney(quotation.total, quotation.currency || 'SAR')}</span>
                </li>
              ))}
            </ul>
          )}
        </DashboardSection>

        <DashboardSection title="الأنشطة الأخيرة">
          {data.recent_activities.length === 0 ? (
            <DashboardEmptyState title="لا أنشطة." description="" />
          ) : (
            <ul className="space-y-2">
              {data.recent_activities.map((activity) => (
                <li key={activity.id} className="rounded-xl border bg-white px-3 py-2 text-sm">
                  <p className="font-medium">{CRM_ACTIVITY_TYPE_LABELS[activity.type] ?? activity.type}</p>
                  <p className="text-xs text-slate-500">
                    {activity.occurred_at ? new Date(activity.occurred_at).toLocaleString('ar-SA') : '—'}
                  </p>
                  {activity.notes ? <p className="mt-1 text-slate-700">{activity.notes}</p> : null}
                </li>
              ))}
            </ul>
          )}
        </DashboardSection>
      </div>
    </section>
  )
}
