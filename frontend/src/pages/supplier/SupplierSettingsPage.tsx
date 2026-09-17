import { Link } from 'react-router-dom'
import { DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { VerificationStatusBadge } from '../../components/verification/VerificationScreen'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getSupplierEmailStatus, getSupplierPortalSettings } from '../../services/supplierPortal'

export function SupplierSettingsPage() {
  const settingsQuery = useAsyncData(() => getSupplierPortalSettings())
  const emailQuery = useAsyncData(() => getSupplierEmailStatus())

  if (settingsQuery.state.status === 'loading') return <DashboardPanelSkeleton label="..." />
  if (settingsQuery.state.status === 'error') {
    return <DashboardErrorState message={settingsQuery.state.message} onRetry={() => void settingsQuery.reload()} />
  }

  const settings = settingsQuery.state.data
  const emailStatus = emailQuery.state.status === 'ready' ? emailQuery.state.data.status : null

  return (
    <DashboardSection title="الإعدادات" description="حالة الحساب والحقول المقفلة من الإدارة.">
      <ul className="grid gap-3 sm:grid-cols-2">
        <li className="rounded-2xl border bg-white p-4">الحالة: {String(settings.status ?? '—')}</li>
        <li className="rounded-2xl border bg-white p-4">التحقق: {String(settings.verification_status ?? '—')}</li>
        <li className="rounded-2xl border bg-white p-4">التأهيل: {String(settings.onboarding_status ?? '—')}</li>
        <li className="rounded-2xl border bg-white p-4">تواصل عام: {settings.show_public_contact ? 'نعم' : 'لا'}</li>
      </ul>

      <div className="grid gap-3 sm:grid-cols-2">
        <div className="space-y-3 rounded-2xl border bg-white p-4 text-sm">
          <div className="flex items-center justify-between gap-2">
            <span>البريد</span>
            {emailStatus === 'verified' || emailStatus === 'pending' || emailStatus === 'expired' ? (
              <VerificationStatusBadge status={emailStatus} />
            ) : (
              <span className="text-slate-500">—</span>
            )}
          </div>
          <Link className="underline" to="/verify-email">إدارة تأكيد البريد</Link>
        </div>
        <div className="space-y-3 rounded-2xl border bg-white p-4 text-sm">
          <div className="flex items-center justify-between gap-2">
            <span>الهاتف</span>
            {settings.phone_verified_at ? (
              <VerificationStatusBadge status="verified" />
            ) : (
              <VerificationStatusBadge status="pending" />
            )}
          </div>
          <Link className="underline" to="/verify-phone">إدارة تأكيد الهاتف</Link>
        </div>
      </div>

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

      <button
        type="button"
        className="text-sm underline"
        onClick={() => {
          void settingsQuery.reload()
          void emailQuery.reload()
        }}
      >
        تحديث الحالة
      </button>
    </DashboardSection>
  )
}
