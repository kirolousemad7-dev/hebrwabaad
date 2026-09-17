import { DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getSupplierPortalSettings } from '../../services/supplierPortal'

export function SupplierSettingsPage() {
  const { state, reload } = useAsyncData(() => getSupplierPortalSettings())

  if (state.status === 'loading') return <DashboardPanelSkeleton label="..." />
  if (state.status === 'error') return <DashboardErrorState message={state.message} onRetry={() => void reload()} />

  const settings = state.data

  return (
    <DashboardSection title="الإعدادات" description="حالة الحساب والحقول المقفلة من الإدارة.">
      <ul className="grid gap-3 sm:grid-cols-2">
        <li className="rounded-2xl border bg-white p-4">الحالة: {String(settings.status ?? '—')}</li>
        <li className="rounded-2xl border bg-white p-4">التحقق: {String(settings.verification_status ?? '—')}</li>
        <li className="rounded-2xl border bg-white p-4">التأهيل: {String(settings.onboarding_status ?? '—')}</li>
        <li className="rounded-2xl border bg-white p-4">تواصل عام: {settings.show_public_contact ? 'نعم' : 'لا'}</li>
      </ul>
      {Array.isArray(settings.locked_fields) && settings.locked_fields.length > 0 ? (
        <div className="rounded-2xl border bg-white p-4 text-sm">
          حقول مقفلة بواسطة المالك: {(settings.locked_fields as string[]).join('، ')}
        </div>
      ) : (
        <div className="rounded-2xl border bg-white p-4 text-sm">لا توجد حقول مقفلة حالياً.</div>
      )}
      {settings.owner_change_request ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">{String(settings.owner_change_request)}</div>
      ) : null}
    </DashboardSection>
  )
}
