type DashboardSectionProps = {
  title: string
  description?: string
  action?: React.ReactNode
  children: React.ReactNode
}

export function DashboardSection({ title, description, action, children }: DashboardSectionProps) {
  return (
    <section className="space-y-4">
      <div className="flex min-w-0 flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 space-y-1">
          <h2 className="text-lg font-semibold">{title}</h2>
          {description ? <p className="text-sm text-slate-600">{description}</p> : null}
        </div>
        {action}
      </div>
      {children}
    </section>
  )
}

export function DashboardErrorState({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <div className="space-y-3 rounded-xl border border-red-200 bg-red-50 px-4 py-5 text-sm text-red-800" role="alert">
      <p>{message}</p>
      <button
        type="button"
        onClick={onRetry}
        className="rounded-lg bg-red-800 px-4 py-2 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-800"
      >
        إعادة المحاولة
      </button>
    </div>
  )
}

export function DashboardEmptyState({
  title,
  description,
  action,
}: {
  title: string
  description: string
  action?: React.ReactNode
}) {
  return (
    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center">
      <p className="font-medium">{title}</p>
      <p className="mt-1 text-sm leading-7 text-slate-600">{description}</p>
      {action ? <div className="mt-4 flex justify-center">{action}</div> : null}
    </div>
  )
}

export function DashboardOverviewSkeleton() {
  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true" aria-live="polite">
      <p className="sr-only">جاري تحميل لوحة التحكم...</p>
      {Array.from({ length: 8 }, (_, index) => (
        <div key={index} className="h-36 animate-pulse rounded-2xl bg-slate-200" />
      ))}
    </div>
  )
}

export function DashboardPanelSkeleton({ label }: { label: string }) {
  return (
    <div className="space-y-3" aria-busy="true" aria-live="polite">
      <p className="sr-only">{label}</p>
      <div className="h-48 animate-pulse rounded-2xl bg-slate-200" />
    </div>
  )
}
