import { Link } from 'react-router-dom'

type LandingCtaProps = {
  children: React.ReactNode
  to?: string
  href?: string
  /** @deprecated Use `primary` — kept for call-site compatibility. */
  variant?: 'primary' | 'secondary' | 'dark' | 'ghost' | 'gold' | 'light' | 'navy'
  onClick?: (event: React.MouseEvent<HTMLAnchorElement>) => void
}

const className: Record<string, string> = {
  primary:
    'brand-btn-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
  gold: 'brand-btn-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
  secondary:
    'brand-btn-secondary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
  ghost:
    'brand-btn-ghost border border-brand-ink-100 bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
  dark: 'brand-btn-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
  navy: 'brand-btn-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
  light:
    'inline-flex min-h-11 items-center justify-center rounded-[0.875rem] border border-white/35 bg-white/10 px-5 text-sm font-medium text-white transition hover:bg-white hover:text-brand-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white',
}

export function LandingCta({ children, to, href, variant = 'primary', onClick }: LandingCtaProps) {
  const classes = className[variant] ?? className.primary

  if (href) {
    return (
      <a href={href} className={classes} onClick={onClick}>
        {children}
      </a>
    )
  }

  return (
    <Link to={to ?? '/register'} className={classes} onClick={onClick}>
      {children}
    </Link>
  )
}
