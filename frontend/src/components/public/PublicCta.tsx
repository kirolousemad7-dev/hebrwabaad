import { Link } from 'react-router-dom'

export type PublicCtaVariant = 'primary' | 'secondary' | 'inverse'

type PublicCtaProps = {
  to: string
  children: React.ReactNode
  variant?: PublicCtaVariant
}

const publicCtaClassName: Record<PublicCtaVariant, string> = {
  primary:
    'inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
  secondary:
    'inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
  inverse:
    'inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-amber-400 px-4 py-2.5 text-sm font-medium text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white',
}

export function PublicCta({ to, children, variant = 'primary' }: PublicCtaProps) {
  return (
    <Link to={to} className={publicCtaClassName[variant]}>
      {children}
    </Link>
  )
}
