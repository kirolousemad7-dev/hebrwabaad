import { BrandLogo } from '../brand/BrandLogo'
import { PublicCta, type PublicCtaVariant } from '../public/PublicCta'

type CatalogErrorStateProps = {
  message: string
  onRetry: () => void
}

export function CatalogErrorState({ message, onRetry }: CatalogErrorStateProps) {
  return (
    <div className="space-y-3 rounded-lg border border-red-200 bg-red-50 px-4 py-5 text-sm text-red-800" role="alert">
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

export type CatalogEmptyAction = {
  to: string
  label: string
  variant?: PublicCtaVariant
}

type CatalogEmptyStateProps = {
  title: string
  description: string
  actions?: CatalogEmptyAction[]
}

const DEFAULT_EMPTY_ACTIONS: CatalogEmptyAction[] = [
  { to: '/services', label: 'الخدمات', variant: 'primary' },
  { to: '/packages', label: 'كل الباقات', variant: 'secondary' },
]

export function CatalogEmptyState({
  title,
  description,
  actions = DEFAULT_EMPTY_ACTIONS,
}: CatalogEmptyStateProps) {
  return (
    <div className="space-y-3 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
      <BrandLogo size="mark" to={null} className="justify-center opacity-90" />
      <h2 className="text-lg font-semibold">{title}</h2>
      <p className="text-sm text-slate-600">{description}</p>
      {actions.length > 0 ? (
        <div className="flex flex-wrap justify-center gap-3">
          {actions.map((action) => (
            <PublicCta key={action.to} to={action.to} variant={action.variant ?? 'secondary'}>
              {action.label}
            </PublicCta>
          ))}
        </div>
      ) : null}
    </div>
  )
}

type CatalogSkeletonProps = {
  label: string
  variant?: 'packages' | 'services' | 'list'
}

export function CatalogSkeleton({ label, variant = 'packages' }: CatalogSkeletonProps) {
  if (variant === 'services' || variant === 'list') {
    return (
      <div className="space-y-5" aria-busy="true" aria-live="polite">
        <p className="sr-only">{label}</p>
        <div className={variant === 'services' ? 'grid gap-4 sm:grid-cols-2' : 'grid gap-4 lg:grid-cols-2'}>
          {[0, 1, 2, 3].map((index) => (
            <div
              key={index}
              className={`animate-pulse rounded-lg bg-slate-200 ${variant === 'list' ? 'h-56' : 'h-36'}`}
            />
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-5" aria-busy="true" aria-live="polite">
      <p className="sr-only">{label}</p>
      <div className="grid gap-3 sm:grid-cols-3">
        {[0, 1, 2].map((index) => (
          <div key={index} className="h-24 animate-pulse rounded-lg bg-slate-200" />
        ))}
      </div>
      <div className="grid gap-5 lg:grid-cols-2">
        {[0, 1].map((index) => (
          <div key={index} className="h-64 animate-pulse rounded-2xl bg-slate-200" />
        ))}
      </div>
    </div>
  )
}
