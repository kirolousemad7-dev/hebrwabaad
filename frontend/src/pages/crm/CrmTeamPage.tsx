import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCrmTeam } from '../../services/crm'
import { EMPLOYEE_ROLE_LABELS } from '../../utils/staff'

const ROLE_LABELS: Record<string, string> = {
  ...EMPLOYEE_ROLE_LABELS,
  OWNER: 'المالك',
  SALES_MANAGER: 'مدير مبيعات',
  SALES_REPRESENTATIVE: 'مندوب مبيعات',
}

export function CrmTeamPage() {
  const { state, reload } = useAsyncData(() => getCrmTeam())
  const items = state.status === 'ready' ? state.data.items : []

  return (
    <DashboardSection title="فريق المبيعات" description="أعضاء فريق CRM النشطون.">
      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل الفريق..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}

      {state.status === 'ready' && items.length === 0 ? (
        <DashboardEmptyState title="لا أعضاء في الفريق." description="أضف مدير أو مندوب مبيعات من إدارة الموظفين." />
      ) : null}

      {items.length > 0 ? (
        <div className="overflow-x-auto rounded-2xl border bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-right">
              <tr>
                <th className="px-3 py-2">الاسم</th>
                <th className="px-3 py-2">البريد</th>
                <th className="px-3 py-2">الدور</th>
              </tr>
            </thead>
            <tbody>
              {items.map((member) => (
                <tr key={member.id} className="border-t">
                  <td className="px-3 py-2 font-medium">{member.name}</td>
                  <td className="px-3 py-2" dir="ltr">
                    {member.email}
                  </td>
                  <td className="px-3 py-2">{ROLE_LABELS[member.role] ?? member.role}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </DashboardSection>
  )
}
