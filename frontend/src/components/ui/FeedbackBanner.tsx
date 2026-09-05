type FeedbackKind = 'error' | 'success' | 'warning' | 'info'

type FeedbackBannerProps = {
  kind: FeedbackKind
  children: React.ReactNode
  className?: string
}

const KIND_CLASS: Record<FeedbackKind, string> = {
  error: 'border-red-200 bg-red-50 text-red-800',
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  warning: 'border-amber-200 bg-amber-50 text-amber-950',
  info: 'border-slate-200 bg-white text-slate-700',
}

export function FeedbackBanner({ kind, children, className }: FeedbackBannerProps) {
  return (
    <div
      role={kind === 'error' ? 'alert' : 'status'}
      className={[
        'rounded-xl border px-4 py-3 text-sm leading-7',
        KIND_CLASS[kind],
        className,
      ]
        .filter(Boolean)
        .join(' ')}
    >
      {children}
    </div>
  )
}
