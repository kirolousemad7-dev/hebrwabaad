import { Link } from 'react-router-dom'
import { DashboardBusinessOverview } from '../../components/owner/DashboardBusinessOverview'
import { DashboardPendingRequests } from '../../components/owner/DashboardPendingRequests'
import { DashboardQuickActions } from '../../components/owner/DashboardQuickActions'
import { DashboardRecentActivity } from '../../components/owner/DashboardRecentActivity'
import {
  DashboardErrorState,
  DashboardOverviewSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { DashboardStatCard } from '../../components/owner/DashboardStatCard'
import { useAuth } from '../../context/AuthContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getOwnerDashboard } from '../../services/ownerDashboard'
import { formatMoney } from '../../utils/catalog'
import { EMPLOYEE_ROLE_LABELS, metricSecondaryNumber } from '../../utils/ownerDashboard'

function employeeSecondaryLabel(byRole: Record<string, number> | undefined): string | null {
  if (!byRole) {
    return null
  }

  const parts = Object.entries(byRole)
    .filter(([, count]) => count > 0)
    .map(([role, count]) => {
      const label =
        role in EMPLOYEE_ROLE_LABELS
          ? EMPLOYEE_ROLE_LABELS[role as keyof typeof EMPLOYEE_ROLE_LABELS]
          : role

      return `${label}: ${count.toLocaleString('ar-SA')}`
    })

  return parts.length > 0 ? parts.join(' · ') : null
}

export function OwnerHomePage() {
  const { user } = useAuth()
  const { state, reload } = useAsyncData(() => getOwnerDashboard())

  return (
    <section className="space-y-8">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold">لوحة التحكم</h1>
        <p className="text-slate-600">مرحباً {user?.name}. نظرة على النشاط الفعلي في المنصة.</p>
      </header>

      {state.status === 'loading' ? <DashboardOverviewSkeleton /> : null}

      {state.status === 'error' ? (
        <DashboardErrorState message={`تعذر تحميل ملخص اللوحة. ${state.message}`} onRetry={() => void reload()} />
      ) : null}

      {state.status === 'ready' ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <DashboardStatCard
              title="الإيرادات"
              icon="revenue"
              metric={state.data.overview.revenue}
              emptyLabel="لا توجد إيرادات مسجّلة بعد."
              secondaryLabel={
                metricSecondaryNumber(state.data.overview.revenue, 'paid_count') !== null
                  ? `${formatMoney(
                      state.data.overview.revenue.value ?? 0,
                      String(state.data.overview.revenue.secondary.currency ?? 'SAR'),
                    )} · ${metricSecondaryNumber(state.data.overview.revenue, 'paid_count')?.toLocaleString('ar-SA')} دفعة مؤكدة`
                  : null
              }
            />
            <DashboardStatCard
              title="الطلبات"
              icon="orders"
              metric={state.data.overview.orders}
              emptyLabel="لا توجد طلبات بعد."
            />
            <DashboardStatCard
              title="العملاء"
              icon="customers"
              metric={state.data.overview.customers}
              emptyLabel="لا يوجد عملاء بعد."
              secondaryLabel={
                metricSecondaryNumber(state.data.overview.customers, 'this_month') !== null
                  ? `${metricSecondaryNumber(state.data.overview.customers, 'this_month')?.toLocaleString('ar-SA')} هذا الشهر`
                  : null
              }
            />
            <DashboardStatCard
              title="المشاريع"
              icon="projects"
              metric={state.data.overview.projects}
              emptyLabel="لا توجد مشاريع بعد."
              secondaryLabel={
                metricSecondaryNumber(state.data.overview.projects, 'in_progress') !== null
                  ? `${metricSecondaryNumber(state.data.overview.projects, 'in_progress')?.toLocaleString('ar-SA')} قيد التنفيذ`
                  : null
              }
            />
            <DashboardStatCard
              title="الموظفون"
              icon="employees"
              metric={state.data.overview.employees}
              emptyLabel="لا يوجد موظفون بعد."
              secondaryLabel={employeeSecondaryLabel(
                state.data.overview.employees.secondary.by_role as Record<string, number> | undefined,
              )}
            />
            <DashboardStatCard
              title="الموردون"
              icon="suppliers"
              metric={state.data.overview.suppliers}
              emptyLabel="لا يوجد موردون بعد."
              secondaryLabel={
                metricSecondaryNumber(state.data.overview.suppliers, 'active') !== null
                  ? `${metricSecondaryNumber(state.data.overview.suppliers, 'active')?.toLocaleString('ar-SA')} نشط`
                  : null
              }
            />
            <DashboardStatCard
              title="العملاء المحتملون"
              icon="leads"
              metric={state.data.overview.leads}
              emptyLabel="لا يوجد عملاء محتملون بعد."
              secondaryLabel={
                metricSecondaryNumber(state.data.overview.leads, 'this_month') !== null
                  ? `${metricSecondaryNumber(state.data.overview.leads, 'this_month')?.toLocaleString('ar-SA')} هذا الشهر`
                  : null
              }
            />
            <DashboardStatCard
              title="الطلبات المعلّقة"
              icon="pending"
              metric={state.data.overview.pending_requests}
              emptyLabel="لا توجد طلبات تحتاج إجراء."
              secondaryLabel={
                metricSecondaryNumber(state.data.overview.pending_requests, 'awaiting_pricing') !== null
                  ? `${metricSecondaryNumber(state.data.overview.pending_requests, 'awaiting_pricing')?.toLocaleString('ar-SA')} بانتظار التسعير`
                  : null
              }
            />
          </div>

          <DashboardSection
            title="أداء النشاط الحالي"
            description="ملخص مبني على طلبات الطباعة المسجّلة. لا يُعرض إيراد أو طلبات غير موجودة في النظام."
          >
            <DashboardBusinessOverview
              requestActivity={state.data.request_activity}
              pricingBreakdown={state.data.pricing_breakdown}
            />
          </DashboardSection>

          <div className="grid gap-8 xl:grid-cols-5">
            <div className="xl:col-span-3">
              <DashboardSection title="النشاط الأخير">
                <DashboardRecentActivity items={state.data.recent_activity} />
              </DashboardSection>
            </div>
            <div className="xl:col-span-2">
              <DashboardSection
                title="طلبات تحتاج إجراء"
                action={
                  <Link
                    to="/printing-requests"
                    className="text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                  >
                    عرض الكل
                  </Link>
                }
              >
                <DashboardPendingRequests items={state.data.pending_requests} />
              </DashboardSection>
            </div>
          </div>
        </>
      ) : null}

      <DashboardSection title="إجراءات سريعة" description="اختصارات للصفحات المتاحة حالياً لمالك المنصة.">
        <DashboardQuickActions />
      </DashboardSection>
    </section>
  )
}
