import { Link } from 'react-router-dom'

export type PublicCtaVariant = 'primary' | 'secondary' | 'inverse'

type PublicCtaProps = {
  to: string
  children: React.ReactNode
  variant?: PublicCtaVariant
}

const publicCtaClassName: Record<PublicCtaVariant, string> = {
  primary:
    'brand-btn-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
  secondary:
    'brand-btn-secondary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
  inverse:
    'brand-btn-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white',
}

export function PublicCta({ to, children, variant = 'primary' }: PublicCtaProps) {
  return (
    <Link to={to} className={publicCtaClassName[variant]}>
      {children}
    </Link>
  )
}
