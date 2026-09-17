import { BrandLogo } from '../brand/BrandLogo'
import { FeedbackBanner } from '../ui/FeedbackBanner'

export type VerificationUiStatus = 'verified' | 'pending' | 'expired' | 'loading' | 'error' | 'idle'

const STATUS_LABEL: Record<'verified' | 'pending' | 'expired', string> = {
  verified: 'Verified',
  pending: 'Pending',
  expired: 'Expired',
}

const STATUS_CLASS: Record<'verified' | 'pending' | 'expired', string> = {
  verified: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  pending: 'border-amber-200 bg-amber-50 text-amber-950',
  expired: 'border-red-200 bg-red-50 text-red-800',
}

export function VerificationStatusBadge({ status }: { status: 'verified' | 'pending' | 'expired' }) {
  return (
    <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-medium ${STATUS_CLASS[status]}`}>
      {STATUS_LABEL[status]}
    </span>
  )
}

type VerificationScreenProps = {
  title: string
  description: string
  status?: VerificationUiStatus
  error?: string | null
  info?: string | null
  children?: React.ReactNode
}

export function VerificationScreen({
  title,
  description,
  status = 'idle',
  error,
  info,
  children,
}: VerificationScreenProps) {
  return (
    <section className="mx-auto max-w-md space-y-6 py-8">
      <div className="flex justify-center">
        <BrandLogo size="auth" to="/" />
      </div>
      <div className="space-y-2 text-center">
        <h1 className="text-2xl font-semibold">{title}</h1>
        <p className="text-sm text-slate-600">{description}</p>
        {status === 'verified' || status === 'pending' || status === 'expired' ? (
          <div className="pt-1">
            <VerificationStatusBadge status={status} />
          </div>
        ) : null}
      </div>
      <div className="space-y-4 rounded-2xl border bg-white p-6 shadow-sm">
        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
        {info ? <FeedbackBanner kind="success">{info}</FeedbackBanner> : null}
        {status === 'loading' ? <p className="text-sm text-slate-500">جاري التحميل...</p> : null}
        {children}
      </div>
    </section>
  )
}
